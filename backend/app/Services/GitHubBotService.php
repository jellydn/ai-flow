<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Parses @ai-flow commands from GitHub comments and posts results back.
 *
 * Delegates GitHub API authentication to GitHubService::botClient()
 * so command parsing and comment formatting stay focused on their concern.
 */
class GitHubBotService
{
    public function __construct(private GitHubService $github) {}

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

    // ── Per-repo configuration ──────────────────────────────────────────

    /**
     * Check whether a launcher is enabled for a given repo.
     *
     * Fetches .github/ai-flow.yml from the default branch. If the file
     * doesn't exist or has no `enabled` list, all launchers are enabled.
     */
    public function isLauncherEnabled(string $owner, string $repo, string $launcherSlug, ?int $installationId = null): bool
    {
        $config = $this->fetchRepoConfig($owner, $repo, $installationId);

        if ($config === null) {
            return true; // No config file — all launchers enabled by default.
        }

        $enabled = $config['enabled'] ?? null;

        if (! is_array($enabled)) {
            return true; // No enabled list — all launchers enabled.
        }

        return in_array($launcherSlug, $enabled, true);
    }

    /**
     * Fetch and parse .github/ai-flow.yml from the repo's default branch.
     *
     * @return array|null Parsed config, or null if the file doesn't exist.
     */
    private function fetchRepoConfig(string $owner, string $repo, ?int $installationId = null): ?array
    {
        $cacheKey = "github-bot:repo-config:{$owner}:{$repo}";

        $cached = Cache::remember(
            $cacheKey,
            now()->addMinutes(5),
            function () use ($owner, $repo, $installationId) {
                $response = $this->github->botClient($installationId)->get(
                    "/repos/{$owner}/{$repo}/contents/.github/ai-flow.yml",
                );

                // Missing file or fetch failure: cache an empty sentinel so
                // Cache::remember() actually retains it. Returning null would
                // be treated as a cache miss and re-hit GitHub on every command.
                if ($response->status() === 404 || ! $response->successful()) {
                    return [];
                }

                $content = base64_decode($response->json('content', ''), true);

                if ($content === false || $content === '') {
                    return [];
                }

                return $this->parseSimpleYaml($content);
            },
        );

        // Convert the "no config" sentinel back to null for callers.
        return $cached === [] ? null : $cached;
    }

    /**
     * Parse a simple YAML-like key-value list for the `enabled` array.
     *
     * Only handles the subset of YAML that .github/ai-flow.yml uses:
     * a top-level `enabled:` key followed by a list of strings.
     */
    private function parseSimpleYaml(string $yaml): array
    {
        $result = [];
        $currentKey = null;

        foreach (explode("\n", $yaml) as $line) {
            // Skip comments and blank lines.
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            // Top-level key: value or key: (without leading spaces).
            if (preg_match('/^(\w[\w-]*)\s*:\s*(.*)$/', $trimmed, $m)) {
                $currentKey = $m[1];
                $value = trim($m[2]);

                if ($value !== '' && $value !== '0') {
                    $result[$currentKey] = $value;
                } else {
                    // Empty value after colon — start a list.
                    $result[$currentKey] = [];
                }

                continue;
            }

            // List item: "- value" (only valid inside a list key).
            if ($currentKey !== null && is_array($result[$currentKey] ?? null) && preg_match('/^-\s+(.+)$/', $trimmed, $m)) {
                $result[$currentKey][] = trim($m[1]);
            }
        }

        return $result;
    }

    // ── Comment posting ─────────────────────────────────────────────────

    /**
     * Post a new comment on an issue or PR. Returns the created comment ID.
     *
     * @throws RuntimeException
     */
    public function postComment(string $owner, string $repo, int $number, string $body, ?int $installationId = null): int
    {
        $response = $this->github->botClient($installationId)->post(
            "/repos/{$owner}/{$repo}/issues/{$number}/comments",
            ['body' => $body],
        );

        if (! $response->successful()) {
            throw new RuntimeException("Failed to post comment: HTTP {$response->status()}");
        }

        return (int) $response->json('id');
    }

    /**
     * Update an existing issue/PR comment.
     *
     * GitHub requires owner/repo in the path: PATCH
     * /repos/{owner}/{repo}/issues/comments/{commentId}.
     *
     * @throws RuntimeException
     */
    public function updateComment(string $owner, string $repo, int $commentId, string $body, ?int $installationId = null): void
    {
        $response = $this->github->botClient($installationId)->patch(
            "/repos/{$owner}/{$repo}/issues/comments/{$commentId}",
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
}
