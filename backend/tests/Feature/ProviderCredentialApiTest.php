<?php

namespace Tests\Feature;

use App\Models\ProviderCredential;
use App\Models\User;
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
        $credential = new ProviderCredential;
        $credential->user_id = $this->user->id;
        $credential->provider = 'openai';
        $credential->label = 'Personal Key';
        $credential->encrypted_api_key = $this->cipher->encrypt('sk-personal');
        $credential->save();

        $this->actingAs($this->user)
            ->getJson('/api/user/provider-credentials')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Personal Key');
    }

    public function test_can_update_credential(): void
    {
        $credential = new ProviderCredential;
        $credential->user_id = $this->user->id;
        $credential->provider = 'openai';
        $credential->label = 'Old Label';
        $credential->encrypted_api_key = $this->cipher->encrypt('sk-old-key');
        $credential->save();

        $this->actingAs($this->user)
            ->patchJson('/api/user/provider-credentials/'.$credential->id, [
                'label' => 'New Label',
            ])
            ->assertOk()
            ->assertJsonPath('data.label', 'New Label');
    }

    public function test_can_replace_api_key(): void
    {
        $credential = new ProviderCredential;
        $credential->user_id = $this->user->id;
        $credential->provider = 'openai';
        $credential->label = 'Test Key';
        $credential->encrypted_api_key = $this->cipher->encrypt('sk-old-key');
        $credential->save();

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
        $credential = new ProviderCredential;
        $credential->user_id = $this->user->id;
        $credential->provider = 'openai';
        $credential->label = 'Delete Me';
        $credential->encrypted_api_key = $this->cipher->encrypt('sk-delete');
        $credential->save();

        $this->actingAs($this->user)
            ->deleteJson('/api/user/provider-credentials/'.$credential->id)
            ->assertOk();

        $this->assertDatabaseMissing('provider_credentials', ['id' => $credential->id]);
    }

    public function test_only_one_default_credential_per_user(): void
    {
        $first = new ProviderCredential;
        $first->user_id = $this->user->id;
        $first->provider = 'openai';
        $first->label = 'First Key';
        $first->encrypted_api_key = $this->cipher->encrypt('sk-first');
        $first->is_default = true;
        $first->save();

        $second = new ProviderCredential;
        $second->user_id = $this->user->id;
        $second->provider = 'openai';
        $second->label = 'Second Key';
        $second->encrypted_api_key = $this->cipher->encrypt('sk-second');
        $second->is_default = true;
        $second->save();

        $this->assertFalse($first->fresh()->is_default, 'First credential should no longer be default.');
        $this->assertTrue($second->fresh()->is_default, 'Second credential should now be default.');
    }

    public function test_credential_cascade_deletes_with_user(): void
    {
        $credential = new ProviderCredential;
        $credential->user_id = $this->user->id;
        $credential->provider = 'openai';
        $credential->label = 'Test Key';
        $credential->encrypted_api_key = $this->cipher->encrypt('sk-test');
        $credential->save();

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

        $credential = new ProviderCredential;
        $credential->user_id = $this->user->id;
        $credential->provider = 'openai';
        $credential->label = 'Test Key';
        $credential->encrypted_api_key = $this->cipher->encrypt('sk-test');
        $credential->save();

        // The limiter allows 10 requests per minute per user.
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->user)
                ->postJson("/api/user/provider-credentials/{$credential->id}/verify")
                ->assertStatus(200);
        }

        // The 11th request should be rate limited.
        $this->actingAs($this->user)
            ->postJson("/api/user/provider-credentials/{$credential->id}/verify")
            ->assertStatus(429);
    }
}
