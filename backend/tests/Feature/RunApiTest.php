<?php

namespace Tests\Feature;

use App\Jobs\ExecuteLauncherJob;
use App\Models\Launcher;
use App\Models\Run;
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
        $this->getJson('/api/flows')
            ->assertOk()
            ->assertJsonCount(4)
            ->assertJsonPath('0.id', 'review-pr');
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

    public function test_execution_alias_uses_the_run_contract(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/executions', [
            'launcher' => 'laravel-doctor',
            'source_url' => 'https://github.com/laravel/framework',
        ])->assertStatus(202)->assertJsonPath('status', 'queued');

        $this->getJson('/api/executions/'.$response->json('id'))
            ->assertOk()
            ->assertJsonPath('data.launcher', 'laravel-doctor');
    }

    public function test_execution_accepts_byop_contract_without_persisting_or_returning_key(): void
    {
        Queue::fake();
        $apiKey = 'sk-user-secret-value';

        $response = $this->postJson('/api/executions', [
            'flow_id' => 'laravel-doctor',
            'input' => ['url' => 'https://github.com/laravel/laravel'],
            'provider' => ['id' => 'openai', 'api_key' => $apiKey],
        ])->assertStatus(202);

        $run = Run::findOrFail($response->json('id'));
        $this->assertSame(['source_url' => 'https://github.com/laravel/laravel'], $run->input);
        $this->assertStringNotContainsString($apiKey, json_encode($run->getAttributes(), JSON_THROW_ON_ERROR));
        $this->getJson('/api/executions/'.$run->id)->assertJsonMissing(['api_key' => $apiKey]);
        Queue::assertPushed(ExecuteLauncherJob::class);
    }

    public function test_execution_rejects_unsupported_provider(): void
    {
        Queue::fake();

        $this->postJson('/api/executions', [
            'flow_id' => 'laravel-doctor',
            'input' => ['url' => 'https://github.com/laravel/laravel'],
            'provider' => ['id' => 'anthropic', 'api_key' => 'secret'],
        ])->assertUnprocessable()->assertJsonValidationErrors('provider.id');
    }

    public function test_run_passes_null_provider_when_provider_id_omitted(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
            'provider' => ['id' => null],
        ])->assertStatus(202);

        Queue::assertPushed(ExecuteLauncherJob::class, function (ExecuteLauncherJob $job) use ($response): bool {
            if ($job->runId !== $response->json('id')) {
                return false;
            }
            $provider = (new \ReflectionProperty(ExecuteLauncherJob::class, 'provider'));
            $provider->setAccessible(true);

            return $provider->getValue($job) === null;
        });
    }

    public function test_stream_emits_terminal_snapshot_without_buffering(): void
    {
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'laravel-doctor')->value('id'),
            'source_url' => 'https://github.com/laravel/framework',
            'input' => ['source_url' => 'https://github.com/laravel/framework'],
            'status' => 'completed',
            'progress' => ['Preparing report'],
            'result' => ['summary' => 'Ready'],
            'completed_at' => now(),
        ]);

        $response = $this->get('/api/runs/'.$run->id.'/stream');

        $response->assertOk()->assertHeader('X-Accel-Buffering', 'no');
        $this->assertStringContainsString('event: completed', $response->streamedContent());
        $this->assertStringContainsString('"status":"completed"', $response->streamedContent());
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
