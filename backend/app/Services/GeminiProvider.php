<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;

class GeminiProvider extends BaseAIProvider
{
    public function id(): string
    {
        return 'gemini';
    }

    public function models(): array
    {
        return [
            'gemini-2.5-pro',
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-1.5-pro',
        ];
    }

    protected function configKey(): ?string
    {
        return 'services.gemini.key';
    }

    public function defaultModel(): string
    {
        return (string) config('services.gemini.model', 'gemini-2.0-flash');
    }

    /**
     * Gemini's auth is the API key as a query param on the endpoint URL,
     * not a header. So configureRequest is a no-op — the base already
     * sets acceptJson.
     */
    protected function configureRequest(PendingRequest $http): PendingRequest
    {
        return $http;
    }

    /**
     * Gemini bakes both the model and the API key into the endpoint URL.
     */
    protected function endpoint(string $model): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/models/'
            .$model.':generateContent?key='.urlencode((string) $this->resolvedKey);
    }

    protected function buildPayload(string $prompt, array $schema, string $model): array
    {
        return [
            'system_instruction' => [
                'parts' => [['text' => $this->systemMessage()]],
            ],
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ],
        ];
    }

    protected function extractContent(array $response): string
    {
        return (string) ($response['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    protected function verifyEndpoint(): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/models?key='
            .urlencode((string) $this->resolvedKey).'&pageSize=1';
    }

    protected function systemMessage(): string
    {
        return $this->jsonOnlySystemMessage();
    }
}
