<?php

namespace App\Services;

use App\Models\ProviderCredential;
use App\Security\CredentialCipher;

/**
 * Resolves the API key used for a launch (HTTP store and queue job).
 *
 * Priority: one-time key > saved credential > server config.
 */
class LaunchAiKeyResolver
{
    public function __construct(
        private CredentialCipher $cipher,
    ) {}

    public function resolve(?string $providerId, ?string $oneTimeApiKey, ?string $providerCredentialId): ?string
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
            'openrouter' => config('services.openai.openrouter_key') ?: config('services.openai.key'),
            'anthropic' => config('services.anthropic.key'),
            'gemini' => config('services.gemini.key'),
            default => config('services.openai.key'),
        };
    }

    public function hasUsableKey(?string $providerId, ?string $oneTimeApiKey, ?string $providerCredentialId): bool
    {
        $key = $this->resolve($providerId, $oneTimeApiKey, $providerCredentialId);

        return is_string($key) && $key !== '';
    }
}
