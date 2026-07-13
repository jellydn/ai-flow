<?php

namespace App\Http\Requests;

use App\Support\AiProviderRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProviderCredentialRequest extends FormRequest
{
    public function __construct(
        private AiProviderRegistry $registry,
    ) {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in($this->registry->ids())],
            'label' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string', 'max:2048'],
            'base_url' => ['nullable', 'url', 'max:2048'],
            'default_model' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
