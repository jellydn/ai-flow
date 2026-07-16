<?php

namespace Tests\Unit;

use App\Services\AnthropicProvider;
use App\Services\GeminiProvider;
use App\Services\OpenAIProvider;
use App\Services\OpenRouterProvider;
use App\Support\AiProviderRegistry;
use InvalidArgumentException;
use Tests\TestCase;

class AiProviderRegistryTest extends TestCase
{
    private AiProviderRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = $this->app->make(AiProviderRegistry::class);
    }

    public function test_get_returns_openai_provider(): void
    {
        $provider = $this->registry->get('openai', 'sk-test');
        $this->assertInstanceOf(OpenAIProvider::class, $provider);
        $this->assertSame('openai', $provider->id());
    }

    public function test_get_returns_openrouter_provider(): void
    {
        $provider = $this->registry->get('openrouter', 'sk-or-test');
        $this->assertInstanceOf(OpenRouterProvider::class, $provider);
        $this->assertSame('openrouter', $provider->id());
    }

    public function test_get_returns_anthropic_provider(): void
    {
        $provider = $this->registry->get('anthropic', 'sk-ant-test');
        $this->assertInstanceOf(AnthropicProvider::class, $provider);
        $this->assertSame('anthropic', $provider->id());
    }

    public function test_get_returns_gemini_provider(): void
    {
        $provider = $this->registry->get('gemini', 'AIza-test');
        $this->assertInstanceOf(GeminiProvider::class, $provider);
        $this->assertSame('gemini', $provider->id());
    }

    public function test_get_throws_for_unsupported_provider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported AI provider: groq');
        $this->registry->get('groq');
    }

    public function test_has_returns_true_for_registered_providers(): void
    {
        $this->assertTrue($this->registry->has('openai'));
        $this->assertTrue($this->registry->has('openrouter'));
        $this->assertTrue($this->registry->has('anthropic'));
        $this->assertTrue($this->registry->has('gemini'));
    }

    public function test_has_returns_false_for_unregistered_provider(): void
    {
        $this->assertFalse($this->registry->has('groq'));
        $this->assertFalse($this->registry->has('fireworks'));
        $this->assertFalse($this->registry->has(''));
    }

    public function test_ids_returns_all_registered_provider_ids(): void
    {
        $ids = $this->registry->ids();
        $this->assertContains('openai', $ids);
        $this->assertContains('openrouter', $ids);
        $this->assertContains('anthropic', $ids);
        $this->assertContains('gemini', $ids);
        $this->assertCount(4, $ids);
    }

    public function test_list_returns_metadata_for_all_providers(): void
    {
        $list = $this->registry->list();
        $this->assertCount(4, $list);

        foreach ($list as $entry) {
            $this->assertArrayHasKey('id', $entry);
            $this->assertArrayHasKey('name', $entry);
            $this->assertArrayHasKey('models', $entry);
            $this->assertIsString($entry['id']);
            $this->assertIsString($entry['name']);
            $this->assertIsArray($entry['models']);
            $this->assertNotEmpty($entry['models']);
        }
    }

    public function test_default_model_delegates_to_adapter(): void
    {
        config()->set('services.openai.model', 'gpt-4o-mini');
        config()->set('services.openai.openrouter_model', 'openai/gpt-4o-mini');
        config()->set('services.anthropic.model', 'claude-sonnet-4-20250514');
        config()->set('services.gemini.model', 'gemini-2.0-flash');

        $this->assertSame('gpt-4o-mini', $this->registry->defaultModel('openai'));
        $this->assertSame('openai/gpt-4o-mini', $this->registry->defaultModel('openrouter'));
        $this->assertSame('claude-sonnet-4-20250514', $this->registry->defaultModel('anthropic'));
        $this->assertSame('gemini-2.0-flash', $this->registry->defaultModel('gemini'));
    }

    public function test_default_model_falls_back_for_unknown_provider(): void
    {
        // Unknown provider returns first model from modelsFor() or 'gpt-4o-mini'
        $this->assertSame('gpt-4o-mini', $this->registry->defaultModel('groq'));
    }

    public function test_resolve_model_prefers_requested_then_credential_default(): void
    {
        config()->set('services.openai.model', 'gpt-4o-mini');

        $this->assertSame('gpt-4o', $this->registry->resolveModel('openai', 'gpt-4o'));
        $this->assertSame('gpt-4o-mini', $this->registry->resolveModel('openai', 'invalid-model'));
        $this->assertSame('gpt-4o', $this->registry->resolveModel('openai', null, 'gpt-4o'));
    }

    public function test_list_includes_display_names(): void
    {
        $list = $this->registry->list();
        $names = array_column($list, 'name', 'id');

        $this->assertSame('OpenAI', $names['openai']);
        $this->assertSame('OpenRouter', $names['openrouter']);
        $this->assertSame('Anthropic', $names['anthropic']);
        $this->assertSame('Google Gemini', $names['gemini']);
    }
}
