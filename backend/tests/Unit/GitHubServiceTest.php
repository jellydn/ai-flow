<?php

namespace Tests\Unit;

use App\Services\GitHubService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GitHubServiceTest extends TestCase
{
    public function test_parses_supported_urls(): void
    {
        $s = new GitHubService;
        $this->assertSame('repository', $s->parse('https://github.com/a/b')->type);
        $this->assertSame(12, $s->parse('https://github.com/a/b/pull/12')->number);
        $this->assertSame('issue', $s->parse('https://github.com/a/b/issues/3')->type);
    }

    public function test_rejects_other_hosts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GitHubService)->parse('https://evil.test/a/b');
    }

    public function test_rejects_malformed_github_paths_and_insecure_urls(): void
    {
        foreach (['https://github.com/a/b/pull/notnumber', 'https://github.com/a/b/extra', 'http://github.com/a/b'] as $url) {
            try {
                (new GitHubService)->parse($url);
                $this->fail("Accepted malformed URL: {$url}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
