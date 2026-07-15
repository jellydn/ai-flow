<?php

namespace App\Services;

use App\Models\Launcher;
use App\Models\LauncherPromptOverride;
use App\Models\User;

class LauncherPromptResolver
{
    public function effectivePrompt(Launcher $launcher, ?User $user): string
    {
        if ($user === null) {
            return $launcher->prompt_template;
        }

        $override = LauncherPromptOverride::query()
            ->where('user_id', $user->id)
            ->where('launcher_id', $launcher->id)
            ->value('prompt_template');

        return $override ?? $launcher->prompt_template;
    }
}
