<?php

namespace Database\Seeders;

use App\Mail\SuperAdminBootstrapMail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SuperAdminBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('super_admin.bootstrap_email');

        if (! is_string($email) || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            if (! $existing->is_super_admin) {
                $existing->forceFill(['is_super_admin' => true])->save();
            }

            return;
        }

        $plainPassword = Str::password(16);

        $user = User::query()->create([
            'name' => (string) config('super_admin.bootstrap_name'),
            'email' => $email,
            'password' => $plainPassword,
            'email_verified_at' => now(),
            'is_super_admin' => true,
        ]);

        $adminUrl = rtrim((string) config('app.url'), '/').'/admin';

        try {
            Mail::to($user->email)->send(new SuperAdminBootstrapMail($user, $plainPassword, $adminUrl));
        } catch (\Throwable $e) {
            Log::warning('Super admin bootstrap mail failed', [
                'email' => $email,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
