<?php

namespace App\Services;

use App\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIProvider implements AIProviderInterface
{
    public function __construct(private ?string $apiKey = null) {}

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

    public function verifyCredential(string $apiKey): array
    {
        $key = $apiKey ?: $this->apiKey ?: config('services.openai.key');
        if (! $key) {
            return ['valid' => false, 'message' => 'The AI provider API key is not configured.'];
        }

        $baseUrl = rtrim(config('services.openai.base_url'), '/');

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout(10)
                ->get($baseUrl.'/models', ['limit' => 1]);

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
        $key = $this->apiKey ?: config('services.openai.key');
        if (! $key) {
            throw new RuntimeException('The AI provider API key is not configured.');
        }

        $baseUrl = rtrim(config('services.openai.base_url'), '/');
        $payload = [
            'model' => $model ?: config('services.openai.model', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => 'Return accurate JSON matching the supplied schema.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'launcher_result', 'strict' => true, 'schema' => $schema],
            ],
        ];
        if (str_contains($baseUrl, 'openrouter.ai')) {
            $payload['provider'] = ['require_parameters' => true];
        }

        $timeout = (int) config('services.openai.timeout');
        if ($timeout <= 0) {
            throw new RuntimeException('The AI provider timeout is not configured.');
        }

        $response = Http::withToken($key)
            ->acceptJson()
            ->withHeaders(array_filter([
                'HTTP-Referer' => config('services.openai.referer'),
                'X-OpenRouter-Title' => config('app.name'),
            ]))
            ->timeout($timeout)
            ->retry(2, 500, throw: false)
            ->post($baseUrl.'/chat/completions', $payload);

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
    }
}
