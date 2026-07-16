<?php

namespace App\Services;

use App\Models\ProviderCredential;
use App\Support\AiProviderRegistry;

/**
 * Resolved launch parameters: the effective provider, model, and key
 * source for a single run launch.
 *
 * Centralizes the provider/model/key resolution that was previously
 * smeared across StoreRunRequest::withValidator, RunController::store,
 * and ExecuteLauncherJob::handle (three call sites each re-deriving
 * "credential → provider", "credential → default_model", and the
 * openai fallback).
 *
 * The form request validates via hasUsableKey() and isModelAllowed();
 * the controller snapshots provider + model onto the run; the job
 * re-resolves only the live API key (which must stay transient).
 */
class LaunchParameters
{
    public function __construct(
        public readonly ?string $providerId,
        public readonly ?string $providerCredentialId,
        public readonly ?string $oneTimeApiKey,
        public readonly ?string $requestedModel,
        public readonly string $effectiveProvider,
        public readonly string $resolvedModel,
        public readonly ?string $dispatchProvider,
        private readonly LaunchAiKeyResolver $keyResolver,
    ) {}

    /**
     * Build LaunchParameters from raw request inputs.
     */
    public static function resolve(
        ?string $providerId,
        ?string $oneTimeApiKey,
        ?string $providerCredentialId,
        ?string $requestedModel,
        AiProviderRegistry $registry,
        LaunchAiKeyResolver $keyResolver,
        bool $allowCustom = false,
    ): self {
        $credential = $providerCredentialId ? ProviderCredential::find($providerCredentialId) : null;

        // If a saved credential is selected, its provider takes precedence.
        $effectiveProvider = $credential ? $credential->provider : $providerId;
        $effectiveProvider = is_string($effectiveProvider) && $effectiveProvider !== ''
            ? $effectiveProvider
            : 'openai';

        $model = $registry->resolveModel(
            $effectiveProvider,
            $requestedModel,
            $credential?->default_model,
            allowCustom: $allowCustom,
        );

        // The dispatch provider is what gets passed to the job — it preserves
        // the original nullable behavior (credential provider when a credential
        // is selected, raw providerId otherwise, null when both are absent).
        // The job resolves null → 'openai' at execution time.
        $dispatchProvider = $credential ? $credential->provider : $providerId;

        return new self(
            providerId: $providerId,
            providerCredentialId: $providerCredentialId,
            oneTimeApiKey: $oneTimeApiKey,
            requestedModel: $requestedModel,
            effectiveProvider: $effectiveProvider,
            resolvedModel: $model,
            dispatchProvider: $dispatchProvider,
            keyResolver: $keyResolver,
        );
    }

    /**
     * Whether a usable API key is available for this launch.
     */
    public function hasUsableKey(): bool
    {
        return $this->keyResolver->hasUsableKey(
            $this->effectiveProvider,
            $this->oneTimeApiKey,
            $this->providerCredentialId,
        );
    }

    /**
     * Whether both a saved credential AND a one-time key were provided (mutual exclusion violation).
     */
    public function hasCredentialKeyConflict(): bool
    {
        return $this->providerCredentialId !== null && $this->oneTimeApiKey !== null;
    }

    /**
     * Whether the requested model (if any) is allowed for the effective provider.
     *
     * Authenticated users may use custom model names (validated by format);
     * unauthenticated users are restricted to guest models only.
     */
    public function isModelAllowed(AiProviderRegistry $registry, bool $isAuthenticated = false): bool
    {
        if ($this->requestedModel === null || $this->requestedModel === '') {
            return true;
        }

        if (! $isAuthenticated && $this->requestedModel !== AiProviderRegistry::GUEST_MODEL) {
            return false;
        }

        if (in_array($this->requestedModel, $registry->modelsFor($this->effectiveProvider), true)) {
            return true;
        }

        // Authenticated users may pass a custom model name with valid format.
        if ($isAuthenticated) {
            return (bool) preg_match('~^[A-Za-z0-9][A-Za-z0-9._:/-]*$~', $this->requestedModel);
        }

        return false;
    }

    /**
     * Whether unauthenticated users are trying to use a non-guest provider.
     */
    public function isGuestProviderViolation(bool $isAuthenticated): bool
    {
        return ! $isAuthenticated && $this->effectiveProvider !== AiProviderRegistry::GUEST_PROVIDER;
    }
}
