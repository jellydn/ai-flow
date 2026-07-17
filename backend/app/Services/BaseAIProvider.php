<?php

namespace App\Services;

use App\Contracts\AIProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Deep base module for AI provider adapters.
 *
 * Owns the HTTP request lifecycle shared by every provider:
 * key resolution (injected → server config fallback), timeout, retry,
 * 401/403 → "Invalid API key." mapping, non-success → "AI provider
 * request failed (HTTP {status}).", ConnectionException → "Unable to
 * reach the AI provider...", and json_decode + "invalid JSON" guard.
 *
 * Each concrete subclass declares only its provider-specific *shape*
 * via small protected hooks:
 *  - configureRequest():  attach auth headers / provider-specific headers
 *  - endpoint():          the request URL (may bake in model/key for Gemini)
 *  - buildPayload():      the provider-specific request body
 *  - extractContent():    the JSON path into the response body
 *  - verifyEndpoint():    the lightweight GET URL for credential verification
 *  - configKey():         which config key holds the server-side API key
 *  - defaultModel():      the configured default model for this provider
 *  - systemMessage():     the system prompt instruction (overridable)
 *
 * Extends ADR-0017 by pulling the duplicated adapter plumbing behind
 * a single seam, making AIProviderInterface a real test surface.
 */
abstract class BaseAIProvider implements AIProviderInterface
{
    /** Retry attempts for transient failures (shared across all providers). */
    protected const RETRY_ATTEMPTS = 2;

    /** Delay between retries in milliseconds. */
    protected const RETRY_DELAY_MS = 500;

    /**
     * Timeout for credential verification GETs (seconds).
     *
     * Intentionally shorter than the generate timeout — verification
     * is a lightweight /models?limit=1 request, not a full AI call.
     */
    protected const VERIFY_TIMEOUT_SECONDS = 10;

    public function __construct(
        protected ?string $apiKey = null,
    ) {}

    /**
     * Resolved API key available to hooks (configureRequest, endpoint, verifyEndpoint).
     * Set by generate()/verifyCredential() before any hook runs.
     */
    protected ?string $resolvedKey = null;

    // ─── AIProviderInterface (shared implementations) ────────────────

    /**
     * Verify a credential by making the smallest practical request.
     *
     * The base owns the full verify lifecycle: key resolution, request
     * building, status mapping, and the result structure. Subclasses
     * only declare their verify endpoint via verifyEndpoint().
     */
    public function verifyCredential(string $apiKey): array
    {
        $this->resolvedKey = $this->resolveKey($apiKey);

        if ($this->resolvedKey === null || $this->resolvedKey === '') {
            return ['valid' => false, 'message' => 'The AI provider API key is not configured.'];
        }

        try {
            $response = $this->configureRequest($this->baseVerifyRequest())
                ->timeout(self::VERIFY_TIMEOUT_SECONDS)
                ->get($this->verifyEndpoint());

            if (in_array($response->status(), [401, 403], true)) {
                return ['valid' => false, 'message' => 'Invalid API key.'];
            }

            if (! $response->successful()) {
                return ['valid' => false, 'message' => 'Provider verification failed (HTTP '.$response->status().').'];
            }

            return ['valid' => true, 'message' => 'Credential verified successfully.'];
        } catch (\Throwable) {
            return ['valid' => false, 'message' => 'Unable to reach the provider. Check your network and try again.'];
        }
    }

    /**
     * Generate a structured JSON response from the AI provider.
     *
     * The base owns the full generate lifecycle: key resolution, model
     * resolution, request building (auth + timeout + retry), status
     * mapping, response extraction, and JSON decode. Subclasses declare
     * their shape via the protected hooks.
     */
    public function generate(string $prompt, array $schema, ?string $model = null): array
    {
        $this->resolvedKey = $this->resolveKey();
        if ($this->resolvedKey === null || $this->resolvedKey === '') {
            throw new RuntimeException('The AI provider API key is not configured.');
        }

        $timeout = $this->resolveTimeout();
        if ($timeout <= 0) {
            throw new RuntimeException('The AI provider timeout is not configured.');
        }

        $resolvedModel = $model ?: $this->defaultModel();

        try {
            $response = $this->configureRequest($this->baseGenerateRequest($timeout))
                ->retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, throw: false)
                ->post($this->endpoint($resolvedModel), $this->buildPayload($prompt, $schema, $resolvedModel));

            if (in_array($response->status(), [401, 403], true)) {
                throw new RuntimeException('Invalid API key.');
            }
            if (! $response->successful()) {
                throw new RuntimeException('AI provider request failed (HTTP '.$response->status().').');
            }

            $raw = $this->extractContent($response->json() ?? []);
            $json = json_decode($raw, true);
            if (! is_array($json)) {
                $error = json_last_error_msg();
                $preview = mb_substr($raw, 0, 200);
                throw new RuntimeException("AI provider returned invalid JSON (json error: {$error}, preview: {$preview}).");
            }

            return $json;
        } catch (ConnectionException) {
            throw new RuntimeException('Unable to reach the AI provider. Check your network.');
        }
    }

    // ─── Shared lifecycle helpers ────────────────────────────────────

    /**
     * Resolve the effective API key: injected key → server config fallback.
     */
    protected function resolveKey(?string $override = null): ?string
    {
        $key = $override ?? $this->apiKey;
        if ($key !== null && $key !== '') {
            return $key;
        }

        $configKey = $this->configKey();

        return $configKey !== null ? config($configKey) : null;
    }

    /**
     * Resolve the shared generate timeout from services.ai.timeout
     * with backward-compat fallback to OPENAI_TIMEOUT.
     */
    protected function resolveTimeout(): int
    {
        return (int) config('services.ai.timeout', config('services.openai.timeout', 30));
    }

    /**
     * Base PendingRequest for generate (before subclass configureRequest hook).
     */
    private function baseGenerateRequest(int $timeout): PendingRequest
    {
        return Http::acceptJson()->timeout($timeout);
    }

    /**
     * Base PendingRequest for verify (before subclass configureRequest hook).
     */
    private function baseVerifyRequest(): PendingRequest
    {
        return Http::acceptJson();
    }

    // ─── Hooks: subclasses declare their provider-specific shape ─────

    /**
     * Decorate the PendingRequest with auth headers and provider-specific headers.
     *
     * The base has already set acceptJson + timeout. The subclass adds
     * auth (Bearer token, x-api-key, etc.) and any provider headers
     * (HTTP-Referer, X-Title, anthropic-version). Gemini's auth is
     * baked into its endpoint URL, so it can leave this as a no-op.
     */
    abstract protected function configureRequest(PendingRequest $http): PendingRequest;

    /**
     * The full request URL for generate, possibly including the model
     * (Gemini) or API key (Gemini) as path/query segments.
     */
    abstract protected function endpoint(string $model): string;

    /**
     * The provider-specific request body for generate.
     */
    abstract protected function buildPayload(string $prompt, array $schema, string $model): array;

    /**
     * Extract the text content from the provider's JSON response,
     * which the base will json_decode and validate.
     */
    abstract protected function extractContent(array $response): string;

    /**
     * The lightweight GET URL for credential verification.
     * May bake in the API key (e.g. Gemini's ?key= query param).
     */
    abstract protected function verifyEndpoint(): string;

    /**
     * The config key that holds the server-side API key for this provider
     * (e.g. 'services.openai.key'). Return null if no config fallback.
     */
    abstract protected function configKey(): ?string;

    /**
     * The configured default model for this provider.
     * Public because AIProviderInterface requires it; the registry delegates to it.
     */
    abstract public function defaultModel(): string;

    /**
     * The system message instructing the provider to return JSON.
     *
     * Providers that enforce a JSON schema (OpenAI, OpenRouter) use the
     * short form. Providers relying on prompting alone (Anthropic, Gemini)
     * override to append "Output only the JSON, no other text."
     */
    protected function systemMessage(): string
    {
        return 'Return accurate JSON matching the supplied schema.';
    }

    /**
     * System message for providers that need an explicit JSON-only instruction
     * (Anthropic, Gemini — no native json_schema enforcement).
     */
    protected function jsonOnlySystemMessage(): string
    {
        return 'Return accurate JSON matching the supplied schema. Output only the JSON, no other text.';
    }
}
