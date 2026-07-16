<?php

namespace App\Services;

use App\Contracts\AIProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenRouter provider adapter.
 *
 * OpenRouter is OpenAI-compatible (same /chat/completions endpoint)
 * but has its own model naming convention (e.g. "anthropic/claude-sonnet-4")
 * and its own base URL. It requires HTTP-Referer and X-Title headers
 * for ranking/identification.
 */
class OpenRouterProvider implements AIProviderInterface
{
    public function __construct(
        private ?string $apiKey = null,
        private ?string $baseUrl = null,
        private ?string $referer = null,
    ) {
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
            'openai/gpt-4o-mini',
            'openai/gpt-4o',
            'anthropic/claude-sonnet-4',
            'anthropic/claude-3.5-haiku',
            'google/gemini-2.0-flash-exp',
            'meta-llama/llama-3.3-70b-instruct',
        ];
    }

    public function verifyCredential(string $apiKey): array
    {
        $key = $apiKey ?: $this->apiKey;
        if (! $key) {
            return ['valid' => false, 'message' => 'The AI provider API key is not configured.'];
        }

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->withHeaders([
                    'HTTP-Referer' => $this->referer,
                    'X-Title' => config('app.name'),
                ])
                ->timeout(10)
                ->get($this->baseUrl.'/key');

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

    public function generate(string $prompt, array $schema, ?string $model = null): array
    {
        $key = $this->apiKey ?: config('services.openai.openrouter_key');
        if (! $key) {
            throw new RuntimeException('The AI provider API key is not configured.');
        }

        $model = $model ?: config('services.openai.openrouter_model', 'openai/gpt-4o-mini');
        $timeout = (int) config('services.openai.timeout', 30);
        if ($timeout <= 0) {
            throw new RuntimeException('The AI provider timeout is not configured.');
        }

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->withHeaders([
                    'HTTP-Referer' => $this->referer,
                    'X-Title' => config('app.name'),
                ])
                ->timeout($timeout)
                ->retry(2, 500, throw: false)
                ->post($this->baseUrl.'/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Return accurate JSON matching the supplied schema.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => ['name' => 'launcher_result', 'strict' => true, 'schema' => $schema],
                    ],
                    'provider' => ['require_parameters' => true],
                ]);

            if (in_array($response->status(), [401, 403], true)) {
                throw new RuntimeException('Invalid API key.');
            }
            if (! $response->successful()) {
                throw new RuntimeException('AI provider request failed (HTTP '.$response->status().').');
            }

            $json = json_decode($response->json('choices.0.message.content', ''), true);
            if (! is_array($json)) {
                throw new RuntimeException('AI provider returned invalid JSON.');
            }

            return $json;
        } catch (ConnectionException) {
            throw new RuntimeException('Unable to reach the AI provider. Check your network.');
        }
    }
}
