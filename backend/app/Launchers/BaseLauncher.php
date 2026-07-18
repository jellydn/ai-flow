<?php

namespace App\Launchers;

use App\Contracts\LauncherInterface;

abstract class BaseLauncher implements LauncherInterface
{
    public static function outputSchema(): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['summary', 'risk', 'findings', 'verification_steps'], 'properties' => ['summary' => ['type' => 'string'], 'risk' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']], 'findings' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['severity', 'title', 'description', 'recommendation'], 'properties' => ['severity' => ['type' => 'string', 'enum' => ['info', 'low', 'medium', 'high', 'critical']], 'title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'recommendation' => ['type' => 'string']]]], 'verification_steps' => ['type' => 'array', 'items' => ['type' => 'string']]]];
    }

    protected static function make(string $slug, string $name, string $description, string $inputType, string $prompt): array
    {
        return compact('slug', 'name', 'description', 'inputType', 'prompt') + ['outputSchema' => static::outputSchema()];
    }
}
