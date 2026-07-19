<?php

namespace App\Policies;

use App\Models\Run;
use App\Models\User;

class RunPolicy
{
    /**
     * Public runs (is_public = true) are viewable by anyone.
     * Private runs are viewable only by their owner.
     */
    public function view(?User $user, Run $run): bool
    {
        if ($run->isPublic()) {
            return true;
        }

        return $user !== null && $run->isOwnedBy($user);
    }

    /**
     * Only the run owner can retry.
     */
    public function retry(User $user, Run $run): bool
    {
        return $run->isOwnedBy($user);
    }

    /**
     * Only the run owner can delete.
     */
    public function delete(User $user, Run $run): bool
    {
        return $run->isOwnedBy($user);
    }
}
