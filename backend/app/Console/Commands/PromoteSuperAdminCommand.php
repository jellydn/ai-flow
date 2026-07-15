<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:promote-super-admin {email : The user email address}')]
#[Description('Grant super admin access to the Filament panel at /admin')]
class PromoteSuperAdminCommand extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        if ($user->is_super_admin) {
            $this->info("{$email} is already a super admin.");

            return self::SUCCESS;
        }

        $user->forceFill(['is_super_admin' => true])->save();
        $this->info("Promoted {$email} to super admin. They can sign in at /admin.");

        return self::SUCCESS;
    }
}
