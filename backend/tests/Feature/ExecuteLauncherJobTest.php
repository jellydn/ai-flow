<?php

namespace Tests\Feature;

use App\Contracts\AIProviderInterface;
use App\Data\GitHubReference;
use App\Jobs\ExecuteLauncherJob;
use App\Models\Launcher;
use App\Models\Run;
use App\Services\GitHubService;
use App\Services\JsonSchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ExecuteLauncherJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_records_structured_result(): void
    {
        $this->seed();
        $run = Run::create(['launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'), 'source_url' => 'https://github.com/a/b', 'input' => ['source_url' => 'https://github.com/a/b'], 'progress' => []]);
        $gh = Mockery::mock(GitHubService::class);
        $gh->shouldReceive('parse')->andReturn(new GitHubReference('a', 'b', 'repository'));
        $gh->shouldReceive('context')->andReturn(['repository' => ['full_name' => 'a/b']]);
        $ai = Mockery::mock(AIProviderInterface::class);
        $ai->shouldReceive('generate')->andReturn(['summary' => 'Good', 'risk' => 'low', 'findings' => [], 'verification_steps' => []]);
        (new ExecuteLauncherJob($run->id))->handle($gh, $ai, new JsonSchemaValidator);
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

        (new ExecuteLauncherJob($run->id))->handle($github, $ai, new JsonSchemaValidator);

        $this->assertSame('failed', $run->fresh()->status);
        $this->assertNull($run->fresh()->result);
    }
}
