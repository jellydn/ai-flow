<?php

namespace Tests\Unit;

use App\Services\GitHubTrendingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Edge-case tests for GitHubTrendingService HTML parsing (CONCERNS B1/F2).
 *
 * The service is already hardened (daily cache, stale-cache fallback on
 * failure, 10-min failure cache); these tests pin the parser against
 * malformed/empty/duplicate HTML so a GitHub HTML structure change is
 * caught early rather than surfacing as a broken home page.
 */
class GitHubTrendingServiceParseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_returns_empty_array_when_html_has_no_repo_links(): void
    {
        Http::fake(['github.com/trending*' => Http::response('<html><body>no repos here</body></html>', 200)]);

        $service = app(GitHubTrendingService::class);
        $this->assertSame([], $service->dailyTopRepositories());
    }

    public function test_returns_empty_array_for_empty_html(): void
    {
        Http::fake(['github.com/trending*' => Http::response('', 200)]);

        $service = app(GitHubTrendingService::class);
        $this->assertSame([], $service->dailyTopRepositories());
    }

    public function test_deduplicates_repeated_repos(): void
    {
        $html = <<<'HTML'
        <article><h2><a href="/dup/repo">one</a></h2></article>
        <article><h2><a href="/dup/repo">one-again</a></h2></article>
        <article><h2><a href="/other/repo-two">two</a></h2></article>
        <article><h2><a href="/third/repo-three">three</a></h2></article>
        HTML;

        Http::fake(['github.com/trending*' => Http::response($html, 200)]);

        $service = app(GitHubTrendingService::class);
        $repos = $service->dailyTopRepositories();

        // LIMIT=3, and dup/repo should appear only once.
        $this->assertCount(3, $repos);
        $names = array_column($repos, 'repo');
        $this->assertSame(['dup/repo', 'other/repo-two', 'third/repo-three'], $names);
    }

    public function test_caps_at_three_repos_even_when_more_available(): void
    {
        $html = '';
        for ($i = 1; $i <= 10; $i++) {
            $html .= "<article><h2><a href=\"/owner{$i}/repo{$i}\">r{$i}</a></h2></article>\n";
        }

        Http::fake(['github.com/trending*' => Http::response($html, 200)]);

        $service = app(GitHubTrendingService::class);
        $repos = $service->dailyTopRepositories();

        $this->assertCount(3, $repos);
        $this->assertSame('owner1/repo1', $repos[0]['repo']);
    }

    public function test_builds_full_github_url_for_each_repo(): void
    {
        $html = '<article><h2><a href="/foo/bar">x</a></h2></article>';

        Http::fake(['github.com/trending*' => Http::response($html, 200)]);

        $service = app(GitHubTrendingService::class);
        $repos = $service->dailyTopRepositories();

        $this->assertNotEmpty($repos);
        $this->assertSame('https://github.com/foo/bar', $repos[0]['url']);
    }

    public function test_ignores_links_without_h2_wrapper(): void
    {
        // Links not inside an <h2> should not be matched by the parser regex.
        $html = '<a href="/not/a/repo">random</a><article><h2><a href="/real/repo">x</a></h2></article>';

        Http::fake(['github.com/trending*' => Http::response($html, 200)]);

        $service = app(GitHubTrendingService::class);
        $repos = $service->dailyTopRepositories();

        $this->assertCount(1, $repos);
        $this->assertSame('real/repo', $repos[0]['repo']);
    }

    public function test_handles_repo_names_with_dots_and_underscores(): void
    {
        $html = '<article><h2><a href="/user.name/my_repo.io">x</a></h2></article>';

        Http::fake(['github.com/trending*' => Http::response($html, 200)]);

        $service = app(GitHubTrendingService::class);
        $repos = $service->dailyTopRepositories();

        $this->assertSame('user.name/my_repo.io', $repos[0]['repo']);
    }

    public function test_caches_successful_result_for_the_day(): void
    {
        $html = '<article><h2><a href="/foo/bar">x</a></h2></article>';
        Http::fake(['github.com/trending*' => Http::response($html, 200)]);

        $service = app(GitHubTrendingService::class);
        $service->dailyTopRepositories();

        $dailyKey = 'github_trending:daily:'.now()->toDateString();
        $this->assertTrue(Cache::has($dailyKey));
    }
}
