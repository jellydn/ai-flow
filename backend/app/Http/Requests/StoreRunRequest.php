<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        return [
            'launcher' => ['required', 'string', 'exists:launchers,slug'],
            'source_url' => ['required', 'url', 'max:2048', 'regex:/^https:\/\/(?:www\.)?github\.com\//i'],
            'provider' => ['sometimes', 'array'],
            'provider.id' => ['nullable', 'string', Rule::in(config('services.openai.providers'))],
            'provider.api_key' => ['nullable', 'string', 'max:512'],
        ];
    }
}
