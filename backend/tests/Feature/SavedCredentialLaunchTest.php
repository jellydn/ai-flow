<?php

namespace Tests\Feature;

use App\Jobs\ExecuteLauncherJob;
use App\Models\ProviderCredential;
use App\Models\User;
use App\Security\CredentialCipher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SavedCredentialLaunchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authenticated_user_can_launch_with_saved_credential(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $cipher = new CredentialCipher;
        $credential = ProviderCredential::forceCreate([
            'user_id' => $user->id,
            'provider' => 'openai',
            'label' => 'Personal',
            'encrypted_api_key' => $cipher->encrypt('sk-test-key'),
        ]);

        $response = $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
            'provider_credential_id' => $credential->id,
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('status', 'queued');

        // Verify the job was dispatched with the credential ID.
        Queue::assertPushed(ExecuteLauncherJob::class, function ($job) use ($credential) {
            $reflection = new \ReflectionProperty(ExecuteLauncherJob::class, 'providerCredentialId');
            $reflection->setAccessible(true);

            return $reflection->getValue($job) === $credential->id;
        });
    }

    public function test_run_record_snapshots_provider_from_credential(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $cipher = new CredentialCipher;
        $credential = ProviderCredential::forceCreate([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'label' => 'Anthropic key',
            'encrypted_api_key' => $cipher->encrypt('sk-ant-test'),
        ]);

        $response = $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
            'provider_credential_id' => $credential->id,
        ]);

        $response->assertStatus(202);
        $this->assertDatabaseHas('runs', [
            'id' => $response->json('id'),
            'user_id' => $user->id,
            'provider_credential_id' => $credential->id,
            'provider' => 'anthropic',
        ]);
    }

    public function test_credential_must_belong_to_authenticated_user(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $cipher = new CredentialCipher;
        $credential = ProviderCredential::forceCreate([
            'user_id' => $otherUser->id,
            'provider' => 'openai',
            'label' => 'Other user key',
            'encrypted_api_key' => $cipher->encrypt('sk-other-key'),
        ]);

        $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
            'provider_credential_id' => $credential->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('provider_credential_id');
    }

    public function test_anonymous_user_cannot_use_saved_credential(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $cipher = new CredentialCipher;
        $credential = ProviderCredential::forceCreate([
            'user_id' => $user->id,
            'provider' => 'openai',
            'label' => 'My key',
            'encrypted_api_key' => $cipher->encrypt('sk-test'),
        ]);

        $this->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
            'provider_credential_id' => $credential->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('provider_credential_id');
    }

    public function test_launch_rejects_saved_credential_with_one_time_key(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $cipher = new CredentialCipher;
        $credential = ProviderCredential::forceCreate([
            'user_id' => $user->id,
            'provider' => 'openai',
            'label' => 'My key',
            'encrypted_api_key' => $cipher->encrypt('sk-saved'),
        ]);

        $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
            'provider_credential_id' => $credential->id,
            'provider' => ['api_key' => 'sk-one-time'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('provider.api_key');

        Queue::assertNothingPushed();
    }

    public function test_launch_without_credential_still_works(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
        ])->assertStatus(202);

        Queue::assertPushed(ExecuteLauncherJob::class);
    }
}
