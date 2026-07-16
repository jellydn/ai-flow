<?php

namespace Tests\Unit;

use App\Services\OpenRouterProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenRouterProviderTest extends TestCase
{
    public function test_id_returns_openrouter(): void
    {
        $this->assertSame('openrouter', (new OpenRouterProvider)->id());
    }

    public function test_models_returns_non_empty_list(): void
    {
        $models = (new OpenRouterProvider)->models();
        $this->assertNotEmpty($models);
        $this->assertContains('openai/gpt-4o-mini', $models);
        $this->assertContains('anthropic/claude-sonnet-4', $models);
    }

    public function test_verify_credential_succeeds_with_valid_key(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/key' => Http::response(['data' => ['usage' => 100]], 200),
        ]);

        $result = (new OpenRouterProvider('sk-or-valid'))->verifyCredential('sk-or-valid');

        $this->assertTrue($result['valid']);
        $this->assertSame('Credential verified successfully.', $result['message']);
    }

    public function test_verify_credential_fails_with_invalid_key(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/key' => Http::response(['error' => 'invalid'], 401),
        ]);

        $result = (new OpenRouterProvider('sk-or-invalid'))->verifyCredential('sk-or-invalid');

        $this->assertFalse($result['valid']);
        $this->assertSame('Invalid API key.', $result['message']);
    }

    public function test_verify_credential_fails_with_no_key(): void
    {
        config()->set('services.openai.openrouter_key', null);

        $result = (new OpenRouterProvider)->verifyCredential('');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not configured', $result['message']);
    }

    public function test_generate_returns_parsed_json_on_success(): void
    {
        $aiJson = '{"summary":"Report ready","risk":"low","findings":[],"verification_steps":[]}';

        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => $aiJson]]],
            ]),
        ]);

        config()->set('services.openai.openrouter_key', 'sk-or-server');
        config()->set('services.openai.openrouter_model', 'openai/gpt-4o-mini');
        config()->set('services.openai.timeout', 30);

        $result = (new OpenRouterProvider('sk-or-test'))->generate('Analyze.', ['type' => 'object']);

        $this->assertSame('Report ready', $result['summary']);
        $this->assertSame('low', $result['risk']);

        // Verify OpenRouter-specific headers are sent.
        Http::assertSent(fn ($request) => $request->hasHeader('HTTP-Referer') && $request->hasHeader('X-Title'));
    }

    public function test_generate_throws_on_invalid_key(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::response(['error' => 'bad key'], 401),
        ]);

        config()->set('services.openai.openrouter_model', 'openai/gpt-4o-mini');
        config()->set('services.openai.timeout', 30);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid API key.');

        (new OpenRouterProvider('sk-or-bad'))->generate('Analyze.', ['type' => 'object']);
    }

    public function test_generate_throws_on_invalid_json_response(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'not json at all']]],
            ]),
        ]);

        config()->set('services.openai.openrouter_model', 'openai/gpt-4o-mini');
        config()->set('services.openai.timeout', 30);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid JSON');

        (new OpenRouterProvider('sk-or-test'))->generate('Analyze.', ['type' => 'object']);
    }

    public function test_generate_throws_on_http_500_error(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::response(['error' => 'server error'], 500),
        ]);

        config()->set('services.openai.openrouter_model', 'openai/gpt-4o-mini');
        config()->set('services.openai.timeout', 30);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI provider request failed');

        (new OpenRouterProvider('sk-or-test'))->generate('Analyze.', ['type' => 'object']);
    }

    public function test_generate_throws_when_no_key_configured(): void
    {
        config()->set('services.openai.openrouter_key', null);
        config()->set('services.openai.key', null);
        config()->set('services.openai.openrouter_model', 'openai/gpt-4o-mini');
        config()->set('services.openai.timeout', 30);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not configured');

        (new OpenRouterProvider)->generate('Analyze.', ['type' => 'object']);
    }

    public function test_base_url_is_resolved_from_config(): void
    {
        config()->set('services.openai.openrouter_base_url', 'https://custom.openrouter.io/v1');

        Http::fake([
            'custom.openrouter.io/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"summary":"ok","risk":"low","findings":[],"verification_steps":[]}']]],
            ]),
        ]);

        config()->set('services.openai.openrouter_model', 'openai/gpt-4o-mini');
        config()->set('services.openai.timeout', 30);

        $result = (new OpenRouterProvider('sk-or-test'))->generate('Analyze.', ['type' => 'object']);

        $this->assertSame('ok', $result['summary']);
    }

    public function test_referer_header_is_resolved_from_config(): void
    {
        config()->set('services.openai.referer', 'https://myapp.example.com');

        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"summary":"ok","risk":"low","findings":[],"verification_steps":[]}']]],
            ]),
        ]);

        config()->set('services.openai.openrouter_model', 'openai/gpt-4o-mini');
        config()->set('services.openai.timeout', 30);

        (new OpenRouterProvider('sk-or-test'))->generate('Analyze.', ['type' => 'object']);

        Http::assertSent(fn ($request) => $request->hasHeader('HTTP-Referer', 'https://myapp.example.com'));
    }

    public function test_constructor_accepts_explicit_base_url_and_referer(): void
    {
        Http::fake([
            'custom.example.io/v2/key' => Http::response(['data' => ['usage' => 100]], 200),
        ]);

        $provider = new OpenRouterProvider('sk-or-test', 'https://custom.example.io/v2', 'https://myapp.example.com');
        $result = $provider->verifyCredential('sk-or-test');

        $this->assertTrue($result['valid']);
        Http::assertSent(fn ($request) => $request->hasHeader('HTTP-Referer', 'https://myapp.example.com'));
    }
}
