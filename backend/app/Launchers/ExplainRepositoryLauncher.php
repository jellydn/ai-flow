<?php

namespace App\Launchers;

class ExplainRepositoryLauncher extends BaseLauncher
{
    public static function metadata(): array
    {
        return static::make('explain-repository', 'Explain Repository', 'Explain a repository architecture and key components.', 'repository', 'Explain this repository architecture, purpose, key files, and how to get started. Use only the supplied GitHub context.');
    }
}
