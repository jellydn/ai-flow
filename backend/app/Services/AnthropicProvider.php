<?php

namespace App\Services;

use App\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicProvider implements AIProviderInterface
{
    public function __construct(private ?string $apiKey = null) {}

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

    public function verifyCredential(string $apiKey): array
    {
        $key = $apiKey ?: $this->apiKey;
        if (! $key) {
            return ['valid' => false, 'message' => 'The AI provider API key is not configured.'];
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
            ])
                ->acceptJson()
                ->timeout(10)
                ->get('https://api.anthropic.com/v1/models', ['limit' => 1]);

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

    public function generate(string $prompt, array $schema): array
    {
        $key = $this->apiKey;
        if (! $key) {
            throw new RuntimeException('The AI provider API key is not configured.');
        }

        $timeout = (int) config('services.openai.timeout');
        if ($timeout <= 0) {
            throw new RuntimeException('The AI provider timeout is not configured.');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
            ])
                ->acceptJson()
                ->timeout($timeout)
                ->retry(2, 500, throw: false)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => config('services.anthropic.model', 'claude-sonnet-4-20250514'),
                    'max_tokens' => 4096,
                    'system' => 'Return accurate JSON matching the supplied schema. Output only the JSON, no other text.',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (in_array($response->status(), [401, 403], true)) {
                throw new RuntimeException('Invalid API key.');
            }
            if (! $response->successful()) {
                throw new RuntimeException('AI provider request failed (HTTP '.$response->status().').');
            }

            $content = $response->json('content.0.text', '');
            $json = json_decode($content, true);
            if (! is_array($json)) {
                throw new RuntimeException('AI provider returned invalid JSON.');
            }

            return $json;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new RuntimeException('Unable to reach the AI provider. Check your network.');
        }
    }
}
