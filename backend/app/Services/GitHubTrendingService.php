<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubTrendingService
{
    private const LIMIT = 3;

    private const TRENDING_URL = 'https://github.com/trending?since=daily';

    private const LAST_SUCCESSFUL_KEY = 'github_trending:last_successful';

    private const LAST_SUCCESSFUL_TTL_DAYS = 3;

    private const FAILURE_CACHE_MINUTES = 10;

    /**
     * @return list<array{repo: string, url: string}>
     */
    public function dailyTopRepositories(): array
    {
        $dailyKey = 'github_trending:daily:'.now()->toDateString();

        $cached = Cache::get($dailyKey);
        if ($cached !== null && is_array($cached)) {
            return $cached;
        }

        $fresh = $this->fetchDailyTopRepositories();
        if ($fresh !== []) {
            Cache::put($dailyKey, $fresh, now()->endOfDay());
            Cache::put(self::LAST_SUCCESSFUL_KEY, $fresh, now()->addDays(self::LAST_SUCCESSFUL_TTL_DAYS));

            return $fresh;
        }

        $stale = Cache::get(self::LAST_SUCCESSFUL_KEY);
        $fallback = is_array($stale) ? $stale : [];

        Cache::put($dailyKey, $fallback, now()->addMinutes(self::FAILURE_CACHE_MINUTES));

        return $fallback;
    }

    /**
     * @return list<array{repo: string, url: string}>
     */
    private function fetchDailyTopRepositories(): array
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'User-Agent' => 'ai-flow-trending/1.0',
                    'Accept' => 'text/html',
                ])
                ->get(self::TRENDING_URL);

            if (! $response->successful()) {
                Log::warning('GitHub trending fetch failed', ['status' => $response->status()]);

                return [];
            }

            return $this->parseTrendingHtml($response->body());
        } catch (\Throwable $e) {
            Log::warning('GitHub trending fetch error', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return list<array{repo: string, url: string}>
     */
    private function parseTrendingHtml(string $html): array
    {
        $repos = [];

        if (preg_match_all(
            '#<h2[^>]*>\s*<a[^>]+href="/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)[^"]*"#',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $repo = $match[1].'/'.$match[2];
                if (isset($repos[$repo])) {
                    continue;
                }
                $repos[$repo] = [
                    'repo' => $repo,
                    'url' => 'https://github.com/'.$repo,
                ];
                if (count($repos) >= self::LIMIT) {
                    break;
                }
            }
        }

        return array_values($repos);
    }
}
