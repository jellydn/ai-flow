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
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }
        $response = Http::withToken($key)->timeout((int) config('services.openai.timeout', 60))->post('https://api.openai.com/v1/chat/completions', ['model' => config('services.openai.model', 'gpt-4o-mini'), 'messages' => [['role' => 'system', 'content' => 'Return accurate JSON matching the supplied schema.'], ['role' => 'user', 'content' => $prompt]], 'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'launcher_result', 'strict' => true, 'schema' => $schema]]]);
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
