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

class MagicLinkController extends Controller
{
    private const TOKEN_HASH_ALGO = 'sha256';

    private const TOKEN_BYTE_LENGTH = 32;

    private const TOKEN_EXPIRY_MINUTES = 15;

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

        $rawToken = bin2hex(random_bytes(self::TOKEN_BYTE_LENGTH));

        DB::table('magic_login_tokens')->insert([
            'email' => $email,
            'token' => $this->hashToken($rawToken),
            'expires_at' => now()->addMinutes(self::TOKEN_EXPIRY_MINUTES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Mail::to($email)->queue(new MagicLinkMail(
            $rawToken,
            self::TOKEN_EXPIRY_MINUTES,
            $request->has('redirect_to') ? $request->string('redirect_to')->value() : null
        ));

        return response()->json([
            'message' => 'If the email address is valid, a sign-in link has been sent.',
        ]);
    }

    /**
     * Verify a magic-link token, authenticate the user, and redirect to the app.
     */
    public function verify(Request $request, string $token): RedirectResponse
    {
        $record = DB::table('magic_login_tokens')
            ->where('token', $this->hashToken($token))
            ->first();

        if (! $record) {
            return redirect()->away($this->errorRedirect('invalid'));
        }

        if ($record->used_at !== null) {
            return redirect()->away($this->errorRedirect('used'));
        }

        if (now()->greaterThan($record->expires_at)) {
            return redirect()->away($this->errorRedirect('expired'));
        }

        DB::table('magic_login_tokens')
            ->where('id', $record->id)
            ->update(['used_at' => now()]);

        $user = User::query()->where('email', $record->email)->firstOrFail();

        Auth::login($user, true);
        $request->session()->regenerate();

        $user->update(['last_login_at' => now()]);

        $frontend = rtrim((string) config('app.frontend_url', ''), '/');

        return redirect()->intended(
            $frontend !== '' ? $frontend.'/user' : '/user'
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

    /**
     * Hash a raw token for database storage and lookup.
     */
    private function hashToken(string $rawToken): string
    {
        return hash(self::TOKEN_HASH_ALGO, $rawToken);
    }

    /**
     * Build a redirect URL with an error query parameter the SPA can read.
     */
    private function errorRedirect(string $error): string
    {
        return (config('app.frontend_url') ?: '/').'?auth_error='.urlencode($error);
    }
}
