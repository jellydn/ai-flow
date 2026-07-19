<?php

namespace App\Services;

class LauncherMetaService
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

    /** @return array{icon: string, tone: string} */
    public function forBuiltIn(string $slug): array
    {
        return self::BUILT_IN[$slug] ?? ['icon' => 'Sparkles', 'tone' => 'blue'];
    }

    /** @return array{icon: string, tone: string} */
    public function forCustom(string $slug): array
    {
        // Deterministic assignment: hash the slug to pick icon and tone.
        // Use the raw hash for icon and a shifted hash (÷ 7) for tone
        // so the two selections are independent — avoids icon/tone pairs
        // that always travel together.
        $hash = crc32($slug);
        $icon = self::CUSTOM_ICONS[abs($hash) % count(self::CUSTOM_ICONS)];
        $tone = self::CUSTOM_TONES[abs((int) ($hash / 7)) % count(self::CUSTOM_TONES)];

        return ['icon' => $icon, 'tone' => $tone];
    }
}
