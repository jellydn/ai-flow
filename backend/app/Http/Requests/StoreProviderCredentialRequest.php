<?php

namespace App\Http\Requests;

use App\Rules\PublicHttpUrl;
use App\Support\AiProviderRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProviderCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(app(AiProviderRegistry::class)->ids())],
            'label' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string', 'max:2048'],
            'base_url' => ['nullable', 'url', 'max:2048', new PublicHttpUrl],
            'default_model' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
