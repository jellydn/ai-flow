<?php

namespace Tests\Unit;

use App\Contracts\AIProviderInterface;
use App\Services\AnthropicProvider;
use App\Services\BaseAIProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests the JSON extraction fallback added to BaseAIProvider (CONCERNS F1).
 *
 * Providers without native json_schema enforcement (Anthropic, Gemini)
 * sometimes wrap JSON in prose or code fences despite the prompt-only
 * instruction. BaseAIProvider::extractJson() tolerates that wrapping.
 *
 * extractJson is protected, so this test defines a tiny concrete subclass
 * that exposes it, and also drives generate() end-to-end with Http::fake
 * to confirm the fallback works in the real lifecycle.
 */
class BaseAIProviderJsonExtractionTest extends TestCase
{
    public function test_extract_json_decodes_plain_json(): void
    {
        $provider = $this->makeExposedProvider();
        $result = $provider->exposedExtractJson('{"summary":"ok","risk":"low"}');
        $this->assertSame(['summary' => 'ok', 'risk' => 'low'], $result);
    }

    public function test_extract_json_strips_code_fence(): void
    {
        $provider = $this->makeExposedProvider();
        $raw = "```json\n{\"summary\":\"ok\"}\n```";
        $this->assertSame(['summary' => 'ok'], $provider->exposedExtractJson($raw));
    }

    public function test_extract_json_strips_bare_code_fence(): void
    {
        $provider = $this->makeExposedProvider();
        $raw = "```\n{\"summary\":\"ok\"}\n```";
        $this->assertSame(['summary' => 'ok'], $provider->exposedExtractJson($raw));
    }

    public function test_extract_json_slices_through_leading_prose(): void
    {
        $provider = $this->makeExposedProvider();
        $raw = "Here is the JSON you requested:\n{\"summary\":\"ok\"}";
        $this->assertSame(['summary' => 'ok'], $provider->exposedExtractJson($raw));
    }

    public function test_extract_json_slices_through_trailing_prose(): void
    {
        $provider = $this->makeExposedProvider();
        $raw = "{\"summary\":\"ok\"}\n\nLet me know if you need anything else.";
        $this->assertSame(['summary' => 'ok'], $provider->exposedExtractJson($raw));
    }

    public function test_extract_json_slices_through_wrapping_prose(): void
    {
        $provider = $this->makeExposedProvider();
        $raw = "Sure! Here's the report:\n{\"summary\":\"ok\",\"risk\":\"high\"}\nHope this helps.";
        $this->assertSame(['summary' => 'ok', 'risk' => 'high'], $provider->exposedExtractJson($raw));
    }

    public function test_extract_json_returns_null_for_non_json(): void
    {
        $provider = $this->makeExposedProvider();
        $this->assertNull($provider->exposedExtractJson('not json at all'));
    }

    public function test_extract_json_returns_null_for_empty_string(): void
    {
        $provider = $this->makeExposedProvider();
        $this->assertNull($provider->exposedExtractJson(''));
    }

    public function test_extract_json_returns_null_when_no_braces(): void
    {
        $provider = $this->makeExposedProvider();
        $this->assertNull($provider->exposedExtractJson('just prose, no json here'));
    }

    public function test_generate_succeeds_when_anthropic_wraps_json_in_prose(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => "Here is the report:\n{\"summary\":\"ok\",\"risk\":\"low\",\"findings\":[],\"verification_steps\":[]}"],
                ],
            ]),
        ]);

        config()->set('services.anthropic.key', 'test-key');

        $provider = new AnthropicProvider('test-key');
        $result = $provider->generate('prompt', $this->sampleSchema());

        $this->assertSame('ok', $result['summary']);
        $this->assertSame('low', $result['risk']);
    }

    public function test_generate_fails_when_response_is_garbage(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'I cannot help with that.']],
            ]),
        ]);

        config()->set('services.anthropic.key', 'test-key');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid JSON');
        $provider = new AnthropicProvider('test-key');
        $provider->generate('prompt', $this->sampleSchema());
    }

    private function sampleSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['summary', 'risk', 'findings', 'verification_steps'],
            'properties' => [
                'summary' => ['type' => 'string'],
                'risk' => ['type' => 'string'],
                'findings' => ['type' => 'array'],
                'verification_steps' => ['type' => 'array'],
            ],
        ];
    }

    private function makeExposedProvider(): object
    {
        return new class('test-key') extends BaseAIProvider implements AIProviderInterface
        {
            public function id(): string
            {
                return 'test';
            }

            public function models(): array
            {
                return ['test-model'];
            }

            public function defaultModel(): string
            {
                return 'test-model';
            }

            protected function configureRequest(PendingRequest $http): PendingRequest
            {
                return $http;
            }

            protected function endpoint(string $model): string
            {
                return 'https://example.com/test';
            }

            protected function buildPayload(string $prompt, array $schema, string $model): array
            {
                return [];
            }

            protected function extractContent(array $response): string
            {
                return '';
            }

            protected function verifyEndpoint(): string
            {
                return 'https://example.com/verify';
            }

            protected function configKey(): ?string
            {
                return null;
            }

            public function exposedExtractJson(string $raw): ?array
            {
                return $this->extractJson($raw);
            }
        };
    }
}
