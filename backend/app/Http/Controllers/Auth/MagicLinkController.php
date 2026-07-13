<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class MagicLinkController extends Controller
{
    /**
     * Request a magic sign-in link for the given email address.
     */
    public function request(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ]);

        $email = strtolower(trim($request->string('email')->value()));
        $user = User::query()->firstOrCreate(['email' => $email], [
            'email' => $email,
            'name' => null,
        ]);

        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        DB::table('magic_login_tokens')->insert([
            'email' => $email,
            'token' => $hashedToken,
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Mail::to($email)->queue(new MagicLinkMail($rawToken, $request->has('redirect_to') ? $request->string('redirect_to')->value() : null));

        return response()->json([
            'message' => 'If the email address is valid, a sign-in link has been sent.',
        ]);
    }

    /**
     * Verify a magic-link token, authenticate the user, and redirect to the app.
     */
    public function verify(Request $request, string $token): RedirectResponse
    {
        $hashedToken = hash('sha256', $token);

        $record = DB::table('magic_login_tokens')
            ->where('token', $hashedToken)
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'token' => ['This sign-in link is invalid or has expired.'],
            ]);
        }

        if ($record->used_at !== null) {
            throw ValidationException::withMessages([
                'token' => ['This sign-in link has already been used.'],
            ]);
        }

        if (now()->greaterThan($record->expires_at)) {
            throw ValidationException::withMessages([
                'token' => ['This sign-in link has expired.'],
            ]);
        }

        DB::table('magic_login_tokens')
            ->where('id', $record->id)
            ->update(['used_at' => now()]);

        $user = User::query()->where('email', $record->email)->firstOrFail();

        Auth::login($user, true);
        $request->session()->regenerate();

        $user->update(['last_login_at' => now()]);

        return redirect()->intended(
            config('app.frontend_url', '/dashboard')
        );
    }

    /**
     * Sign out the current user.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out successfully.']);
    }
}
