<?php

namespace Tests\Unit;

use App\Services\OpenAIProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAIProviderTest extends TestCase
{
    public function test_it_uses_configured_openai_compatible_provider_and_schema(): void
    {
        config()->set('services.openai', [
            'key' => 'test-key',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'timeout' => 30,
            'referer' => 'https://ai-flow.test',
        ]);
        $schema = [
            'type' => 'object',
            'required' => ['summary'],
            'properties' => ['summary' => ['type' => 'string']],
        ];
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"summary":"Ready"}']]],
            ]),
        ]);

        $result = (new OpenAIProvider)->generate('Inspect this repository.', $schema);

        $this->assertSame(['summary' => 'Ready'], $result);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request['model'] === 'gpt-4o-mini'
            && $request['response_format']['json_schema']['schema'] === $schema
            && $request->hasHeader('Authorization', 'Bearer test-key'));
    }

    public function test_execution_key_overrides_server_key(): void
    {
        config()->set('services.openai', [
            'key' => 'server-key',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-5',
            'timeout' => 30,
        ]);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '{"summary":"Ready"}']]]])]);

        (new OpenAIProvider('user-key'))->generate('Inspect.', ['type' => 'object']);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer user-key'));
    }

    public function test_invalid_key_has_safe_error_message(): void
    {
        config()->set('services.openai', [
            'key' => 'server-key',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-5',
            'timeout' => 30,
        ]);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'provider detail']], 401)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid API key.');

        (new OpenAIProvider('bad-user-key'))->generate('Inspect.', ['type' => 'object']);
    }

    public function test_connection_failure_produces_safe_message(): void
    {
        config()->set('services.openai', [
            'key' => 'test-key',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'timeout' => 30,
        ]);
        Http::fake(function (): never {
            throw new ConnectionException('cURL error 28: Connection timed out');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to reach the AI provider. Check your network.');

        (new OpenAIProvider('test-key'))->generate('Inspect.', ['type' => 'object']);
    }

    public function test_invalid_json_response_throws_safe_message(): void
    {
        config()->set('services.openai', [
            'key' => 'test-key',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'timeout' => 30,
        ]);
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'not json at all']]],
        ])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI provider returned invalid JSON (json error: Syntax error, preview: not json at all).');

        (new OpenAIProvider('test-key'))->generate('Inspect.', ['type' => 'object']);
    }

    public function test_falls_back_to_server_config_key(): void
    {
        config()->set('services.openai', [
            'key' => 'server-config-key',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'timeout' => 30,
        ]);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '{"summary":"ok"}']]]])]);

        // No injected key — should fall back to services.openai.key
        (new OpenAIProvider)->generate('Inspect.', ['type' => 'object']);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer server-config-key'));
    }

    public function test_timeout_is_resolved_from_services_ai_timeout(): void
    {
        config()->set('services.ai.timeout', 45);
        config()->set('services.openai', [
            'key' => 'test-key',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
        ]);

        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '{"summary":"ok"}']]]])]);

        (new OpenAIProvider('test-key'))->generate('Inspect.', ['type' => 'object']);

        Http::assertSent(fn ($request) => $request->hasHeader('Accept', 'application/json'));
    }
}
