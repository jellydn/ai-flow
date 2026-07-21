<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validate that a JSON string is a JSON Schema *object* — not an array,
 * primitive, or empty object — and that it carries at least one of the
 * recognised schema anchor keys (`type` or `properties`).
 *
 * This mirrors the frontend {@see isValidJsonObjectSchema} shape check so the
 * client and server enforce the same rule for custom launcher output schemas.
 * It is intentionally a structural check, not full JSON Schema validation.
 */
class JsonObjectSchemaRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $decoded = json_decode((string) $value, associative: true);

        if (! is_array($decoded) || array_is_list($decoded) || $decoded === []) {
            $fail('The :attribute must be a valid JSON object.');

            return;
        }

        if (! array_key_exists('type', $decoded) && ! array_key_exists('properties', $decoded)) {
            $fail('The :attribute must contain a "type" or "properties" key.');
        }
    }
}
