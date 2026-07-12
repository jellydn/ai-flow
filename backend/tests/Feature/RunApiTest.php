<?php

namespace Tests\Feature;

use App\Jobs\ExecuteLauncherJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RunApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_lists_seeded_launchers_and_health(): void
    {
        $this->getJson('/api/health')->assertOk()->assertExactJson(['status' => 'ok']);
        $this->getJson('/api/launchers')
            ->assertOk()
            ->assertJsonCount(4)
            ->assertJsonPath('0.id', 'review-pr')
            ->assertJsonMissingPath('0.class_name');
    }

    public function test_run_is_validated_created_and_queued(): void
    {
        Queue::fake();
        $r = $this->postJson('/api/runs', ['launcher' => 'explain-repository', 'source_url' => 'https://github.com/laravel/framework']);
        $r->assertStatus(202)
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('message', 'Workflow started');
        $this->assertDatabaseHas('runs', ['id' => $r->json('id')]);
        Queue::assertPushed(ExecuteLauncherJob::class);

        $this->getJson('/api/runs/'.$r->json('id'))
            ->assertOk()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.progress', [])
            ->assertJsonPath('data.result', null);
    }

    public function test_rejects_invalid_url_and_unknown_launcher(): void
    {
        $this->postJson('/api/runs', ['launcher' => 'missing', 'source_url' => 'https://example.com/x'])->assertUnprocessable()->assertJsonValidationErrors(['launcher', 'source_url']);
    }

    public function test_post_runs_is_rate_limited(): void
    {
        Queue::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/runs', ['launcher' => 'explain-repository', 'source_url' => 'https://github.com/a/b'])->assertStatus(202);
        }
        $this->postJson('/api/runs', ['launcher' => 'explain-repository', 'source_url' => 'https://github.com/a/b'])->assertStatus(429);
    }
}
