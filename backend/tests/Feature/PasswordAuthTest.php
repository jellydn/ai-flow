<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_authenticates(): void
    {
        $this->postJson('/auth/register', [
            'email' => 'new@example.com',
            'password' => 'SecretPass1!',
            'password_confirmation' => 'SecretPass1!',
            'name' => 'New User',
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'name' => 'New User']);
        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
    }

    public function test_register_rejects_magic_link_only_user_email(): void
    {
        User::factory()->create([
            'email' => 'magic@example.com',
            'password' => null,
        ]);

        $this->postJson('/auth/register', [
            'email' => 'magic@example.com',
            'password' => 'SecretPass1!',
            'password_confirmation' => 'SecretPass1!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_rejects_existing_password_user(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/auth/register', [
            'email' => 'taken@example.com',
            'password' => 'SecretPass1!',
            'password_confirmation' => 'SecretPass1!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('SecretPass1!'),
        ]);

        $this->postJson('/auth/login', [
            'email' => 'login@example.com',
            'password' => 'SecretPass1!',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'login@example.com');

        $this->assertAuthenticated();
    }

    public function test_login_fails_for_magic_link_only_user(): void
    {
        User::factory()->create([
            'email' => 'nomagic@example.com',
            'password' => null,
        ]);

        $this->postJson('/auth/login', [
            'email' => 'nomagic@example.com',
            'password' => 'anything',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('SecretPass1!'),
        ]);

        $this->postJson('/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_logout_after_password_login_clears_session(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('SecretPass1!'),
        ]);

        $this->postJson('/auth/login', [
            'email' => 'login@example.com',
            'password' => 'SecretPass1!',
        ])->assertOk();

        $this->actingAs($user)
            ->postJson('/auth/logout')
            ->assertOk();

        $this->getJson('/api/user')->assertUnauthorized();
    }
}
