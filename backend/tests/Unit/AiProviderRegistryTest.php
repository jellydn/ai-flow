<?php

namespace Tests\Unit;

use App\Services\AnthropicProvider;
use App\Services\GeminiProvider;
use App\Services\OpenAIProvider;
use App\Services\OpenRouterProvider;
use App\Support\AiProviderRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AiProviderRegistryTest extends TestCase
{
    private AiProviderRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new AiProviderRegistry;
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
