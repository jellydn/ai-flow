<?php

namespace App\Http\Requests;

use App\Services\LaunchAiKeyResolver;
use App\Services\LaunchParameters;
use App\Support\AiProviderRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRunRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $input = [
            'launcher' => $this->input('launcher') ?: $this->input('flow_id'),
            'source_url' => $this->input('source_url') ?: $this->input('input.url'),
        ];

        if (! $this->user()) {
            $input['provider'] = [
                'id' => AiProviderRegistry::GUEST_PROVIDER,
                'model' => AiProviderRegistry::GUEST_MODEL,
            ];
            $input['model'] = AiProviderRegistry::GUEST_MODEL;
        }

        $this->merge($input);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $registry = app(AiProviderRegistry::class);

        return [
            'launcher' => ['required', 'string', 'exists:launchers,slug'],
            'source_url' => ['required', 'url', 'max:2048', 'regex:/^https:\/\/(?:www\.)?github\.com\//i'],
            'provider' => ['sometimes', 'array'],
            'provider.id' => ['nullable', 'string', Rule::in($registry->ids())],
            'provider.api_key' => ['nullable', 'string', 'max:512'],
            'provider.model' => ['nullable', 'string', 'max:128'],
            'model' => ['nullable', 'string', 'max:128'],
            'provider_credential_id' => [
                'nullable',
                'uuid',
                Rule::exists('provider_credentials', 'id')->where(fn ($q) => $q->where('user_id', $this->user()?->id)),
            ],
        ];
    }

    /**
     * Configure the validator to add a custom message for provider_credential_id.
     */
    public function messages(): array
    {
        return [
            'provider_credential_id.exists' => 'The selected credential does not belong to your account.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $resolver = app(LaunchAiKeyResolver::class);
            $registry = app(AiProviderRegistry::class);

            $params = LaunchParameters::resolve(
                providerId: $this->input('provider.id'),
                oneTimeApiKey: $this->input('provider.api_key'),
                providerCredentialId: $this->input('provider_credential_id'),
                requestedModel: $this->input('provider.model') ?: $this->input('model'),
                registry: $registry,
                keyResolver: $resolver,
            );

            if ($params->hasCredentialKeyConflict()) {
                $validator->errors()->add(
                    'provider.api_key',
                    'Choose either a saved credential or a one-time API key, not both.',
                );

                return;
            }

            if (! $params->hasUsableKey()) {
                $validator->errors()->add(
                    'provider.api_key',
                    'No AI provider API key is available. Paste a key, select a saved key (sign in), or configure the provider key on the server.',
                );

                return;
            }

            if ($params->isGuestViolationFor($this->user() !== null)) {
                $validator->errors()->add(
                    'provider.id',
                    'Sign in to choose a different AI provider.',
                );
            }

            $modelResult = $params->isModelAllowed($registry, $this->user() !== null);
            if (! $modelResult['valid']) {
                $validator->errors()->add('provider.model', $modelResult['error']);
            }
        });
    }
}
