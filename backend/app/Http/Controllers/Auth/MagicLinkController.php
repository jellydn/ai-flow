<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MagicLinkController extends Controller
{
    /**
     * Request a magic sign-in link for the given email address.
     */
    public function request(Request $request): void
    {
        // TODO: Phase 1 implementation
    }

    /**
     * Verify a magic-link token, authenticate the user, and redirect to the app.
     */
    public function verify(string $token): void
    {
        // TODO: Phase 1 implementation
    }

    /**
     * Sign out the current user.
     */
    public function logout(Request $request): void
    {
        // TODO: Phase 1 implementation
    }
}
