<?php

namespace Tests\Feature;

use App\Console\Commands\ReapStuckRuns;
use App\Models\Launcher;
use App\Models\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReapStuckRunsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reaper_transitions_stuck_running_runs_to_failed(): void
    {
        $this->seed();
        $launcherId = Launcher::where('slug', 'explain-repository')->value('id');

        $stuckRun = Run::create([
            'launcher_id' => $launcherId,
            'source_url' => 'https://github.com/a/b',
            'status' => 'running',
            'started_at' => now()->subMinutes(5),
            'input' => ['source_url' => 'https://github.com/a/b'],
            'progress' => ['Fetching repository', 'Running AI analysis'],
            'source_context' => ['repository' => ['full_name' => 'a/b']],
        ]);

        $freshRun = Run::create([
            'launcher_id' => $launcherId,
            'source_url' => 'https://github.com/c/d',
            'status' => 'running',
            'started_at' => now()->subSeconds(30),
            'input' => ['source_url' => 'https://github.com/c/d'],
            'progress' => ['Fetching repository'],
        ]);

        Artisan::call(ReapStuckRuns::class, ['--ttl' => 120]);

        $stuckRun->refresh();
        $freshRun->refresh();

        $this->assertSame('failed', $stuckRun->status);
        $this->assertSame('Run timed out.', $stuckRun->error);
        $this->assertNull($stuckRun->source_context);
        $this->assertNotNull($stuckRun->completed_at);

        $this->assertSame('running', $freshRun->status);
        $this->assertNull($freshRun->error);
    }

    public function test_reaper_skips_when_no_stuck_runs(): void
    {
        $this->seed();
        $result = Artisan::call(ReapStuckRuns::class);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('No stuck runs found', Artisan::output());
    }

    public function test_reaper_skips_completed_runs(): void
    {
        $this->seed();
        $launcherId = Launcher::where('slug', 'explain-repository')->value('id');

        Run::create([
            'launcher_id' => $launcherId,
            'source_url' => 'https://github.com/a/b',
            'status' => 'completed',
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(9),
            'input' => ['source_url' => 'https://github.com/a/b'],
            'progress' => ['Fetching repository', 'Running AI analysis', 'Preparing report'],
        ]);

        Artisan::call(ReapStuckRuns::class, ['--ttl' => 60]);

        $this->assertDatabaseHas('runs', [
            'source_url' => 'https://github.com/a/b',
            'status' => 'completed',
        ]);
    }

    public function test_reaper_skips_queued_runs(): void
    {
        $this->seed();
        $launcherId = Launcher::where('slug', 'explain-repository')->value('id');

        Run::create([
            'launcher_id' => $launcherId,
            'source_url' => 'https://github.com/a/b',
            'status' => 'queued',
            'input' => ['source_url' => 'https://github.com/a/b'],
            'progress' => [],
        ]);

        Artisan::call(ReapStuckRuns::class, ['--ttl' => 10]);

        $this->assertDatabaseHas('runs', [
            'source_url' => 'https://github.com/a/b',
            'status' => 'queued',
        ]);
    }
}
