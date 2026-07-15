<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrendingRepositoriesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_trending_repositories_returns_top_three_from_github_html(): void
    {
        Cache::flush();

        $html = <<<'HTML'
        <article><h2 class="h3"><a href="/first/repo-one">one</a></h2></article>
        <article><h2 class="h3"><a href="/second/repo-two">two</a></h2></article>
        <article><h2 class="h3"><a href="/third/repo-three">three</a></h2></article>
        <article><h2 class="h3"><a href="/fourth/repo-four">four</a></h2></article>
        HTML;

        Http::fake([
            'github.com/trending*' => Http::response($html, 200),
        ]);

        $response = $this->getJson('/api/trending-repositories');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.repo', 'first/repo-one')
            ->assertJsonPath('data.0.url', 'https://github.com/first/repo-one')
            ->assertJsonPath('data.2.repo', 'third/repo-three');
    }

    public function test_trending_repositories_returns_empty_when_fetch_fails_and_no_stale_cache(): void
    {
        Cache::flush();

        Http::fake([
            'github.com/trending*' => Http::response('Service unavailable', 503),
        ]);

        $response = $this->getJson('/api/trending-repositories');

        $response->assertOk()->assertJsonPath('data', []);

        $dailyKey = 'github_trending:daily:'.now()->toDateString();
        $this->assertFalse(Cache::has($dailyKey));
    }

    public function test_trending_repositories_returns_stale_cache_when_github_fails(): void
    {
        Cache::flush();

        $stale = [
            ['repo' => 'cached/owner', 'url' => 'https://github.com/cached/owner'],
        ];
        Cache::put('github_trending:last_successful', $stale, now()->addDay());

        Http::fake([
            'github.com/trending*' => Http::response('Service unavailable', 503),
        ]);

        $response = $this->getJson('/api/trending-repositories');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.repo', 'cached/owner');
    }
}
