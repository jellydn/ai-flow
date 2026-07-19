<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserLauncherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:128'],
            'description' => ['sometimes', 'required', 'string', 'max:512'],
            'prompt_template' => ['sometimes', 'required', 'string', 'min:20'],
            'input_type' => ['sometimes', 'required', 'string', Rule::in(['repository', 'pull_request', 'issue'])],
            'output_schema' => ['sometimes', 'required', 'json'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'output_schema.json' => 'The output schema must be valid JSON.',
            'prompt_template.min' => 'The prompt must be at least 20 characters.',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validatedOutputSchema(): ?array
    {
        $raw = $this->validated('output_schema');
        if ($raw === null) {
            return null;
        }

        return json_decode((string) $raw, associative: true, flags: JSON_THROW_ON_ERROR);
    }
}
