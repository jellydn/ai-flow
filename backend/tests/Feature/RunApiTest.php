<?php

namespace Tests\Feature;

use App\Jobs\ExecuteLauncherJob;
use App\Models\Launcher;
use App\Models\Run;
use App\Models\User;
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

    public function test_authenticated_run_rejects_invalid_custom_model_name(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
            'provider' => ['id' => 'openai', 'model' => 'invalid model!'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider.model');

        Queue::assertNothingPushed();
    }

    public function test_run_persists_requested_model(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
            'provider' => ['id' => 'openai', 'model' => 'gpt-4o'],
        ])->assertStatus(202);

        $this->assertDatabaseHas('runs', [
            'id' => $response->json('id'),
            'provider' => 'openai',
            'model' => 'gpt-4o',
        ]);
    }

    public function test_authenticated_run_accepts_custom_model_name(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
            'provider' => ['id' => 'openrouter', 'model' => 'deepseek/deepseek-r1:free'],
        ])->assertStatus(202);

        $this->assertDatabaseHas('runs', [
            'id' => $response->json('id'),
            'provider' => 'openrouter',
            'model' => 'deepseek/deepseek-r1:free',
            'user_id' => $user->id,
        ]);
    }

    public function test_anonymous_run_is_forced_to_openrouter_free(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
            'provider' => ['id' => 'openai', 'model' => 'gpt-4o'],
        ])->assertStatus(202);

        $this->assertDatabaseHas('runs', [
            'id' => $response->json('id'),
            'provider' => 'openrouter',
            'model' => 'openrouter/free',
            'user_id' => null,
        ]);
    }

    public function test_run_is_validated_created_and_queued(): void
    {
        Queue::fake();
        $r = $this->postJson('/api/runs', ['launcher' => 'explain-repository', 'source_url' => 'https://github.com/laravel/framework']);
        $r->assertStatus(202)
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('message', 'Workflow started');
        $this->assertDatabaseHas('runs', ['id' => $r->json('id')]);
        $run = Run::find($r->json('id'));
        $this->assertNotNull($run->model);
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
        $user = User::factory()->create();
        $apiKey = 'sk-user-secret-value';

        $response = $this->actingAs($user)->postJson('/api/executions', [
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

    public function test_anonymous_execution_ignores_unsupported_provider(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/executions', [
            'flow_id' => 'laravel-doctor',
            'input' => ['url' => 'https://github.com/laravel/laravel'],
            'provider' => ['id' => 'groq', 'api_key' => 'secret'],
        ])->assertStatus(202);

        $this->assertDatabaseHas('runs', [
            'id' => $response->json('id'),
            'provider' => 'openrouter',
            'model' => 'openrouter/free',
        ]);
    }

    public function test_execution_accepts_openrouter_provider(): void
    {
        Queue::fake();

        $this->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
            'provider' => ['id' => 'openrouter', 'api_key' => 'or-user-key'],
        ])->assertStatus(202);

        Queue::assertPushed(ExecuteLauncherJob::class);
    }

    public function test_anonymous_run_dispatches_with_guest_provider_when_provider_id_omitted(): void
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

            return $provider->getValue($job) === 'openrouter';
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
            'is_public' => true,
            'completed_at' => now(),
        ]);

        $response = $this->get('/api/runs/'.$run->id.'/stream');

        $response->assertOk()->assertHeader('X-Accel-Buffering', 'no');
        $this->assertStringContainsString('event: completed', $response->streamedContent());
        $this->assertStringContainsString('"status":"completed"', $response->streamedContent());
    }

    public function test_recent_returns_completed_public_runs(): void
    {
        $launcher = Launcher::where('slug', 'review-pr')->first();

        $completed = Run::create([
            'launcher_id' => $launcher->id,
            'source_url' => 'https://github.com/jellydn/my-ai-tools/pull/42',
            'repo_slug' => 'jellydn/my-ai-tools',
            'repo_type' => 'pull_request',
            'input' => ['source_url' => 'https://github.com/jellydn/my-ai-tools/pull/42'],
            'status' => 'completed',
            'progress' => ['Done'],
            'result' => ['summary' => 'OK', 'risk' => 'medium', 'findings' => [['severity' => 'high', 'title' => 'Bug', 'description' => 'd', 'recommendation' => 'r']], 'verification_steps' => []],
            'is_public' => true,
            'started_at' => now()->subSeconds(34),
            'completed_at' => now(),
        ]);

        Run::create([
            'launcher_id' => $launcher->id,
            'user_id' => User::factory()->create()->id,
            'source_url' => 'https://github.com/private/repo',
            'input' => ['source_url' => 'https://github.com/private/repo'],
            'status' => 'completed',
            'progress' => [],
            'result' => ['summary' => 'private'],
            'completed_at' => now(),
        ]);

        Run::create([
            'launcher_id' => $launcher->id,
            'source_url' => 'https://github.com/failed/repo',
            'input' => ['source_url' => 'https://github.com/failed/repo'],
            'status' => 'failed',
            'progress' => [],
            'error' => 'Something went wrong',
            'is_public' => true,
            'completed_at' => now(),
        ]);

        $response = $this->getJson('/api/runs/recent');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $completed->id)
            ->assertJsonPath('data.0.repo', 'jellydn/my-ai-tools')
            ->assertJsonPath('data.0.type', 'Pull request')
            ->assertJsonPath('data.0.risk', 'medium')
            ->assertJsonPath('data.0.findings_count', 1)
            ->assertJsonPath('data.0.has_verification_steps', false)
            ->assertJsonPath('data.0.launcher_slug', 'review-pr');
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
