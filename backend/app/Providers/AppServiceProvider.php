<?php

namespace App\Providers;

use App\Contracts\AIProviderInterface;
use App\Services\OpenAIProvider;
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
        $this->app->bind(AIProviderInterface::class, OpenAIProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('runs', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('runs-stream', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        if (
            app()->environment('production')
            && ! app()->runningUnitTests()
            && config('database.default') === 'sqlite'
        ) {
            throw new RuntimeException('SQLite must not be used as the production database. Set DB_CONNECTION to mysql or pgsql.');
        }

        if (app()->environment('production') && strtolower((string) env('LOG_LEVEL', 'warning')) === 'debug') {
            Log::warning('LOG_LEVEL is debug in production; set LOG_LEVEL=warning or error to reduce sensitive log exposure.');
        }
    }
}
