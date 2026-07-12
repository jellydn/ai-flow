<?php

namespace App\Launchers;

class ReviewPullRequestLauncher extends BaseLauncher
{
    public static function metadata(): array
    {
        return static::make('review-pr', 'Review Pull Request', 'Review a pull request for defects, risks, and missing tests.', 'pull_request', 'Review this pull request for correctness, regressions, security, and missing tests. Cite changed files. Use only the supplied GitHub context.');
    }
}
