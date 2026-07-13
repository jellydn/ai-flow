<?php

namespace App\Http\Requests;

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
            'provider' => ['required', 'string', Rule::in(config('services.openai.providers'))],
            'label' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string', 'max:2048'],
            'base_url' => ['nullable', 'url', 'max:2048'],
            'default_model' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
