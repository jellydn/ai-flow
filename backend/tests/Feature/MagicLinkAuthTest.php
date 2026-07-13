<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MagicLinkAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_magic_link_creates_user_and_stores_token(): void
    {
        Mail::fake();

        $this->postJson('/auth/magic-link', ['email' => 'test@example.com'])
            ->assertOk()
            ->assertJsonFragment(['message' => 'If the email address is valid, a sign-in link has been sent.']);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $this->assertDatabaseHas('magic_login_tokens', ['email' => 'test@example.com']);
    }

    public function test_request_magic_link_returns_generic_response_for_existing_user(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'test@example.com']);

        $this->postJson('/auth/magic-link', ['email' => 'test@example.com'])
            ->assertOk()
            ->assertJsonFragment(['message' => 'If the email address is valid, a sign-in link has been sent.']);
    }

    public function test_request_magic_link_returns_generic_response_for_unknown_email(): void
    {
        Mail::fake();

        $this->postJson('/auth/magic-link', ['email' => 'unknown@example.com'])
            ->assertOk()
            ->assertJsonFragment(['message' => 'If the email address is valid, a sign-in link has been sent.']);

        $this->assertDatabaseHas('users', ['email' => 'unknown@example.com']);
    }

    public function test_verify_valid_token_authenticates_user(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        DB::table('magic_login_tokens')->insert([
            'email' => $user->email,
            'token' => $hashedToken,
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/auth/magic-link/'.$rawToken)
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertNotNull(DB::table('magic_login_tokens')->where('token', $hashedToken)->value('used_at'));
    }

    public function test_expired_token_is_rejected(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        DB::table('magic_login_tokens')->insert([
            'email' => 'test@example.com',
            'token' => $hashedToken,
            'expires_at' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/auth/magic-link/'.$rawToken)
            ->assertInvalid('token');
    }

    public function test_reused_token_is_rejected(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        DB::table('magic_login_tokens')->insert([
            'email' => 'test@example.com',
            'token' => $hashedToken,
            'expires_at' => now()->addMinutes(15),
            'used_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::factory()->create(['email' => 'test@example.com']);

        $this->get('/auth/magic-link/'.$rawToken)
            ->assertInvalid('token');
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->get('/auth/magic-link/does-not-exist')
            ->assertInvalid('token');
    }

    public function test_logout_clears_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/auth/logout')
            ->assertOk()
            ->assertJsonFragment(['message' => 'Signed out successfully.']);

        $this->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_auth_guard_rejects_unauthenticated_access(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_magic_link_request_is_rate_limited(): void
    {
        Mail::fake();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/auth/magic-link', ['email' => 'test@example.com'])
                ->assertOk();
        }

        $this->postJson('/auth/magic-link', ['email' => 'test@example.com'])
            ->assertStatus(429);
    }
}
