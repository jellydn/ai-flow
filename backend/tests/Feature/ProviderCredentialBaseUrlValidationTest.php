<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProviderCredentialBaseUrlValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function blockedBaseUrls(): array
    {
        return [
            'localhost' => ['http://localhost:11434/v1'],
            'loopback ipv4' => ['http://127.0.0.1:11434/v1'],
            'private 10/8' => ['http://10.0.0.1/v1'],
            'private 192.168/16' => ['http://192.168.1.1/v1'],
            'link-local metadata' => ['http://169.254.169.254/latest/meta-data'],
            'loopback ipv6' => ['http://[::1]/v1'],
            'non-http scheme' => ['ftp://example.com/v1'],
        ];
    }

    #[DataProvider('blockedBaseUrls')]
    public function test_rejects_unsafe_base_url_on_create(string $baseUrl): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/user/provider-credentials', [
            'provider' => 'openai',
            'label' => 'Key',
            'api_key' => 'sk-test',
            'base_url' => $baseUrl,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['base_url']);
    }

    public function test_accepts_public_https_base_url_on_create(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/user/provider-credentials', [
            'provider' => 'openai',
            'label' => 'Key',
            'api_key' => 'sk-test',
            'base_url' => 'https://api.openai.com/v1',
        ]);

        $response->assertCreated();
    }
}
