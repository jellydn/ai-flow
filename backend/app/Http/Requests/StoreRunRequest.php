<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['launcher' => ['required', 'string', 'exists:launchers,slug'], 'source_url' => ['required', 'url', 'max:2048', 'regex:/^https:\/\/(?:www\.)?github\.com\//i']];
    }
}
