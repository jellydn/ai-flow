<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * OpenRouter provider adapter.
 *
 * OpenRouter is OpenAI-compatible (same /chat/completions endpoint)
 * but has its own model naming convention and base URL, and requires
 * HTTP-Referer and X-Title headers for ranking/identification.
 */
class OpenRouterProvider extends BaseAIProvider
{
    public function __construct(
        ?string $apiKey = null,
        private ?string $baseUrl = null,
        private ?string $referer = null,
    ) {
        parent::__construct($apiKey);
        $this->baseUrl ??= config('services.openai.openrouter_base_url', 'https://openrouter.ai/api/v1');
        $this->referer ??= config('services.openai.referer', config('app.url'));
    }

    public function id(): string
    {
        return 'openrouter';
    }

    public function models(): array
    {
        return [
            'openrouter/free',
            'openai/gpt-4o-mini',
            'openai/gpt-4o',
            'anthropic/claude-sonnet-4',
            'anthropic/claude-3.5-haiku',
            'google/gemini-2.0-flash-exp',
            'meta-llama/llama-3.3-70b-instruct',
        ];
    }

    protected function configKey(): ?string
    {
        return 'services.openai.openrouter_key';
    }

    public function defaultModel(): string
    {
        return (string) config('services.openai.openrouter_model', 'openai/gpt-4o-mini');
    }

    protected function configureRequest(PendingRequest $http): PendingRequest
    {
        return $http
            ->withToken($this->resolvedKey)
            ->withHeaders([
                'HTTP-Referer' => $this->referer,
                'X-Title' => config('app.name'),
            ]);
    }

    protected function endpoint(string $model): string
    {
        return rtrim((string) $this->baseUrl, '/').'/chat/completions';
    }

    protected function buildPayload(string $prompt, array $schema, string $model): array
    {
        // The free router has no endpoints for strict json_schema requests.
        // Supply the schema in the prompt; RunExecutor still validates the result.
        $isFreeRouter = $model === 'openrouter/free';
        $systemMessage = $isFreeRouter
            ? $this->jsonOnlySystemMessage()."\nJSON schema:\n".json_encode($schema, JSON_THROW_ON_ERROR)
            : $this->systemMessage();

        return [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => $isFreeRouter ? ['type' => 'json_object'] : [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'launcher_result', 'strict' => true, 'schema' => $schema],
            ],
            'provider' => ['require_parameters' => true],
        ];
    }

    protected function sendGenerateRequest(PendingRequest $http, string $model, array $payload): Response
    {
        if ($model !== 'openrouter/free') {
            return parent::sendGenerateRequest($http, $model, $payload);
        }

        // Keep fallback attempts within one generation timeout, not one per route.
        $deadline = microtime(true) + $this->resolveTimeout();
        for ($attempt = 1; ; $attempt++) {
            try {
                $response = $http->timeout(max(1, (int) ceil($deadline - microtime(true))))
                    ->post($this->endpoint($model), $payload);
            } catch (ConnectionException $e) {
                if ($attempt >= 3 || microtime(true) >= $deadline) {
                    throw $e;
                }

                usleep(self::RETRY_DELAY_MS * 1000);

                continue;
            }

            $error = $response->json('error') ?? $response->json('choices.0.error');
            $content = (string) ($response->json('choices.0.message.content') ?? '');
            if ($response->successful() && $error === null && trim($content) !== '') {
                return $response;
            }

            $provider = $error['metadata']['provider_name'] ?? $response->json('provider');
            Log::warning('OpenRouter generation failed', [
                'model' => $model,
                'provider' => $provider,
                'http_status' => $response->status(),
                'error_code' => $error['code'] ?? null,
                'finish_reason' => $response->json('choices.0.finish_reason'),
                'content_length' => strlen($content),
                'response_id' => $response->json('id'),
            ]);

            if ($attempt >= 3 || microtime(true) >= $deadline
                || in_array($response->status(), [400, 401, 402, 403], true)) {
                return $response;
            }

            // A free route can select a broken upstream again on a blind retry.
            $failedProviders = array_column($error['metadata']['previous_errors'] ?? [], 'provider_name');
            $failedProviders[] = $provider;
            $payload['provider']['ignore'] = array_values(array_unique(array_filter([
                ...($payload['provider']['ignore'] ?? []), ...$failedProviders,
            ])));
            usleep(self::RETRY_DELAY_MS * 1000);
        }
    }

    protected function extractContent(array $response): string
    {
        if (isset($response['error']) || isset($response['choices'][0]['error'])) {
            throw new RuntimeException('AI provider failed while generating the response. Please try again.');
        }

        $content = (string) ($response['choices'][0]['message']['content'] ?? '');
        if (trim($content) === '') {
            throw new RuntimeException('AI provider returned no content. Please try again.');
        }

        return $content;
    }

    protected function verifyEndpoint(): string
    {
        return rtrim((string) $this->baseUrl, '/').'/key';
    }
}
