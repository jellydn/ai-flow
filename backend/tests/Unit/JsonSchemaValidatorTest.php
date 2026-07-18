<?php

namespace Tests\Unit;

use App\Services\JsonSchemaValidator;
use RuntimeException;
use Tests\TestCase;

class JsonSchemaValidatorTest extends TestCase
{
    private JsonSchemaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new JsonSchemaValidator;
    }

    public function test_validates_simple_object_with_required_fields(): void
    {
        $schema = [
            'type' => 'object',
            'required' => ['summary'],
            'properties' => ['summary' => ['type' => 'string']],
        ];

        $this->expectNotToPerformAssertions();
        $this->validator->validate(['summary' => 'ok'], $schema);
    }

    public function test_throws_on_missing_required_field(): void
    {
        $schema = [
            'type' => 'object',
            'required' => ['summary'],
            'properties' => ['summary' => ['type' => 'string']],
        ];

        // Use a valid object (not a list) that's missing the required field.
        // An empty array [] is a list, so it fails the type check before
        // reaching the required-field check.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing required field: result.summary');
        $this->validator->validate(['extra' => 'x'], $schema);
    }

    public function test_throws_on_wrong_type(): void
    {
        $schema = ['type' => 'string'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be string');
        $this->validator->validate(42, $schema);
    }

    public function test_validates_enum_values(): void
    {
        $schema = ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']];

        $this->expectNotToPerformAssertions();
        $this->validator->validate('high', $schema);
    }

    public function test_throws_on_invalid_enum_value(): void
    {
        $schema = ['type' => 'string', 'enum' => ['low', 'medium', 'high']];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid value');
        $this->validator->validate('critical', $schema);
    }

    public function test_validates_nested_array_of_objects(): void
    {
        $schema = [
            'type' => 'object',
            'required' => ['findings'],
            'properties' => [
                'findings' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['severity', 'title'],
                        'properties' => [
                            'severity' => ['type' => 'string', 'enum' => ['info', 'low', 'medium', 'high']],
                            'title' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $this->expectNotToPerformAssertions();
        $this->validator->validate([
            'findings' => [
                ['severity' => 'high', 'title' => 'Bug'],
                ['severity' => 'low', 'title' => 'Nit'],
            ],
        ], $schema);
    }

    public function test_throws_on_invalid_nested_array_item_type(): void
    {
        $schema = [
            'type' => 'array',
            'items' => ['type' => 'string'],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('result.0 must be string');
        $this->validator->validate([42], $schema);
    }

    public function test_throws_on_invalid_nested_object_field_type(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'findings' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['severity'],
                        'properties' => ['severity' => ['type' => 'string']],
                    ],
                ],
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('result.findings.1.severity must be string');
        $this->validator->validate([
            'findings' => [
                ['severity' => 'high'],
                ['severity' => 99],
            ],
        ], $schema);
    }

    public function test_rejects_unexpected_field_when_additional_properties_false(): void
    {
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['summary'],
            'properties' => ['summary' => ['type' => 'string']],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unexpected field: result.extra');
        $this->validator->validate(['summary' => 'ok', 'extra' => 'nope'], $schema);
    }

    public function test_allows_unexpected_field_when_additional_properties_default(): void
    {
        $schema = [
            'type' => 'object',
            'required' => ['summary'],
            'properties' => ['summary' => ['type' => 'string']],
        ];

        $this->expectNotToPerformAssertions();
        $this->validator->validate(['summary' => 'ok', 'extra' => 'fine'], $schema);
    }

    public function test_validates_boolean_type(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate(true, ['type' => 'boolean']);
    }

    public function test_validates_integer_type(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate(42, ['type' => 'integer']);
    }

    public function test_validates_number_type_accepts_float(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate(3.14, ['type' => 'number']);
    }

    public function test_validates_array_must_be_list(): void
    {
        $this->expectException(RuntimeException::class);
        $this->validator->validate(['a' => 1], ['type' => 'array']); // associative → not a list
    }

    public function test_validates_object_must_not_be_list(): void
    {
        $this->expectException(RuntimeException::class);
        $this->validator->validate([1, 2, 3], ['type' => 'object']); // list → not an object
    }

    public function test_validates_full_launcher_output_schema(): void
    {
        // The shared output schema from BaseLauncher.
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['summary', 'risk', 'findings', 'verification_steps'],
            'properties' => [
                'summary' => ['type' => 'string'],
                'risk' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
                'findings' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['severity', 'title', 'description', 'recommendation'],
                        'properties' => [
                            'severity' => ['type' => 'string', 'enum' => ['info', 'low', 'medium', 'high', 'critical']],
                            'title' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'recommendation' => ['type' => 'string'],
                        ],
                    ],
                ],
                'verification_steps' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];

        $valid = [
            'summary' => 'Looks good.',
            'risk' => 'low',
            'findings' => [
                ['severity' => 'info', 'title' => 'Style', 'description' => 'Minor.', 'recommendation' => 'Ignore.'],
            ],
            'verification_steps' => ['Run tests.'],
        ];

        $this->expectNotToPerformAssertions();
        $this->validator->validate($valid, $schema);
    }
}
