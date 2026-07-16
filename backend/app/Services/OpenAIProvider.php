<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;

class OpenAIProvider extends BaseAIProvider
{
    public function id(): string
    {
        return 'openai';
    }

    public function models(): array
    {
        return [
            'gpt-4o-mini',
            'gpt-4o',
            'gpt-4-turbo',
            'gpt-3.5-turbo',
        ];
    }

    protected function configKey(): ?string
    {
        return 'services.openai.key';
    }

    public function defaultModel(): string
    {
        return (string) config('services.openai.model', 'gpt-4o-mini');
    }

    protected function configureRequest(PendingRequest $http): PendingRequest
    {
        return $http->withToken($this->resolvedKey);
    }

    protected function endpoint(string $model): string
    {
        $baseUrl = rtrim((string) config('services.openai.base_url'), '/');

        return $baseUrl.'/chat/completions';
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
        ];
    }

    protected function extractContent(array $response): string
    {
        return (string) ($response['choices'][0]['message']['content'] ?? '');
    }

    protected function verifyEndpoint(): string
    {
        $baseUrl = rtrim((string) config('services.openai.base_url'), '/');

        return $baseUrl.'/models?limit=1';
    }
}
