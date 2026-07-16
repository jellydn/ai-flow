<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;

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
        return [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $this->systemMessage()],
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'launcher_result', 'strict' => true, 'schema' => $schema],
            ],
            'provider' => ['require_parameters' => true],
        ];
    }

    protected function extractContent(array $response): string
    {
        return (string) ($response['choices'][0]['message']['content'] ?? '');
    }

    protected function verifyEndpoint(): string
    {
        return rtrim((string) $this->baseUrl, '/').'/key';
    }
}
