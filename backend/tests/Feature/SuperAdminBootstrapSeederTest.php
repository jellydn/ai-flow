<?php

namespace Tests\Feature;

use App\Mail\SuperAdminBootstrapMail;
use App\Models\User;
use Database\Seeders\SuperAdminBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SuperAdminBootstrapSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_super_admin_and_emails_password_when_configured(): void
    {
        Mail::fake();

        config([
            'super_admin.bootstrap_email' => 'dung@productsway.com',
            'super_admin.bootstrap_name' => 'Dung',
            'app.url' => 'https://ai-flow.example',
        ]);

        $this->seed(SuperAdminBootstrapSeeder::class);

        $user = User::query()->where('email', 'dung@productsway.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_super_admin);
        $this->assertNotNull($user->email_verified_at);

        $plainPassword = null;

        Mail::assertSent(SuperAdminBootstrapMail::class, function (SuperAdminBootstrapMail $mail) use ($user, &$plainPassword): bool {
            $plainPassword = $mail->plainPassword;

            return $mail->hasTo($user->email)
                && $mail->plainPassword !== ''
                && str_contains($mail->adminUrl, '/admin');
        });

        $this->assertNotNull($plainPassword);
        $this->assertTrue(Hash::check($plainPassword, $user->fresh()->password));
    }

    public function test_promotes_existing_user_without_emailing_password(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'dung@productsway.com',
            'is_super_admin' => false,
        ]);

        config(['super_admin.bootstrap_email' => 'dung@productsway.com']);

        $this->seed(SuperAdminBootstrapSeeder::class);

        $this->assertTrue($user->fresh()->is_super_admin);
        Mail::assertNothingSent();
    }

    public function test_skips_when_bootstrap_email_unset(): void
    {
        Mail::fake();

        config(['super_admin.bootstrap_email' => null]);

        $this->seed(SuperAdminBootstrapSeeder::class);

        $this->assertSame(0, User::query()->count());
        Mail::assertNothingSent();
    }
}
