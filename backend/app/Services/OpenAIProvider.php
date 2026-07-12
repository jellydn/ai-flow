<?php

namespace App\Services;

use App\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIProvider implements AIProviderInterface
{
    public function generate(string $prompt, array $schema): array
    {
        $key = config('services.openai.key');
        if (! $key) {
            throw new RuntimeException('The AI provider API key is not configured.');
        }

        $baseUrl = rtrim(config('services.openai.base_url'), '/');
        $payload = [
            'model' => config('services.openai.model', 'gpt-4o-mini'),
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

        $response = Http::withToken($key)
            ->acceptJson()
            ->withHeaders(array_filter([
                'HTTP-Referer' => config('services.openai.referer'),
                'X-OpenRouter-Title' => config('app.name'),
            ]))
            ->timeout((int) config('services.openai.timeout', 60))
            ->retry(2, 500, throw: false)
            ->post($baseUrl.'/chat/completions', $payload);

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
