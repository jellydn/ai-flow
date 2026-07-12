<?php

namespace App\Launchers;

class LaravelDoctorLauncher extends BaseLauncher
{
    public static function metadata(): array
    {
        return static::make('laravel-doctor', 'Laravel Project Doctor', 'Assess a Laravel project for quality, security, and maintainability.', 'repository', 'Assess this Laravel project architecture, security, maintainability, dependencies, and testing. Prioritize concrete findings. Use only the supplied GitHub context.');
    }
}
