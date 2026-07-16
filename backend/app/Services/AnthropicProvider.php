<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;

class AnthropicProvider extends BaseAIProvider
{
    private const MAX_TOKENS = 4096;

    public function id(): string
    {
        return 'anthropic';
    }

    public function models(): array
    {
        return [
            'claude-sonnet-4-20250514',
            'claude-3-5-sonnet-20241022',
            'claude-3-5-haiku-20241022',
            'claude-3-opus-20240229',
        ];
    }

    protected function configKey(): ?string
    {
        return 'services.anthropic.key';
    }

    public function defaultModel(): string
    {
        return (string) config('services.anthropic.model', 'claude-sonnet-4-20250514');
    }

    protected function configureRequest(PendingRequest $http): PendingRequest
    {
        return $http->withHeaders([
            'x-api-key' => $this->resolvedKey,
            'anthropic-version' => '2023-06-01',
        ]);
    }

    protected function endpoint(string $model): string
    {
        return 'https://api.anthropic.com/v1/messages';
    }

    protected function buildPayload(string $prompt, array $schema, string $model): array
    {
        return [
            'model' => $model,
            'max_tokens' => self::MAX_TOKENS,
            'system' => $this->systemMessage(),
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];
    }

    protected function extractContent(array $response): string
    {
        return (string) ($response['content'][0]['text'] ?? '');
    }

    protected function verifyEndpoint(): string
    {
        return 'https://api.anthropic.com/v1/models?limit=1';
    }

    protected function systemMessage(): string
    {
        return $this->jsonOnlySystemMessage();
    }
}
