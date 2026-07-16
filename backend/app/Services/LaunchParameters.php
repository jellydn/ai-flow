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
        public readonly ?string $rawProviderId,
        private readonly AiProviderRegistry $registry,
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
        bool $allowCustom = false,
    ): self {
        $credential = $providerCredentialId ? ProviderCredential::find($providerCredentialId) : null;

        // If a saved credential is selected, its provider takes precedence.
        $effectiveProvider = $credential ? $credential->provider : $providerId;
        $effectiveProvider = is_string($effectiveProvider) && $effectiveProvider !== ''
            ? $effectiveProvider
            : AiProviderRegistry::DEFAULT_PROVIDER;

        $model = $registry->resolveModel(
            $effectiveProvider,
            $requestedModel,
            $credential?->default_model,
            allowCustom: $allowCustom,
        );

        // The raw provider ID is what gets passed to the job — it preserves
        // the original nullable behavior (credential provider when a credential
        // is selected, raw providerId otherwise, null when both are absent).
        // The job resolves null → 'openai' at execution time.
        $rawProviderId = $credential ? $credential->provider : $providerId;

        return new self(
            providerId: $providerId,
            providerCredentialId: $providerCredentialId,
            oneTimeApiKey: $oneTimeApiKey,
            requestedModel: $requestedModel,
            effectiveProvider: $effectiveProvider,
            resolvedModel: $model,
            rawProviderId: $rawProviderId,
            registry: $registry,
        );
    }

    /**
     * Whether a usable API key is available for this launch.
     */
    public function hasUsableKey(): bool
    {
        return $this->registry->hasUsableKey(
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
     * Returns a structured result so error messages are owned here,
     * not in the form request (single responsibility).
     *
     * @return array{valid: bool, error: ?string}
     */
    public function isModelAllowed(AiProviderRegistry $registry, bool $isAuthenticated = false): array
    {
        if ($this->requestedModel === null || $this->requestedModel === '') {
            return ['valid' => true, 'error' => null];
        }

        // Guest users always pass with the designated guest model.
        if (! $isAuthenticated && $this->requestedModel === AiProviderRegistry::GUEST_MODEL) {
            return ['valid' => true, 'error' => null];
        }

        if (! $isAuthenticated) {
            return ['valid' => false, 'error' => 'Sign in to choose a different AI model.'];
        }

        if (in_array($this->requestedModel, $registry->modelsFor($this->effectiveProvider), true)) {
            return ['valid' => true, 'error' => null];
        }

        // Authenticated users may pass a custom model name with valid format.
        if ((bool) preg_match('~^[A-Za-z0-9][A-Za-z0-9._:/-]*$~', $this->requestedModel)) {
            return ['valid' => true, 'error' => null];
        }

        return ['valid' => false, 'error' => 'The model name may only contain letters, numbers, dots, underscores, colons, slashes, and hyphens.'];
    }

    /**
     * Whether unauthenticated users are trying to use a non-guest provider.
     */
    public function isGuestViolationFor(bool $isAuthenticated): bool
    {
        return ! $isAuthenticated && $this->effectiveProvider !== AiProviderRegistry::GUEST_PROVIDER;
    }
}
