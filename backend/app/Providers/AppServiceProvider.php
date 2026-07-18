<?php

namespace App\Providers;

use App\Events\RunProgressed;
use App\Listeners\CacheRunProgressedVersion;
use App\Support\AiProviderRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Max credential-verification requests per minute per authenticated user.
     *
     * Shared with tests so the limiter and its test stay in sync.
     */
    public const CREDENTIAL_VERIFY_PER_MINUTE = 10;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiProviderRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (
            app()->environment('production')
            && str_starts_with((string) config('app.url'), 'https://')
        ) {
            URL::forceScheme('https');
        }

        Event::listen(RunProgressed::class, CacheRunProgressedVersion::class);

        RateLimiter::for('runs', fn (Request $request) => Limit::perHour((int) config('app.runs_rate_limit_per_hour', 5))->by($request->ip()));
        RateLimiter::for('runs-stream', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('magic-link', fn (Request $request) => Limit::perMinute(3)->by($request->ip().'|'.$request->input('email', '')));
        RateLimiter::for('auth-login', fn (Request $request) => Limit::perMinute(10)->by($request->ip().'|'.$request->input('email', '')));
        RateLimiter::for('auth-register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('credentials', fn (Request $request) => Limit::perMinute(self::CREDENTIAL_VERIFY_PER_MINUTE)->by($request->user()->id));

        // HTTP only: allow artisan during Cloud build (package:discover) and workers before DB env is wired.
        // Production uses Neon PostgreSQL; file-backed sqlite is local/CI only.
        if (
            app()->environment('production')
            && ! app()->runningInConsole()
            && ! app()->runningUnitTests()
            && config('database.default') === 'sqlite'
        ) {
            throw new RuntimeException(
                'SQLite must not be used as the production database. Set DB_CONNECTION to pgsql for Neon PostgreSQL.'
            );
        }

        // Production PostgreSQL should use TLS for external hosts (e.g., Neon).
        // Internal Docker network hostnames (e.g., dokku-postgres-ai-flow) are
        // exempt since container-to-container traffic does not need TLS.
        if (
            app()->environment('production')
            && ! app()->runningInConsole()
            && ! app()->runningUnitTests()
            && config('database.default') === 'pgsql'
        ) {
            $pgHost = (string) config('database.connections.pgsql.host');
            if (
                str_contains($pgHost, '.')
                && ! in_array(strtolower((string) config('database.connections.pgsql.sslmode')), ['require', 'verify-ca', 'verify-full'], true)
            ) {
                throw new RuntimeException(
                    'Production PostgreSQL must use TLS. Set DB_SSLMODE=require (or verify-ca / verify-full) for Neon.'
                );
            }
        }

        // The sync queue driver executes jobs inside the HTTP request, which
        // would run slow GitHub + OpenAI calls synchronously and block the
        // response. Production must use a real queue (database, redis, etc.).
        if (
            app()->environment('production')
            && ! app()->runningInConsole()
            && ! app()->runningUnitTests()
            && config('queue.default') === 'sync'
        ) {
            throw new RuntimeException(
                'QUEUE_CONNECTION must not be "sync" in production. Set QUEUE_CONNECTION=database (or redis) so AI and GitHub work run asynchronously.'
            );
        }

        if (app()->environment('production') && strtolower((string) env('LOG_LEVEL', 'warning')) === 'debug') {
            Log::warning('LOG_LEVEL is debug in production; set LOG_LEVEL=warning or error to reduce sensitive log exposure.');
        }

        // Alert operators when stored BYOK credentials fall back to APP_KEY
        // instead of a dedicated CREDENTIAL_ENCRYPTION_KEY. Rotating APP_KEY
        // in this state would silently invalidate every stored credential.
        // Uses config('credentials.uses_dedicated_key') (not env() directly)
        // so the check works correctly after `php artisan config:cache`.
        if (
            app()->environment('production')
            && ! app()->runningInConsole()
            && ! app()->runningUnitTests()
            && ! config('credentials.uses_dedicated_key')
        ) {
            Log::warning('CREDENTIAL_ENCRYPTION_KEY is not set in production; stored BYOK credentials are encrypted with APP_KEY. Set a dedicated CREDENTIAL_ENCRYPTION_KEY to decouple credential encryption from APP_KEY rotation (see config/credentials.php for the rotation procedure).');
        }
    }
}
