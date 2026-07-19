<?php

namespace App\Services;

use App\Contracts\LauncherMetaInterface;

class LauncherMetaService implements LauncherMetaInterface
{
    /** @var array<string, array{icon: string, tone: string}> */
    private const BUILT_IN = [
        'review-pr' => ['icon' => 'GitPullRequest', 'tone' => 'orange'],
        'plan-issue' => ['icon' => 'ListTodo', 'tone' => 'blue'],
        'explain-repository' => ['icon' => 'BookOpen', 'tone' => 'purple'],
        'laravel-doctor' => ['icon' => 'Stethoscope', 'tone' => 'green'],
    ];

    /** @var list<string> */
    private const CUSTOM_ICONS = ['Sparkles', 'Zap', 'Star', 'Target', 'Crosshair', 'Award'];

    /** @var list<string> */
    private const CUSTOM_TONES = ['blue', 'purple', 'green', 'orange'];

    public function forBuiltIn(string $slug): array
    {
        return self::BUILT_IN[$slug] ?? ['icon' => 'Sparkles', 'tone' => 'blue'];
    }

    public function forCustom(string $slug): array
    {
        // Deterministic assignment: hash the slug to pick icon/tone.
        $hash = crc32($slug);
        $icon = self::CUSTOM_ICONS[abs($hash) % count(self::CUSTOM_ICONS)];
        // Shift the hash to get a different distribution for tone vs icon.
        $tone = self::CUSTOM_TONES[abs((int) ($hash / 7)) % count(self::CUSTOM_TONES)];

        return ['icon' => $icon, 'tone' => $tone];
    }
}
