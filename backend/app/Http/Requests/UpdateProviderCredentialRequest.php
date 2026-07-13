<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:255'],
            'api_key' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'base_url' => ['nullable', 'url', 'max:2048'],
            'default_model' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
