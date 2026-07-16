<?php

namespace App\Services;

use App\Contracts\AIProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiProvider implements AIProviderInterface
{
    public function __construct(private ?string $apiKey = null) {}

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

    public function verifyCredential(string $apiKey): array
    {
        $key = $apiKey ?: $this->apiKey;
        if (! $key) {
            return ['valid' => false, 'message' => 'The AI provider API key is not configured.'];
        }

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get('https://generativelanguage.googleapis.com/v1beta/models', [
                    'key' => $key,
                    'pageSize' => 1,
                ]);

            if ($response->status() === 401 || $response->status() === 403) {
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
        $key = $this->apiKey;
        if (! $key) {
            throw new RuntimeException('The AI provider API key is not configured.');
        }

        $timeout = (int) config('services.openai.timeout');
        if ($timeout <= 0) {
            throw new RuntimeException('The AI provider timeout is not configured.');
        }

        $model = $model ?: config('services.gemini.model', 'gemini-2.0-flash');

        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->retry(2, 500, throw: false)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=".urlencode($key), [
                    'system_instruction' => [
                        'parts' => [['text' => 'Return accurate JSON matching the supplied schema. Output only the JSON, no other text.']],
                    ],
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (in_array($response->status(), [401, 403], true)) {
                throw new RuntimeException('Invalid API key.');
            }
            if (! $response->successful()) {
                throw new RuntimeException('AI provider request failed (HTTP '.$response->status().').');
            }

            $text = $response->json('candidates.0.content.parts.0.text', '');
            $json = json_decode($text, true);
            if (! is_array($json)) {
                throw new RuntimeException('AI provider returned invalid JSON.');
            }

            return $json;
        } catch (ConnectionException $e) {
            throw new RuntimeException('Unable to reach the AI provider. Check your network.');
        }
    }
}
