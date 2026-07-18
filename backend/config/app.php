<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Frontend URL
    |--------------------------------------------------------------------------
    |
    | Where magic-link verification redirects after sign-in. Defaults to APP_URL
    | for the same-origin React SPA served by Laravel.
    |
    */

    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost')),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Runs Rate Limit
    |--------------------------------------------------------------------------
    |
    | Max workflow launches per hour per IP. E2E and CI set a higher value
    | (e.g. 100) via RUNS_RATE_LIMIT_PER_HOUR so the full launcher suite can
    | POST multiple runs in a single test pass without hitting the limiter.
    |
    */

    'runs_rate_limit_per_hour' => env('RUNS_RATE_LIMIT_PER_HOUR', 5),

    /*
    |--------------------------------------------------------------------------
    | Auth Rate Limits
    |--------------------------------------------------------------------------
    |
    | Max auth-login / auth-register attempts per minute per IP. E2E and CI
    | set higher values so parallel .real.spec.ts files can each register
    | user accounts without hitting the limiter.
    |
    */

    'auth_login_rate_limit_per_min' => env('AUTH_LOGIN_RATE_LIMIT_PER_MIN', 10),

    'auth_register_rate_limit_per_min' => env('AUTH_REGISTER_RATE_LIMIT_PER_MIN', 5),

    /*
    |--------------------------------------------------------------------------
    | Other Rate Limits
    |--------------------------------------------------------------------------
    |
    | Remaining rate limiters that benefit from test-time configurability.
    |
    */

    'runs_stream_rate_limit_per_min' => env('RUNS_STREAM_RATE_LIMIT_PER_MIN', 30),

    'magic_link_rate_limit_per_min' => env('MAGIC_LINK_RATE_LIMIT_PER_MIN', 3),

    'credentials_rate_limit_per_min' => env('CREDENTIALS_RATE_LIMIT_PER_MIN', 10),

];
