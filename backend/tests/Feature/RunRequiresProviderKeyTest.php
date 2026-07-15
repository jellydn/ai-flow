<?php

namespace Tests\Feature;

use App\Jobs\ExecuteLauncherJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RunRequiresProviderKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config()->set('services.openai.key', null);
        config()->set('services.openai.openrouter_key', null);
        config()->set('services.anthropic.key', null);
        config()->set('services.gemini.key', null);
    }

    public function test_post_runs_rejects_when_no_server_or_byok_key(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['provider.api_key']);

        Queue::assertNothingPushed();
    }

    public function test_post_runs_accepts_one_time_key_without_server_config(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
            'provider' => ['id' => 'openai', 'api_key' => 'sk-test-one-time-key'],
        ]);

        $response->assertStatus(202);
        Queue::assertPushed(ExecuteLauncherJob::class);
    }

    public function test_authenticated_user_without_key_gets_validation_error(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/runs', [
                'launcher' => 'explain-repository',
                'source_url' => 'https://github.com/laravel/framework',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['provider.api_key']);

        Queue::assertNothingPushed();
    }
}
