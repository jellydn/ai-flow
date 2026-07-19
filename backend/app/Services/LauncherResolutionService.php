<?php

namespace App\Services;

use App\Data\ResolvedLauncher;
use App\Models\Launcher;
use App\Models\User;
use App\Models\UserLauncher;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LauncherResolutionService
{
    public function __construct(
        private LauncherPromptResolver $promptResolver,
    ) {}

    /**
     * Resolve a launcher by slug — built-in first, then user custom.
     *
     *
     * @throws ModelNotFoundException when no matching launcher is found
     */
    public function resolve(string $slug, ?User $user): ResolvedLauncher
    {
        $builtIn = Launcher::where('slug', $slug)->where('active', true)->first();

        if ($builtIn !== null) {
            return new ResolvedLauncher(
                launcher: $builtIn,
                promptSnapshot: $this->promptResolver->effectivePrompt($builtIn, $user),
                launcherId: $builtIn->id,
                userLauncherId: null,
            );
        }

        $custom = UserLauncher::where('slug', $slug)->where('user_id', $user?->id)->first();

        if ($custom === null) {
            throw (new ModelNotFoundException)->setModel(UserLauncher::class, $slug);
        }

        // launcher_id is NOT NULL (SQLite-compatible). Custom-launcher runs use a
        // placeholder built-in launcher_id so FK constraints stay intact.
        $placeholderId = Launcher::where('active', true)->value('id');

        return new ResolvedLauncher(
            launcher: $custom,
            promptSnapshot: $custom->prompt_template,
            launcherId: $placeholderId,
            userLauncherId: $custom->id,
        );
    }

    /**
     * Check whether a launcher with the given slug exists (built-in or user custom).
     */
    public function exists(string $slug, ?User $user): bool
    {
        if (Launcher::where('slug', $slug)->where('active', true)->exists()) {
            return true;
        }

        return $user !== null
            && UserLauncher::where('slug', $slug)->where('user_id', $user->id)->exists();
    }
}
