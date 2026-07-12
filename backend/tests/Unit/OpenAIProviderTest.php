<?php

namespace Tests\Unit;

use App\Services\OpenAIProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIProviderTest extends TestCase
{
    public function test_it_uses_configured_openai_compatible_provider_and_schema(): void
    {
        config()->set('services.openai', [
            'key' => 'test-key',
            'base_url' => 'https://openrouter.ai/api/v1',
            'model' => 'openrouter/free',
            'timeout' => 30,
            'referer' => 'https://ai-flow.test',
        ]);
        $schema = [
            'type' => 'object',
            'required' => ['summary'],
            'properties' => ['summary' => ['type' => 'string']],
        ];
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => '{"summary":"Ready"}']]],
            ]),
        ]);

        $result = (new OpenAIProvider)->generate('Inspect this repository.', $schema);

        $this->assertSame(['summary' => 'Ready'], $result);
        Http::assertSent(fn ($request) => $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && $request['model'] === 'openrouter/free'
            && $request['provider']['require_parameters'] === true
            && $request['response_format']['json_schema']['schema'] === $schema
            && $request->hasHeader('Authorization', 'Bearer test-key'));
    }
}
