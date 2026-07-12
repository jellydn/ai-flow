<?php

namespace App\Services;

use RuntimeException;

class JsonSchemaValidator
{
    public function validate(mixed $value, array $schema, string $path = 'result'): void
    {
        $type = $schema['type'] ?? null;
        $valid = match ($type) {
            'object' => is_array($value) && ! array_is_list($value),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => is_null($value),
            default => true,
        };

        if (! $valid) {
            throw new RuntimeException("AI result field {$path} must be {$type}.");
        }

        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            throw new RuntimeException("AI result field {$path} has an invalid value.");
        }

        if ($type === 'object') {
            $this->validateObject($value, $schema, $path);
        }

        if ($type === 'array' && isset($schema['items'])) {
            foreach ($value as $key => $item) {
                $this->validate($item, $schema['items'], "{$path}.{$key}");
            }
        }
    }

    private function validateObject(array $value, array $schema, string $path): void
    {
        foreach ($schema['required'] ?? [] as $key) {
            if (! array_key_exists($key, $value)) {
                throw new RuntimeException("AI result missing required field: {$path}.{$key}.");
            }
        }

        foreach ($value as $key => $item) {
            if (! isset($schema['properties'][$key])) {
                if (($schema['additionalProperties'] ?? true) === false) {
                    throw new RuntimeException("AI result has unexpected field: {$path}.{$key}.");
                }

                continue;
            }

            $this->validate($item, $schema['properties'][$key], "{$path}.{$key}");
        }
    }
}
