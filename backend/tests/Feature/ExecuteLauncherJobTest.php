<?php

namespace Tests\Feature;

use App\Contracts\AIProviderInterface;
use App\Data\GitHubReference;
use App\Jobs\ExecuteLauncherJob;
use App\Models\Launcher;
use App\Models\Run;
use App\Services\ContextEncoder;
use App\Services\GitHubService;
use App\Services\JsonSchemaValidator;
use App\Services\OpenAIProvider;
use App\Services\RunExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ExecuteLauncherJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_marks_run_failed_when_provider_is_unsupported(): void
    {
        $this->seed();
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'source_url' => 'https://github.com/a/b',
            'input' => ['source_url' => 'https://github.com/a/b'],
            'progress' => [],
        ]);
        $executor = Mockery::mock(RunExecutor::class);
        $executor->shouldNotReceive('execute');

        (new ExecuteLauncherJob($run->id, 'groq'))->handle($executor);

        $fresh = $run->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertSame('Unsupported AI provider.', $fresh->error);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_job_fails_when_no_api_key_configured(): void
    {
        $this->seed();
        config()->set('services.openai.key', null);
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'source_url' => 'https://github.com/a/b',
            'input' => ['source_url' => 'https://github.com/a/b'],
            'progress' => [],
        ]);
        $executor = Mockery::mock(RunExecutor::class);
        $executor->shouldNotReceive('execute');

        (new ExecuteLauncherJob($run->id))->handle($executor);

        $fresh = $run->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertStringContainsString('No AI provider API key', $fresh->error);
    }

    public function test_job_delegates_run_execution(): void
    {
        $this->seed();
        config()->set('services.openai.key', 'server-test-key');
        $run = Run::create(['launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'), 'source_url' => 'https://github.com/a/b', 'input' => ['source_url' => 'https://github.com/a/b'], 'progress' => []]);
        $executor = Mockery::mock(RunExecutor::class);
        $executor->shouldReceive('execute')->once()->withArgs(fn (Run $executedRun, AIProviderInterface $executedAi): bool => $executedRun->is($run));

        (new ExecuteLauncherJob($run->id))->handle($executor);
    }

    public function test_job_payload_encrypts_byok_secret(): void
    {
        $apiKey = 'sk-plaintext-must-not-enter-queue';
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('queue.default', 'database');

        ExecuteLauncherJob::dispatch('00000000-0000-0000-0000-000000000000', 'openai', $apiKey);
        $payload = DB::table('jobs')->value('payload');

        $this->assertStringNotContainsString($apiKey, $payload);
    }

    public function test_run_executor_uses_server_key_when_byok_omitted(): void
    {
        $this->seed();
        config()->set('services.openai', [
            'key' => 'server-secret-key',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'timeout' => 30,
        ]);
        $aiJson = '{"summary":"Ok","risk":"low","findings":[],"verification_steps":[]}';
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => $aiJson]]],
            ]),
        ]);
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'source_url' => 'https://github.com/a/b',
            'input' => ['source_url' => 'https://github.com/a/b'],
            'progress' => [],
        ]);
        $github = Mockery::mock(GitHubService::class);
        $github->shouldReceive('parse')->andReturn(new GitHubReference('a', 'b', 'repository'));
        $github->shouldReceive('context')->andReturn(['repository' => ['full_name' => 'a/b']]);

        (new RunExecutor($github, new ContextEncoder, new JsonSchemaValidator))
            ->execute($run, app()->make(OpenAIProvider::class, ['apiKey' => null]));

        $this->assertSame('completed', $run->fresh()->status);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer server-secret-key'));
    }

    public function test_job_records_structured_result(): void
    {
        $this->seed();
        $run = Run::create(['launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'), 'source_url' => 'https://github.com/a/b', 'input' => ['source_url' => 'https://github.com/a/b'], 'progress' => []]);
        $gh = Mockery::mock(GitHubService::class);
        $gh->shouldReceive('parse')->andReturn(new GitHubReference('a', 'b', 'repository'));
        $gh->shouldReceive('context')->andReturn(['repository' => ['full_name' => 'a/b']]);
        $ai = Mockery::mock(AIProviderInterface::class);
        $ai->shouldReceive('generate')->andReturn(['summary' => 'Good', 'risk' => 'low', 'findings' => [], 'verification_steps' => []]);
        (new RunExecutor($gh, new ContextEncoder, new JsonSchemaValidator))->execute($run, $ai);
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame('Good', $run->fresh()->result['summary']);
    }

    public function test_job_rejects_malformed_ai_result(): void
    {
        $this->seed();
        $run = Run::create(['launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'), 'source_url' => 'https://github.com/a/b', 'input' => ['source_url' => 'https://github.com/a/b'], 'progress' => []]);
        $github = Mockery::mock(GitHubService::class);
        $github->shouldReceive('parse')->andReturn(new GitHubReference('a', 'b', 'repository'));
        $github->shouldReceive('context')->andReturn(['repository' => ['name' => 'b']]);
        $ai = Mockery::mock(AIProviderInterface::class);
        $ai->shouldReceive('generate')->andReturn(['summary' => 'Missing required fields', 'findings' => [['severity' => 'impossible']]]);

        (new RunExecutor($github, new ContextEncoder, new JsonSchemaValidator))->execute($run, $ai);

        $this->assertSame('failed', $run->fresh()->status);
        $this->assertNull($run->fresh()->result);
        $this->assertNull($run->fresh()->source_context);
    }

    public function test_job_structurally_bounds_large_github_context(): void
    {
        $this->seed();
        $run = Run::create(['launcher_id' => Launcher::where('slug', 'review-pr')->value('id'), 'source_url' => 'https://github.com/a/b/pull/1', 'input' => ['source_url' => 'https://github.com/a/b/pull/1'], 'progress' => []]);
        $github = Mockery::mock(GitHubService::class);
        $github->shouldReceive('parse')->andReturn(new GitHubReference('a', 'b', 'pull_request', 1));
        $github->shouldReceive('context')->andReturn([
            'reference' => ['owner' => 'a', 'repo' => 'b', 'type' => 'pull_request', 'number' => 1],
            'repository' => ['full_name' => 'a/b', 'readme' => str_repeat('r', 50_000), 'file_tree' => []],
            'changed_files' => array_fill(0, 50, ['name' => 'file.php', 'status' => 'modified', 'diff' => str_repeat('d', 4_000)]),
        ]);
        $ai = Mockery::mock(AIProviderInterface::class);
        $ai->shouldReceive('generate')->once()->withArgs(function (string $prompt): bool {
            $context = json_decode(explode("GitHub context:\n", $prompt, 2)[1], true, flags: JSON_THROW_ON_ERROR);

            return $context['truncated'] === true && strlen(json_encode($context)) <= 120_000;
        })->andReturn(['summary' => 'Good', 'risk' => 'low', 'findings' => [], 'verification_steps' => []]);

        (new RunExecutor($github, new ContextEncoder, new JsonSchemaValidator))->execute($run, $ai);

        $this->assertSame('completed', $run->fresh()->status);
    }

    public function test_byok_failure_does_not_log_api_key(): void
    {
        $this->seed();
        $apiKey = 'sk-never-log-this';
        $run = Run::create(['launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'), 'source_url' => 'https://github.com/a/b', 'input' => ['source_url' => 'https://github.com/a/b'], 'progress' => []]);
        $github = Mockery::mock(GitHubService::class);
        $github->shouldReceive('parse')->andReturn(new GitHubReference('a', 'b', 'repository'));
        $github->shouldReceive('context')->andReturn(['repository' => ['name' => 'b']]);
        $ai = Mockery::mock(AIProviderInterface::class);
        $ai->shouldReceive('generate')->andThrow(new RuntimeException('Invalid API key.'));
        Log::spy();

        (new RunExecutor($github, new ContextEncoder, new JsonSchemaValidator))->execute($run, $ai);

        $this->assertSame('Invalid API key.', $run->fresh()->error);
        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) use ($apiKey): bool {
            return ! str_contains($message.json_encode($context), $apiKey)
                && ! array_key_exists('message', $context);
        });
    }

    public function test_run_executor_hides_non_allowlisted_invalid_argument_messages(): void
    {
        $this->seed();
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'source_url' => 'https://github.com/a/b',
            'input' => ['source_url' => 'https://github.com/a/b'],
            'progress' => [],
        ]);
        $github = Mockery::mock(GitHubService::class);
        $github->shouldReceive('parse')->andReturn(new GitHubReference('a', 'b', 'repository'));
        $github->shouldReceive('context')->andReturn(['repository' => ['name' => 'b']]);
        $ai = Mockery::mock(AIProviderInterface::class);
        $ai->shouldReceive('generate')->andThrow(new \InvalidArgumentException('Internal parser detail.'));

        (new RunExecutor($github, new ContextEncoder, new JsonSchemaValidator))->execute($run, $ai);

        $this->assertSame('Run failed unexpectedly.', $run->fresh()->error);
    }
}
