<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordAuthController extends Controller
{
    /** Bcrypt hash used only to normalize login timing when the user is missing or has no password. */
    private const DUMMY_PASSWORD_HASH = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    /**
     * Create a new account. Existing emails (including magic-link-only) must sign in via magic link first.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $email = strtolower(trim($validated['email']));
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'email' => ['An account with this email already exists.'],
            ]);
        }

        $user = new User(['email' => $email]);
        $user->fill([
            'name' => $validated['name'] ?? null,
            'password' => $validated['password'],
            'last_login_at' => now(),
        ]);
        $user->save();

        Auth::login($user, true);
        $request->session()->regenerate();

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Sign in with email and password.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($credentials['email']));
        $user = User::query()->where('email', $email)->first();

        $passwordValid = $user !== null
            && $user->password !== null
            && Auth::validate([
                'email' => $user->email,
                'password' => $credentials['password'],
            ]);

        if (! $passwordValid) {
            Hash::check($credentials['password'], self::DUMMY_PASSWORD_HASH);

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }
}
