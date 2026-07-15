<?php

namespace Tests\Feature;

use App\Jobs\ExecuteLauncherJob;
use App\Models\Launcher;
use App\Models\LauncherPromptOverride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RunPromptSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authenticated_run_stores_user_override_in_prompt_snapshot(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $launcher = Launcher::where('slug', 'laravel-doctor')->firstOrFail();
        $custom = str_repeat('User-specific doctor prompt for snapshot test. ', 2);

        LauncherPromptOverride::query()->create([
            'user_id' => $user->id,
            'launcher_id' => $launcher->id,
            'prompt_template' => $custom,
        ]);

        $response = $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'laravel-doctor',
            'source_url' => 'https://github.com/laravel/framework',
        ])->assertStatus(202);

        $this->assertDatabaseHas('runs', [
            'id' => $response->json('id'),
            'prompt_snapshot' => $custom,
        ]);
    }

    public function test_anonymous_run_snapshots_platform_default(): void
    {
        Queue::fake();
        $launcher = Launcher::where('slug', 'explain-repository')->firstOrFail();

        $response = $this->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
        ])->assertStatus(202);

        $this->assertDatabaseHas('runs', [
            'id' => $response->json('id'),
            'prompt_snapshot' => $launcher->prompt_template,
        ]);

        Queue::assertPushed(ExecuteLauncherJob::class);
    }
}
