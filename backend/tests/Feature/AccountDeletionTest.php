<?php

namespace Tests\Feature;

use App\Models\Launcher;
use App\Models\ProviderCredential;
use App\Models\Run;
use App\Models\User;
use App\Security\CredentialCipher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->deleteJson('/api/user/account', ['confirm' => true]);

        $response->assertOk()
            ->assertJsonPath('message', 'Account deleted.');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_account_deletion_requires_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->deleteJson('/api/user/account', ['confirm' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirm');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_account_deletion_requires_confirm_field(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->deleteJson('/api/user/account', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirm');
    }

    public function test_account_deletion_cascades_runs(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $launcherId = Launcher::where('slug', 'explain-repository')->value('id');

        $run = Run::create([
            'launcher_id' => $launcherId,
            'user_id' => $user->id,
            'source_url' => 'https://github.com/a/b',
            'input' => ['source_url' => 'https://github.com/a/b'],
            'progress' => [],
            'status' => 'completed',
        ]);

        $this->actingAs($user)->deleteJson('/api/user/account', ['confirm' => true])
            ->assertOk();

        $this->assertDatabaseMissing('runs', ['id' => $run->id]);
    }

    public function test_account_deletion_cascades_credentials(): void
    {
        $user = User::factory()->create();
        $cipher = new CredentialCipher;
        $credential = ProviderCredential::forceCreate([
            'user_id' => $user->id,
            'provider' => 'openai',
            'label' => 'My key',
            'encrypted_api_key' => $cipher->encrypt('sk-test'),
        ]);

        $this->actingAs($user)->deleteJson('/api/user/account', ['confirm' => true])
            ->assertOk();

        $this->assertDatabaseMissing('provider_credentials', ['id' => $credential->id]);
    }

    public function test_account_deletion_does_not_affect_anonymous_runs(): void
    {
        $this->seed();
        $launcherId = Launcher::where('slug', 'explain-repository')->value('id');

        $anonymousRun = Run::create([
            'launcher_id' => $launcherId,
            'user_id' => null,
            'source_url' => 'https://github.com/a/b',
            'input' => ['source_url' => 'https://github.com/a/b'],
            'progress' => [],
            'status' => 'completed',
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)->deleteJson('/api/user/account', ['confirm' => true])
            ->assertOk();

        $this->assertDatabaseHas('runs', ['id' => $anonymousRun->id]);
    }

    public function test_unauthenticated_user_cannot_delete_account(): void
    {
        $this->deleteJson('/api/user/account', ['confirm' => true])
            ->assertUnauthorized();
    }

    public function test_account_deletion_logs_out_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->deleteJson('/api/user/account', ['confirm' => true])
            ->assertOk();

        // Subsequent authenticated request should fail.
        $this->getJson('/api/user')->assertUnauthorized();
    }
}
