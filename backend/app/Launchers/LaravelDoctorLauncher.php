<?php

namespace App\Launchers;

class LaravelDoctorLauncher extends BaseLauncher
{
    public static function metadata(): array
    {
        return static::make(
            'laravel-doctor',
            'Laravel Project Doctor',
            'Assess a Laravel project for quality, security, and maintainability.',
            'repository',
            'You are a senior Laravel reviewer. Using only the supplied GitHub context, assess this Laravel project for: application structure and boundaries; routing, middleware, and authorization; validation and mass-assignment safety; queues, jobs, and scheduled tasks; configuration and secrets handling; dependency and framework version hygiene; database migrations and N+1/query risks; test coverage signals and CI hints. Prioritize concrete, actionable findings with file or area references when visible in context. Do not invent files or APIs not present in the context.',
        );
    }
}
