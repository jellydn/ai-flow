<?php

namespace App\Services;

use App\Models\Run;
use InvalidArgumentException;

/**
 * Transform a completed Run into the lightweight summary shape
 * used by the home-page recent-runs endpoint.
 *
 * Reuses GitHubService::parse() for repo slug + type derivation
 * so URL-shape knowledge lives in one module (ADR-0010).
 */
class RecentRunSummary
{
    public static function from(Run $run): array
    {
        $sourceUrl = $run->source_url ?? '';

        $repo = null;
        $type = 'Repository';

        try {
            $ref = app(GitHubService::class)->parse($sourceUrl);
            $repo = "{$ref->owner}/{$ref->repo}";
            $type = match ($ref->type) {
                'pull_request' => 'Pull request',
                'issue' => 'Issue',
                default => 'Repository',
            };
        } catch (InvalidArgumentException) {
            // Malformed or unsupported URL — leave repo null, type 'Repository'.
        }

        $result = $run->result ?? [];
        $findings = isset($result['findings']) ? count($result['findings']) : 0;
        $risk = $result['risk'] ?? '—';

        $durationSeconds = null;
        if ($run->started_at && $run->completed_at) {
            $durationSeconds = (int) $run->started_at->diffInSeconds($run->completed_at);
        }

        return [
            'id' => $run->id,
            'repo' => $repo,
            'type' => $type,
            'launcher_slug' => $run->launcher?->slug,
            'launcher_name' => $run->launcher?->name,
            'risk' => $risk,
            'findings_count' => $findings,
            'has_verification_steps' => ! empty($result['verification_steps']),
            'duration_seconds' => $durationSeconds,
            'completed_at' => $run->completed_at?->toIso8601String(),
        ];
    }
}
