<?php

namespace Tests\Feature;

use App\Models\ProviderCredential;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Security\CredentialCipher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderCredentialApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CredentialCipher $cipher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->cipher = new CredentialCipher;
    }

    /**
     * Create a ProviderCredential for the test user without mass-assignment.
     */
    private function createCredential(string $provider = 'openai', string $label = 'Test Key', string $apiKey = 'sk-test'): ProviderCredential
    {
        $credential = new ProviderCredential;
        $credential->user_id = $this->user->id;
        $credential->provider = $provider;
        $credential->label = $label;
        $credential->encrypted_api_key = $this->cipher->encrypt($apiKey);
        $credential->save();

        return $credential;
    }

    public function test_can_create_credential(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/user/provider-credentials', [
            'provider' => 'openai',
            'label' => 'My OpenAI Key',
            'api_key' => 'sk-test-secret',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.provider', 'openai')
            ->assertJsonPath('data.label', 'My OpenAI Key')
            ->assertJsonPath('data.masked_key', 'sk-t...cret')
            ->assertJsonMissing(['encrypted_api_key'])
            ->assertJsonMissing(['api_key']);
    }

    public function test_cannot_see_other_users_credentials(): void
    {
        $otherUser = User::factory()->create();
        $credential = new ProviderCredential;
        $credential->user_id = $otherUser->id;
        $credential->provider = 'openai';
        $credential->label = 'Other User Key';
        $credential->encrypted_api_key = $this->cipher->encrypt('sk-other-secret');
        $credential->save();

        $this->actingAs($this->user)
            ->getJson('/api/user/provider-credentials')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($this->user)
            ->patchJson('/api/user/provider-credentials/'.$credential->id, ['label' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_can_list_own_credentials(): void
    {
        $this->createCredential('openai', 'Personal Key', 'sk-personal');

        $this->actingAs($this->user)
            ->getJson('/api/user/provider-credentials')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Personal Key');
    }

    public function test_can_update_credential(): void
    {
        $credential = $this->createCredential('openai', 'Old Label', 'sk-old-key');

        $this->actingAs($this->user)
            ->patchJson('/api/user/provider-credentials/'.$credential->id, [
                'label' => 'New Label',
            ])
            ->assertOk()
            ->assertJsonPath('data.label', 'New Label');
    }

    public function test_can_replace_api_key(): void
    {
        $credential = $this->createCredential('openai', 'Test Key', 'sk-old-key');

        $response = $this->actingAs($this->user)
            ->patchJson('/api/user/provider-credentials/'.$credential->id, [
                'api_key' => 'sk-new-key-12345',
            ])
            ->assertOk();

        $updated = $credential->fresh();
        $this->assertSame('sk-new-key-12345', $this->cipher->decrypt($updated->encrypted_api_key));
    }

    public function test_can_delete_credential(): void
    {
        $credential = $this->createCredential('openai', 'Delete Me', 'sk-delete');

        $this->actingAs($this->user)
            ->deleteJson('/api/user/provider-credentials/'.$credential->id)
            ->assertOk();

        $this->assertDatabaseMissing('provider_credentials', ['id' => $credential->id]);
    }

    public function test_only_one_default_credential_per_user(): void
    {
        $first = $this->createCredential('openai', 'First Key', 'sk-first');
        $first->is_default = true;
        $first->save();

        $second = $this->createCredential('openai', 'Second Key', 'sk-second');
        $second->is_default = true;
        $second->save();

        $this->assertFalse($first->fresh()->is_default, 'First credential should no longer be default.');
        $this->assertTrue($second->fresh()->is_default, 'Second credential should now be default.');
    }

    public function test_credential_cascade_deletes_with_user(): void
    {
        $this->createCredential();

        $this->user->delete();

        $this->assertDatabaseMissing('provider_credentials', ['user_id' => $this->user->id]);
    }

    public function test_credentials_require_authentication(): void
    {
        $this->getJson('/api/user/provider-credentials')->assertUnauthorized();
        $this->postJson('/api/user/provider-credentials', [
            'provider' => 'openai',
            'label' => 'Test',
            'api_key' => 'sk-test',
        ])->assertUnauthorized();
    }

    public function test_create_credential_validates_required_fields(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/user/provider-credentials', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['provider', 'label', 'api_key']);
    }

    public function test_credential_verify_is_rate_limited(): void
    {
        Http::fake();

        $credential = $this->createCredential();

        // The limiter allows CREDENTIAL_VERIFY_PER_MINUTE requests per minute per user.
        $limit = AppServiceProvider::CREDENTIAL_VERIFY_PER_MINUTE;
        for ($i = 0; $i < $limit; $i++) {
            $this->actingAs($this->user)
                ->postJson("/api/user/provider-credentials/{$credential->id}/verify")
                ->assertStatus(200);
        }

        // The provider should have been contacted exactly $limit times.
        Http::assertSentCount($limit);

        // The next request should be rate limited (not sent to the provider).
        $this->actingAs($this->user)
            ->postJson("/api/user/provider-credentials/{$credential->id}/verify")
            ->assertStatus(429);
    }
}
