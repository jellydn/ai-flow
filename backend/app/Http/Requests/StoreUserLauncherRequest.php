<?php

namespace App\Http\Requests;

use App\Models\Launcher;
use App\Models\UserLauncher;
use App\Rules\JsonObjectSchemaRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserLauncherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $builtInSlugs = Launcher::pluck('slug')->toArray();

        return [
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(UserLauncher::class, 'slug')->where(fn ($q) => $q->where('user_id', $user->id)),
                Rule::notIn($builtInSlugs),
            ],
            'name' => ['required', 'string', 'max:128'],
            'description' => ['required', 'string', 'max:512'],
            'prompt_template' => ['required', 'string', 'min:20'],
            'input_type' => ['required', 'string', Rule::in(['repository', 'pull_request', 'issue'])],
            'output_schema' => [
                'required',
                'json',
                new JsonObjectSchemaRule,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and single hyphens.',
            'slug.not_in' => 'This slug is reserved for a built-in launcher.',
            'output_schema.json' => 'The output schema must be valid JSON.',
            'prompt_template.min' => 'The prompt must be at least 20 characters.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'output_schema' => 'output schema',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedOutputSchema(): array
    {
        return json_decode((string) $this->validated('output_schema'), associative: true, flags: JSON_THROW_ON_ERROR);
    }
}
