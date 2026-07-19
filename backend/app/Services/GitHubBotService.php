<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Parses @ai-flow commands from GitHub comments and posts results back.
 *
 * Authentication priority:
 * 1. GitHub App (GITHUB_APP_ID + GITHUB_APP_PRIVATE_KEY) → installation tokens
 * 2. GITHUB_TOKEN fallback → personal access token
 */
class GitHubBotService
{
    // ── Command parsing ────────────────────────────────────────────────

    /**
     * Parse an @ai-flow command from a comment body.
     *
     * @return array{command: string, launcher: string}|null
     *                                                       Null if no command was found or the command is unknown.
     */
    public function parseCommand(string $commentBody): ?array
    {
        $pattern = '/@ai-flow\s+(review|plan|explain|doctor)\b/i';

        if (! preg_match($pattern, $commentBody, $matches)) {
            return null;
        }

        $command = strtolower($matches[1]);
        $launcherSlug = config('github-bot.commands.'.$command);

        if ($launcherSlug === null) {
            return null;
        }

        return [
            'command' => $command,
            'launcher' => $launcherSlug,
        ];
    }

    /**
     * Build the URL for the issue/PR the comment was posted on.
     */
    public function buildSourceUrl(string $owner, string $repo, string $type, int $number): string
    {
        $path = match ($type) {
            'pull_request' => "/{$owner}/{$repo}/pull/{$number}",
            'issue' => "/{$owner}/{$repo}/issues/{$number}",
            default => "/{$owner}/{$repo}",
        };

        return "https://github.com{$path}";
    }

    // ── Comment posting ─────────────────────────────────────────────────

    /**
     * Post a new comment on an issue or PR. Returns the created comment ID.
     *
     * @throws RuntimeException
     */
    public function postComment(string $owner, string $repo, int $number, string $body): int
    {
        $response = $this->client()->post(
            "/repos/{$owner}/{$repo}/issues/{$number}/comments",
            ['body' => $body],
        );

        if (! $response->successful()) {
            throw new RuntimeException("Failed to post comment: HTTP {$response->status()}");
        }

        return (int) $response->json('id');
    }

    /**
     * Update an existing comment.
     *
     * @throws RuntimeException
     */
    public function updateComment(int $commentId, string $body): void
    {
        $response = $this->client()->patch(
            "/repos/issues/comments/{$commentId}",
            ['body' => $body],
        );

        if (! $response->successful()) {
            throw new RuntimeException("Failed to update comment {$commentId}: HTTP {$response->status()}");
        }
    }

    // ── Result formatting ───────────────────────────────────────────────

    /**
     * Format a run result as a GitHub comment body.
     */
    public function formatResultComment(string $label, string $launcherSlug, array $result, ?string $error): string
    {
        $maxLen = config('github-bot.result_max_length', 2000);

        if (filled($error)) {
            return implode("\n", array_filter([
                "<!-- {$label}-comment -->",
                "## ❌ ai-flow `{$launcherSlug}` failed",
                '',
                '```',
                $error,
                '```',
            ]));
        }

        $summary = mb_substr((string) ($result['summary'] ?? ''), 0, $maxLen);

        $lines = [
            "<!-- {$label}-comment -->",
            "## 🤖 ai-flow `{$launcherSlug}` results",
            '',
            $summary,
        ];

        if (! empty($result['risk'])) {
            $lines[] = '';
            $lines[] = '**Risk level:** '.$result['risk'];
        }

        if (! empty($result['findings'])) {
            $lines[] = '';
            $lines[] = '**Findings:** '.count($result['findings']).' issue(s) identified.';
            $lines[] = '';
            $lines[] = '[View full report]('.config('app.url').'/runs/'.($result['run_id'] ?? '').')';
        }

        return implode("\n", $lines);
    }

    /**
     * Format a progress comment body.
     */
    public function formatProgressComment(string $label, string $launcherSlug, string $status): string
    {
        $icon = match ($status) {
            'queued' => '⏳',
            'running' => '🔄',
            'completed' => '✅',
            'failed' => '❌',
            default => '⏳',
        };

        $message = match ($status) {
            'queued' => 'Queued — waiting to start…',
            'running' => 'Analyzing with ai-flow…',
            'completed' => 'Analysis complete!',
            'failed' => 'Analysis failed.',
            default => 'Processing…',
        };

        return implode("\n", [
            "<!-- {$label}-comment -->",
            "{$icon} **ai-flow `{$launcherSlug}`**: {$message}",
            '',
            '_This comment will update when the analysis finishes._',
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────

    /**
     * Build an authenticated HTTP client for the GitHub API.
     *
     * Tries GitHub App auth first (installation token), falls back to
     * GITHUB_TOKEN, then unauthenticated (low rate limit).
     */
    private function client(): PendingRequest
    {
        $http = Http::baseUrl('https://api.github.com')
            ->acceptJson()
            ->withUserAgent('ai-flow-bot')
            ->timeout(15)
            ->retry(2, 200, null, false);

        // GitHub App auth: mint a short-lived installation token.
        $appId = config('github-bot.app_id');
        $privateKey = config('github-bot.app_private_key');

        if (filled($appId) && filled($privateKey)) {
            $http = $http->withToken($this->appInstallationToken((int) $appId, $privateKey));

            return $http;
        }

        // PAT fallback.
        if ($token = config('services.github.token')) {
            $http = $http->withToken($token);
        }

        return $http;
    }

    /**
     * Generate a GitHub App installation access token.
     *
     * This is a simplified impl that gets the first installation.
     * For multi-installation apps, extend to resolve by owner/repo.
     */
    private function appInstallationToken(int $appId, string $privateKey): string
    {
        $cacheKey = "github-bot:installation-token:{$appId}";

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(50),  // Tokens expire after 1 hour
            function () use ($appId, $privateKey) {
                $jwt = $this->appJwt($appId, $privateKey);

                // List installations for this app.
                $installationsResponse = Http::baseUrl('https://api.github.com')
                    ->acceptJson()
                    ->withUserAgent('ai-flow-bot')
                    ->withHeader('Authorization', "Bearer {$jwt}")
                    ->get('/app/installations');

                if (! $installationsResponse->successful()) {
                    throw new RuntimeException('Failed to list GitHub App installations.');
                }

                $installations = $installationsResponse->json();

                if (empty($installations)) {
                    throw new RuntimeException('The GitHub App is not installed on any repositories.');
                }

                $installationId = $installations[0]['id'];

                // Create an installation access token.
                $tokenResponse = Http::baseUrl('https://api.github.com')
                    ->acceptJson()
                    ->withUserAgent('ai-flow-bot')
                    ->withHeader('Authorization', "Bearer {$jwt}")
                    ->post("/app/installations/{$installationId}/access_tokens");

                if (! $tokenResponse->successful()) {
                    throw new RuntimeException('Failed to create GitHub App installation token.');
                }

                return $tokenResponse->json('token');
            },
        );
    }

    /**
     * Create a JWT for GitHub App authentication (RS256).
     */
    private function appJwt(int $appId, string $privateKey): string
    {
        $now = time();

        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'iat' => $now - 60,
            'exp' => $now + 600,
            'iss' => (string) $appId,
        ]));

        $signature = '';
        $key = openssl_get_privatekey($privateKey);

        if ($key === false) {
            throw new RuntimeException('Invalid GitHub App private key.');
        }

        openssl_sign("{$header}.{$payload}", $signature, $key, OPENSSL_ALGO_SHA256);

        return "{$header}.{$payload}.".base64_encode($signature);
    }
}
