<?php

namespace App\Http\Controllers;

use App\Models\ProviderCredential;
use App\Models\Run;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    /**
     * Delete the authenticated user's account and all associated data.
     *
     * Cascades: provider credentials (via FK cascadeOnDelete), runs owned
     * by the user, and the user record itself. This action is irreversible.
     *
     * The request must be confirmed with a 'confirm: true' field to prevent
     * accidental deletion via CSRF or other unintended POST.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'confirm' => ['required', 'boolean', 'accepted'],
        ]);

        $user = $request->user();
        $userId = $user->id;

        // Log out and invalidate the session BEFORE deleting the user.
        // This prevents the SessionGuard from re-querying or updating the
        // user record (e.g. cycleRememberToken) after the row is gone.
        Auth::logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Delete the user's runs (anonymous runs with user_id = null are not affected).
        Run::where('user_id', $userId)->delete();

        // Provider credentials cascade-delete via FK constraint, but delete
        // explicitly here for clarity and in case the FK is not enforced.
        ProviderCredential::where('user_id', $userId)->delete();

        // Delete the user record. The session was already invalidated above,
        // so the SessionGuard will not attempt to re-query or update this row.
        $user->delete();

        return response()->json(['message' => 'Account deleted.'], 200);
    }
}
