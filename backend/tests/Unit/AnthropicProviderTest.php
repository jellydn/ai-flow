<?php

namespace Tests\Unit;

use App\Services\AnthropicProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AnthropicProviderTest extends TestCase
{
    public function test_id_returns_anthropic(): void
    {
        $this->assertSame('anthropic', (new AnthropicProvider)->id());
    }

    public function test_models_returns_non_empty_list(): void
    {
        $models = (new AnthropicProvider)->models();
        $this->assertNotEmpty($models);
        $this->assertContains('claude-sonnet-4-20250514', $models);
    }

    public function test_default_model_from_config(): void
    {
        config()->set('services.anthropic.model', 'claude-3-5-haiku-20241022');
        $this->assertSame('claude-3-5-haiku-20241022', (new AnthropicProvider)->defaultModel());
    }

    public function test_generate_returns_parsed_json_on_success(): void
    {
        $aiJson = '{"summary":"Report ready","risk":"low","findings":[],"verification_steps":[]}';

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['text' => $aiJson]],
            ]),
        ]);

        config()->set('services.anthropic.model', 'claude-sonnet-4-20250514');
        config()->set('services.ai.timeout', 30);

        $result = (new AnthropicProvider('sk-ant-test'))->generate('Analyze.', ['type' => 'object']);

        $this->assertSame('Report ready', $result['summary']);
    }

    public function test_generate_sends_x_api_key_header(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['text' => '{"summary":"ok"}']],
            ]),
        ]);

        config()->set('services.anthropic.model', 'claude-sonnet-4-20250514');
        config()->set('services.ai.timeout', 30);

        (new AnthropicProvider('sk-ant-test'))->generate('Analyze.', ['type' => 'object']);

        Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'sk-ant-test')
            && $request->hasHeader('anthropic-version', '2023-06-01'));
    }

    public function test_generate_throws_on_invalid_key(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response(['error' => 'bad key'], 401),
        ]);

        config()->set('services.anthropic.model', 'claude-sonnet-4-20250514');
        config()->set('services.ai.timeout', 30);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid API key.');

        (new AnthropicProvider('sk-ant-bad'))->generate('Analyze.', ['type' => 'object']);
    }

    public function test_generate_throws_on_invalid_json_response(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['text' => 'not json at all']],
            ]),
        ]);

        config()->set('services.anthropic.model', 'claude-sonnet-4-20250514');
        config()->set('services.ai.timeout', 30);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid JSON');

        (new AnthropicProvider('sk-ant-test'))->generate('Analyze.', ['type' => 'object']);
    }

    public function test_generate_throws_on_connection_failure(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('cURL error 28: Connection timed out');
        });

        config()->set('services.anthropic.model', 'claude-sonnet-4-20250514');
        config()->set('services.ai.timeout', 30);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to reach the AI provider. Check your network.');

        (new AnthropicProvider('sk-ant-test'))->generate('Analyze.', ['type' => 'object']);
    }

    public function test_falls_back_to_server_config_key(): void
    {
        config()->set('services.anthropic.key', 'server-ant-key');
        config()->set('services.anthropic.model', 'claude-sonnet-4-20250514');
        config()->set('services.ai.timeout', 30);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['text' => '{"summary":"ok"}']],
            ]),
        ]);

        // No injected key — should fall back to services.anthropic.key
        (new AnthropicProvider)->generate('Analyze.', ['type' => 'object']);

        Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'server-ant-key'));
    }

    public function test_verify_credential_succeeds_with_valid_key(): void
    {
        Http::fake([
            'api.anthropic.com/v1/models*' => Http::response(['data' => []], 200),
        ]);

        $result = (new AnthropicProvider('sk-ant-valid'))->verifyCredential('sk-ant-valid');

        $this->assertTrue($result['valid']);
        $this->assertSame('Credential verified successfully.', $result['message']);
    }

    public function test_verify_credential_fails_with_invalid_key(): void
    {
        Http::fake([
            'api.anthropic.com/v1/models*' => Http::response(['error' => 'invalid'], 401),
        ]);

        $result = (new AnthropicProvider('sk-ant-invalid'))->verifyCredential('sk-ant-invalid');

        $this->assertFalse($result['valid']);
        $this->assertSame('Invalid API key.', $result['message']);
    }

    public function test_verify_credential_fails_with_no_key(): void
    {
        $result = (new AnthropicProvider)->verifyCredential('');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not configured', $result['message']);
    }
}
