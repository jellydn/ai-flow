<?php

namespace Tests\Unit;

use App\Models\ProviderCredential;
use App\Models\User;
use App\Services\LaunchParameters;
use App\Support\AiProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaunchParametersTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_uses_credential_provider_when_credential_selected(): void
    {
        $user = User::factory()->create();
        $credential = ProviderCredential::factory()->forUser($user)->forProvider('anthropic')->create();

        $params = LaunchParameters::resolve(
            providerId: 'openai',
            oneTimeApiKey: null,
            providerCredentialId: $credential->id,
            requestedModel: null,
            registry: app(AiProviderRegistry::class),
        );

        $this->assertSame('anthropic', $params->effectiveProvider);
        $this->assertSame('anthropic', $params->rawProviderId);
    }

    public function test_resolve_falls_back_to_default_provider_when_neither_credential_nor_provider_given(): void
    {
        $params = LaunchParameters::resolve(
            providerId: null,
            oneTimeApiKey: null,
            providerCredentialId: null,
            requestedModel: null,
            registry: app(AiProviderRegistry::class),
        );

        $this->assertSame(AiProviderRegistry::DEFAULT_PROVIDER, $params->effectiveProvider);
        $this->assertNull($params->rawProviderId);
    }

    public function test_resolve_uses_requested_provider_when_no_credential(): void
    {
        $params = LaunchParameters::resolve(
            providerId: 'gemini',
            oneTimeApiKey: null,
            providerCredentialId: null,
            requestedModel: null,
            registry: app(AiProviderRegistry::class),
        );

        $this->assertSame('gemini', $params->effectiveProvider);
        $this->assertSame('gemini', $params->rawProviderId);
    }

    public function test_resolve_resolves_model_from_registry_default(): void
    {
        $registry = app(AiProviderRegistry::class);
        $params = LaunchParameters::resolve(
            providerId: 'openai',
            oneTimeApiKey: null,
            providerCredentialId: null,
            requestedModel: null,
            registry: $registry,
        );

        // Assert against the registry's own default (not hardcoded) so the
        // test is robust to OPENAI_MODEL / AI_MODEL env overrides in test env.
        $this->assertSame($registry->defaultModel('openai'), $params->resolvedModel);
    }

    public function test_resolve_resolves_requested_model_when_allowed(): void
    {
        $params = LaunchParameters::resolve(
            providerId: 'openai',
            oneTimeApiKey: null,
            providerCredentialId: null,
            requestedModel: 'gpt-4o',
            registry: app(AiProviderRegistry::class),
        );

        $this->assertSame('gpt-4o', $params->resolvedModel);
    }

    public function test_resolve_falls_back_to_default_model_when_requested_not_allowed_for_guest(): void
    {
        $registry = app(AiProviderRegistry::class);
        $params = LaunchParameters::resolve(
            providerId: 'openrouter',
            oneTimeApiKey: null,
            providerCredentialId: null,
            requestedModel: 'openai/gpt-4-turbo', // NOT in OpenRouter's models list
            registry: $registry,
        );

        // Not in the allowed list and allowCustom=false → falls back to provider default.
        $this->assertSame($registry->defaultModel('openrouter'), $params->resolvedModel);
    }

    public function test_has_credential_key_conflict_when_both_credential_and_onetime_key_provided(): void
    {
        $user = User::factory()->create();
        $credential = ProviderCredential::factory()->forUser($user)->create();

        $params = LaunchParameters::resolve(
            providerId: 'openai',
            oneTimeApiKey: 'sk-onetime',
            providerCredentialId: $credential->id,
            requestedModel: null,
            registry: app(AiProviderRegistry::class),
        );

        $this->assertTrue($params->hasCredentialKeyConflict());
    }

    public function test_has_usable_key_with_one_time_key(): void
    {
        $params = LaunchParameters::resolve(
            providerId: 'openai',
            oneTimeApiKey: 'sk-onetime',
            providerCredentialId: null,
            requestedModel: null,
            registry: app(AiProviderRegistry::class),
        );

        $this->assertTrue($params->hasUsableKey());
    }

    public function test_has_usable_key_with_server_config_fallback(): void
    {
        config()->set('services.openai.key', 'sk-server-config');

        $params = LaunchParameters::resolve(
            providerId: 'openai',
            oneTimeApiKey: null,
            providerCredentialId: null,
            requestedModel: null,
            registry: app(AiProviderRegistry::class),
        );

        $this->assertTrue($params->hasUsableKey());
    }

    public function test_is_guest_violation_when_unauthenticated_and_not_guest_provider(): void
    {
        $params = LaunchParameters::resolve(
            providerId: 'openai',
            oneTimeApiKey: 'sk-onetime',
            providerCredentialId: null,
            requestedModel: null,
            registry: app(AiProviderRegistry::class),
        );

        $this->assertTrue($params->isGuestViolationFor(false));
        $this->assertFalse($params->isGuestViolationFor(true));
    }

    public function test_no_guest_violation_when_using_guest_provider(): void
    {
        $params = LaunchParameters::resolve(
            providerId: 'openrouter',
            oneTimeApiKey: 'sk-onetime',
            providerCredentialId: null,
            requestedModel: null,
            registry: app(AiProviderRegistry::class),
        );

        $this->assertFalse($params->isGuestViolationFor(false));
    }

    public function test_is_model_allowed_passes_for_guest_model_when_unauthenticated(): void
    {
        $params = LaunchParameters::resolve(
            providerId: 'openrouter',
            oneTimeApiKey: null,
            providerCredentialId: null,
            requestedModel: AiProviderRegistry::GUEST_MODEL,
            registry: app(AiProviderRegistry::class),
        );

        $result = $params->isModelAllowed(app(AiProviderRegistry::class), false);
        $this->assertTrue($result['valid']);
    }

    public function test_is_model_allowed_blocks_non_guest_model_for_unauthenticated(): void
    {
        $params = LaunchParameters::resolve(
            providerId: 'openrouter',
            oneTimeApiKey: null,
            providerCredentialId: null,
            requestedModel: 'openai/gpt-4o',
            registry: app(AiProviderRegistry::class),
        );

        $result = $params->isModelAllowed(app(AiProviderRegistry::class), false);
        $this->assertFalse($result['valid']);
        $this->assertSame('Sign in to choose a different AI model.', $result['error']);
    }

    public function test_is_model_allowed_allows_custom_format_for_authenticated_users(): void
    {
        $params = LaunchParameters::resolve(
            providerId: 'openai',
            oneTimeApiKey: null,
            providerCredentialId: null,
            requestedModel: 'custom-org/my-fine-tune',
            registry: app(AiProviderRegistry::class),
            allowCustom: true,
        );

        $result = $params->isModelAllowed(app(AiProviderRegistry::class), true);
        $this->assertTrue($result['valid']);
    }

    public function test_is_model_allowed_rejects_invalid_format(): void
    {
        $params = LaunchParameters::resolve(
            providerId: 'openai',
            oneTimeApiKey: null,
            providerCredentialId: null,
            requestedModel: 'has spaces and !symbols',
            registry: app(AiProviderRegistry::class),
            allowCustom: true,
        );

        $result = $params->isModelAllowed(app(AiProviderRegistry::class), true);
        $this->assertFalse($result['valid']);
    }
}
