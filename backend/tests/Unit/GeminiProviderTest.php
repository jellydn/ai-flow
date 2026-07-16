<?php

namespace Tests\Unit;

use App\Services\GeminiProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GeminiProviderTest extends TestCase
{
    public function test_id_returns_gemini(): void
    {
        $this->assertSame('gemini', (new GeminiProvider)->id());
    }

    public function test_models_returns_non_empty_list(): void
    {
        $models = (new GeminiProvider)->models();
        $this->assertNotEmpty($models);
        $this->assertContains('gemini-2.0-flash', $models);
    }

    public function test_default_model_from_config(): void
    {
        config()->set('services.gemini.model', 'gemini-2.5-pro');
        $this->assertSame('gemini-2.5-pro', (new GeminiProvider)->defaultModel());
    }

    public function test_generate_returns_parsed_json_on_success(): void
    {
        $aiJson = '{"summary":"Report ready","risk":"low","findings":[],"verification_steps":[]}';

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => $aiJson]]]]],
            ]),
        ]);

        config()->set('services.gemini.model', 'gemini-2.0-flash');
        config()->set('services.ai.timeout', 30);

        $result = (new GeminiProvider('AIza-test'))->generate('Analyze.', ['type' => 'object']);

        $this->assertSame('Report ready', $result['summary']);
    }

    public function test_generate_bakes_key_into_endpoint_url(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{"summary":"ok"}']]]]],
            ]),
        ]);

        config()->set('services.gemini.model', 'gemini-2.0-flash');
        config()->set('services.ai.timeout', 30);

        (new GeminiProvider('AIza-test-key'))->generate('Analyze.', ['type' => 'object']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'key=AIza-test-key'));
    }

    public function test_generate_bakes_model_into_endpoint_url(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{"summary":"ok"}']]]]],
            ]),
        ]);

        config()->set('services.ai.timeout', 30);

        (new GeminiProvider('AIza-test'))->generate('Analyze.', ['type' => 'object'], 'gemini-2.5-pro');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'models/gemini-2.5-pro:generateContent'));
    }

    public function test_generate_throws_on_invalid_key(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'bad key'], 401),
        ]);

        config()->set('services.gemini.model', 'gemini-2.0-flash');
        config()->set('services.ai.timeout', 30);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid API key.');

        (new GeminiProvider('AIza-bad'))->generate('Analyze.', ['type' => 'object']);
    }

    public function test_generate_throws_on_invalid_json_response(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'not json at all']]]]],
            ]),
        ]);

        config()->set('services.gemini.model', 'gemini-2.0-flash');
        config()->set('services.ai.timeout', 30);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid JSON');

        (new GeminiProvider('AIza-test'))->generate('Analyze.', ['type' => 'object']);
    }

    public function test_generate_throws_on_connection_failure(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('cURL error 28: Connection timed out');
        });

        config()->set('services.gemini.model', 'gemini-2.0-flash');
        config()->set('services.ai.timeout', 30);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to reach the AI provider. Check your network.');

        (new GeminiProvider('AIza-test'))->generate('Analyze.', ['type' => 'object']);
    }

    public function test_falls_back_to_server_config_key(): void
    {
        config()->set('services.gemini.key', 'server-gemini-key');
        config()->set('services.gemini.model', 'gemini-2.0-flash');
        config()->set('services.ai.timeout', 30);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{"summary":"ok"}']]]]],
            ]),
        ]);

        // No injected key — should fall back to services.gemini.key
        (new GeminiProvider)->generate('Analyze.', ['type' => 'object']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'key=server-gemini-key'));
    }

    public function test_verify_credential_succeeds_with_valid_key(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['models' => []], 200),
        ]);

        $result = (new GeminiProvider('AIza-valid'))->verifyCredential('AIza-valid');

        $this->assertTrue($result['valid']);
        $this->assertSame('Credential verified successfully.', $result['message']);
    }

    public function test_verify_credential_fails_with_invalid_key(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'invalid'], 401),
        ]);

        $result = (new GeminiProvider('AIza-invalid'))->verifyCredential('AIza-invalid');

        $this->assertFalse($result['valid']);
        $this->assertSame('Invalid API key.', $result['message']);
    }

    public function test_verify_credential_fails_with_no_key(): void
    {
        $result = (new GeminiProvider)->verifyCredential('');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not configured', $result['message']);
    }
}
