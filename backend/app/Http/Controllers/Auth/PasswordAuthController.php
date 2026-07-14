<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordAuthController extends Controller
{
    /**
     * Create an account or set a password on an existing magic-link-only user.
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

        if ($existing !== null && $existing->password !== null) {
            throw ValidationException::withMessages([
                'email' => ['An account with this email already exists.'],
            ]);
        }

        $user = $existing ?? new User(['email' => $email]);
        $user->fill([
            'name' => $validated['name'] ?? $user->name,
            'password' => $validated['password'],
        ]);
        $user->save();

        Auth::login($user, true);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

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

        if ($user === null || $user->password === null || ! Auth::validate([
            'email' => $user->email,
            'password' => $credentials['password'],
        ])) {
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
