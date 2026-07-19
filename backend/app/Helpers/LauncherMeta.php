<?php

/**
 * Server-side launcher metadata helpers.
 *
 * Built-in launchers get curated icon/tone values (mirrors frontend launcherMeta.ts).
 * Custom launchers get auto-assigned icon/tone via deterministic hash of slug.
 */

/**
 * @return array{icon: string, tone: string}
 */
function launcherServerMeta(string $slug): array
{
    $builtIn = [
        'review-pr' => ['icon' => 'GitPullRequest', 'tone' => 'orange'],
        'plan-issue' => ['icon' => 'ListTodo', 'tone' => 'blue'],
        'explain-repository' => ['icon' => 'BookOpen', 'tone' => 'purple'],
        'laravel-doctor' => ['icon' => 'Stethoscope', 'tone' => 'green'],
    ];

    return $builtIn[$slug] ?? ['icon' => 'Sparkles', 'tone' => 'blue'];
}

/**
 * @return array{icon: string, tone: string}
 */
function customLauncherMeta(string $slug): array
{
    $icons = ['Sparkles', 'Zap', 'Star', 'Target', 'Crosshair', 'Award'];
    $tones = ['blue', 'purple', 'green', 'orange'];

    // Deterministic assignment: hash the slug to pick icon/tone.
    $hash = crc32($slug);
    $icon = $icons[abs($hash) % count($icons)];
    // Shift the hash to get a different distribution for tone vs icon.
    $tone = $tones[abs((int) ($hash / 7)) % count($tones)];

    return ['icon' => $icon, 'tone' => $tone];
}
