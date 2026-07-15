<?php

namespace App\Http\Requests;

use App\Services\LaunchAiKeyResolver;
use App\Support\AiProviderRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRunRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'launcher' => $this->input('launcher') ?: $this->input('flow_id'),
            'source_url' => $this->input('source_url') ?: $this->input('input.url'),
        ]);
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
            $providerId = $this->input('provider.id');
            $oneTimeKey = $this->input('provider.api_key');
            $credentialId = $this->input('provider_credential_id');

            if (! $resolver->hasUsableKey($providerId, $oneTimeKey, $credentialId)) {
                $validator->errors()->add(
                    'provider.api_key',
                    'No AI provider API key is available. Paste a key, select a saved key (sign in), or configure OPENAI_API_KEY on the server.',
                );
            }
        });
    }
}
