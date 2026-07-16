<?php

namespace App\Services;

use App\Models\Run;

/**
 * Transform a completed Run into the lightweight summary shape
 * used by the home-page recent-runs endpoint.
 *
 * Repo slug and type are stored on the Run at creation time
 * (RunController::store) — no runtime parsing needed.
 */
class RecentRunSummary
{
    public static function from(Run $run): array
    {
        $repo = $run->repo_slug;
        $type = match ($run->repo_type) {
            'pull_request' => 'Pull request',
            'issue' => 'Issue',
            default => 'Repository',
        };

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
