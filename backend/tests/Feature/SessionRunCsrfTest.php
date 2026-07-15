<?php

namespace Tests\Feature;

use App\Models\ProviderCredential;
use App\Models\User;
use App\Security\CredentialCipher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Session-backed run create + read (SPA cookie auth).
 */
class SessionRunCsrfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_signed_in_user_can_create_and_read_owned_run_with_saved_credential(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $cipher = new CredentialCipher;
        $credential = ProviderCredential::forceCreate([
            'user_id' => $user->id,
            'provider' => 'openrouter',
            'label' => 'OR',
            'encrypted_api_key' => $cipher->encrypt('sk-test'),
        ]);

        $this->actingAs($user);

        $create = $this->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/jellydn/pi-clinepass-provider',
            'provider' => ['id' => 'openrouter'],
            'provider_credential_id' => $credential->id,
        ]);

        $create->assertStatus(202);
        $runId = $create->json('id');

        // Owner must be able to load the private run (session user present).
        $this->actingAs($user)
            ->getJson('/api/runs/'.$runId)
            ->assertOk()
            ->assertJsonPath('data.id', $runId);

        // Guest still cannot.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/runs/'.$runId)->assertForbidden();
    }
}
