<?php

namespace App\Support;

use App\Contracts\AIProviderInterface;
use App\Models\ProviderCredential;
use App\Security\CredentialCipher;
use App\Services\AnthropicProvider;
use App\Services\GeminiProvider;
use App\Services\OpenAIProvider;
use App\Services\OpenRouterProvider;
use InvalidArgumentException;

/**
 * Central registry for AI provider adapters.
 *
 * Maps provider IDs to concrete adapter classes and creates instances
 * with an optional API key. This replaces the ad-hoc app()->make()
 * with a single source of truth for provider lookup.
 */
class AiProviderRegistry
{
    public function __construct(
        private CredentialCipher $cipher,
    ) {}

    public const DEFAULT_PROVIDER = 'openai';

    public const GUEST_PROVIDER = 'openrouter';

    public const GUEST_MODEL = 'openrouter/free';

    /** @var array<string, class-string<AIProviderInterface>> */
    private const PROVIDERS = [
        'openai' => OpenAIProvider::class,
        'openrouter' => OpenRouterProvider::class,
        'anthropic' => AnthropicProvider::class,
        'gemini' => GeminiProvider::class,
    ];

    /** @var array<string, string> */
    private const DISPLAY_NAMES = [
        'openai' => 'OpenAI',
        'openrouter' => 'OpenRouter',
        'anthropic' => 'Anthropic',
        'gemini' => 'Google Gemini',
    ];

    /**
     * Get a provider adapter instance by ID.
     *
     * @param  string  $providerId  e.g. "openai", "anthropic"
     * @param  string|null  $apiKey  Optional API key to inject into the adapter
     */
    public function get(string $providerId, ?string $apiKey = null): AIProviderInterface
    {
        if (! isset(self::PROVIDERS[$providerId])) {
            throw new InvalidArgumentException("Unsupported AI provider: {$providerId}");
        }

        return new (self::PROVIDERS[$providerId])($apiKey);
    }

    /**
     * Get all registered provider IDs.
     *
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys(self::PROVIDERS);
    }

    /** @var list<array{id: string, name: string, models: list<string>}>|null */
    private static ?array $cachedList = null;

    /**
     * Get provider metadata (id, display name, models) for all providers.
     *
     * @return list<array{id: string, name: string, models: list<string>}>
     */
    public function list(): array
    {
        return self::$cachedList ??= array_map(function (string $id): array {
            $provider = new (self::PROVIDERS[$id])(null);

            return [
                'id' => $id,
                'name' => self::DISPLAY_NAMES[$id] ?? $id,
                'models' => $provider->models(),
            ];
        }, array_keys(self::PROVIDERS));
    }

    /**
     * Check if a provider ID is registered.
     */
    public function has(string $providerId): bool
    {
        return isset(self::PROVIDERS[$providerId]);
    }

    public function displayName(?string $providerId): ?string
    {
        if ($providerId === null || $providerId === '') {
            return null;
        }

        return self::DISPLAY_NAMES[$providerId] ?? $providerId;
    }

    /**
     * @return list<string>
     */
    public function modelsFor(string $providerId): array
    {
        if (! isset(self::PROVIDERS[$providerId])) {
            return [];
        }

        return (new (self::PROVIDERS[$providerId])(null))->models();
    }

    public function defaultModel(string $providerId): string
    {
        if (! isset(self::PROVIDERS[$providerId])) {
            return $this->modelsFor($providerId)[0] ?? 'gpt-4o-mini';
        }

        // Delegate to the adapter — single source of truth (ADR-0022).
        return (new (self::PROVIDERS[$providerId])(null))->defaultModel();
    }

    /**
     * Resolve the API key for a launch.
     *
     * Priority: one-time key > saved credential > server config.
     */
    public function resolveApiKey(?string $providerId, ?string $oneTimeApiKey, ?string $providerCredentialId): ?string
    {
        if ($oneTimeApiKey !== null && $oneTimeApiKey !== '') {
            return $oneTimeApiKey;
        }

        if ($providerCredentialId !== null) {
            $credential = ProviderCredential::find($providerCredentialId);

            if ($credential) {
                return $credential->decryptApiKey($this->cipher);
            }
        }

        $providerId = $providerId ?? 'openai';

        return match ($providerId) {
            'openrouter' => config('services.openai.openrouter_key'),
            'anthropic' => config('services.anthropic.key'),
            'gemini' => config('services.gemini.key'),
            default => config('services.openai.key'),
        };
    }

    /**
     * Whether a usable API key is available.
     */
    public function hasUsableKey(?string $providerId, ?string $oneTimeApiKey, ?string $providerCredentialId): bool
    {
        $key = $this->resolveApiKey($providerId, $oneTimeApiKey, $providerCredentialId);

        return is_string($key) && $key !== '';
    }

    public function resolveModel(
        string $providerId,
        ?string $requested,
        ?string $credentialDefault = null,
        bool $allowCustom = false,
    ): string {
        $allowed = $this->modelsFor($providerId);

        foreach (array_filter([$requested, $credentialDefault]) as $candidate) {
            if ($candidate !== '' && ($allowCustom || in_array($candidate, $allowed, true))) {
                return $candidate;
            }
        }

        return $this->defaultModel($providerId);
    }
}
