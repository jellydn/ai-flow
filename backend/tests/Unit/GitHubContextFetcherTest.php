<?php

namespace Tests\Unit;

use App\Data\GitHubReference;
use App\Services\GitHubContextFetcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubContextFetcherTest extends TestCase
{
    public function test_fetches_repository_data(): void
    {
        Http::fake([
            '*api.github.com/repos/a/b' => Http::response(['name' => 'b', 'full_name' => 'a/b', 'description' => 'desc', 'default_branch' => 'main']),
            '*api.github.com/repos/a/b/languages' => Http::response(['PHP' => 5000]),
            '*api.github.com/repos/a/b/readme' => Http::response(['content' => base64_encode('# Hello')]),
            '*api.github.com/repos/a/b/git/trees/main*' => Http::response(['tree' => [['path' => 'src/app.php']]]),
        ]);

        $fetcher = new GitHubContextFetcher;
        $result = $fetcher->fetch(new GitHubReference('a', 'b', 'repository'));

        $this->assertSame('b', $result['repo']['name']);
        $this->assertSame(['PHP' => 5000], $result['languages']);
        $this->assertSame('# Hello', $result['readmeContent']);
        $this->assertSame([['path' => 'src/app.php']], $result['tree']);
    }

    public function test_fetches_pull_request_data(): void
    {
        Http::fake([
            '*api.github.com/repos/a/b' => Http::response(['name' => 'b', 'full_name' => 'a/b', 'description' => null, 'default_branch' => 'main']),
            '*api.github.com/repos/a/b/languages' => Http::response([]),
            '*api.github.com/repos/a/b/readme' => Http::response(['content' => base64_encode('readme')]),
            '*api.github.com/repos/a/b/git/trees/main*' => Http::response(['tree' => []]),
            '*api.github.com/repos/a/b/pulls/1' => Http::response(['number' => 1, 'title' => 'Fix', 'body' => '', 'state' => 'open', 'user' => ['login' => 'dev']]),
            '*api.github.com/repos/a/b/pulls/1/files*' => Http::response([['filename' => 'f.php', 'status' => 'modified', 'patch' => 'diff']]),
            '*api.github.com/repos/a/b/issues/1/comments*' => Http::response([['user' => ['login' => 'r'], 'body' => 'ok']]),
        ]);

        $fetcher = new GitHubContextFetcher;
        $result = $fetcher->fetch(new GitHubReference('a', 'b', 'pull_request', 1));

        $this->assertNotNull($result['pr']);
        $this->assertSame(1, $result['pr']['number']);
        $this->assertCount(1, $result['prFiles']);
        $this->assertCount(1, $result['prComments']);
    }

    public function test_fetches_issue_data(): void
    {
        Http::fake([
            '*api.github.com/repos/a/b' => Http::response(['name' => 'b', 'full_name' => 'a/b', 'description' => null, 'default_branch' => 'main']),
            '*api.github.com/repos/a/b/languages' => Http::response([]),
            '*api.github.com/repos/a/b/readme' => Http::response(['content' => base64_encode('readme')]),
            '*api.github.com/repos/a/b/git/trees/main*' => Http::response(['tree' => []]),
            '*api.github.com/repos/a/b/issues/42' => Http::response(['number' => 42, 'title' => 'Bug', 'body' => 'desc', 'state' => 'open', 'labels' => ['bug'], 'user' => ['login' => 'reporter']]),
            '*api.github.com/repos/a/b/issues/42/comments*' => Http::response([['user' => ['login' => 'h'], 'body' => 'try this']]),
        ]);

        $fetcher = new GitHubContextFetcher;
        $result = $fetcher->fetch(new GitHubReference('a', 'b', 'issue', 42));

        $this->assertNotNull($result['issue']);
        $this->assertSame(42, $result['issue']['number']);
        $this->assertCount(1, $result['issueComments']);
    }

    public function test_handles_404_readme_gracefully(): void
    {
        Http::fake([
            '*api.github.com/repos/a/b' => Http::response(['name' => 'b', 'full_name' => 'a/b', 'description' => null, 'default_branch' => 'main']),
            '*api.github.com/repos/a/b/languages' => Http::response([]),
            '*api.github.com/repos/a/b/readme' => Http::response(null, 404),
            '*api.github.com/repos/a/b/git/trees/main*' => Http::response(['tree' => []]),
        ]);

        $fetcher = new GitHubContextFetcher;
        $result = $fetcher->fetch(new GitHubReference('a', 'b', 'repository'));

        $this->assertNull($result['readmeContent']);
    }

    public function test_uses_github_token_when_configured(): void
    {
        config()->set('services.github.token', 'ghp_test-token');
        Http::fake([
            '*api.github.com/repos/a/b' => Http::response(['name' => 'b', 'full_name' => 'a/b', 'description' => null, 'default_branch' => 'main']),
            '*api.github.com/repos/a/b/languages' => Http::response([]),
            '*api.github.com/repos/a/b/readme' => Http::response(['content' => base64_encode('readme')]),
            '*api.github.com/repos/a/b/git/trees/main*' => Http::response(['tree' => []]),
        ]);

        $fetcher = new GitHubContextFetcher;
        $fetcher->fetch(new GitHubReference('a', 'b', 'repository'));

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer ghp_test-token'));
    }
}
