<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserLauncher;

class UserLauncherPolicy
{
    /**
     * Only the owner can view their custom launchers.
     */
    public function view(User $user, UserLauncher $userLauncher): bool
    {
        return $userLauncher->user_id === $user->id;
    }

    /**
     * Only the owner can update their custom launchers.
     */
    public function update(User $user, UserLauncher $userLauncher): bool
    {
        return $userLauncher->user_id === $user->id;
    }

    /**
     * Only the owner can delete their custom launchers.
     */
    public function delete(User $user, UserLauncher $userLauncher): bool
    {
        return $userLauncher->user_id === $user->id;
    }
}
