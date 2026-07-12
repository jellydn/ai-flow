<?php

namespace App\Providers;

use App\Contracts\RunExecutorInterface;
use App\Services\RunExecutor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RunExecutorInterface::class, RunExecutor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('runs', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('runs-stream', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

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

        if (
            app()->environment('production')
            && ! app()->runningInConsole()
            && ! app()->runningUnitTests()
            && config('database.default') === 'pgsql'
            && ! in_array(strtolower((string) config('database.connections.pgsql.sslmode')), ['require', 'verify-ca', 'verify-full'], true)
        ) {
            throw new RuntimeException(
                'Production PostgreSQL must use TLS. Set DB_SSLMODE=require (or verify-ca / verify-full) for Neon.'
            );
        }

        if (app()->environment('production') && strtolower((string) env('LOG_LEVEL', 'warning')) === 'debug') {
            Log::warning('LOG_LEVEL is debug in production; set LOG_LEVEL=warning or error to reduce sensitive log exposure.');
        }
    }
}
