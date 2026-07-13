<?php

namespace App\Policies;

use App\Models\ProviderCredential;
use App\Models\User;

class ProviderCredentialPolicy
{
    /**
     * Determine whether the user can view, update, or delete the credential.
     */
    public function manage(User $user, ProviderCredential $credential): bool
    {
        return $user->id === $credential->user_id;
    }
}
