<?php

namespace Tests\Feature;

use App\Models\Launcher;
use App\Models\Run;
use App\Models\User;
use App\Models\UserLauncher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the UserLauncher cascade-delete behavior (CONCERNS #13).
 *
 * UserLauncher::booted() uses a bulk DELETE query to remove associated runs
 * when the launcher is deleted. This test ensures:
 * 1. All runs associated with the deleted launcher are removed.
 * 2. Runs from other launchers (built-in or other user launchers) are not affected.
 * 3. The bulk delete works even when multiple runs exist.
 */
class UserLauncherCascadeDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_user_launcher_cascades_to_associated_runs(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $launcher = UserLauncher::factory()->forUser($user)->create([
            'slug' => 'cascade-test',
            'input_type' => 'repository',
        ]);

        $builtInLauncherId = Launcher::where('slug', 'explain-repository')->value('id');

        // Create multiple runs for the user launcher.
        $run1 = Run::create([
            'launcher_id' => $builtInLauncherId,
            'user_launcher_id' => $launcher->id,
            'user_id' => $user->id,
            'source_url' => 'https://github.com/a/b',
            'input' => ['source_url' => 'https://github.com/a/b'],
            'status' => 'completed',
            'progress' => [],
            'result' => ['summary' => 'Run 1', 'risk' => 'low', 'findings' => [], 'verification_steps' => []],
            'is_public' => true,
            'completed_at' => now(),
        ]);

        $run2 = Run::create([
            'launcher_id' => $builtInLauncherId,
            'user_launcher_id' => $launcher->id,
            'user_id' => $user->id,
            'source_url' => 'https://github.com/c/d',
            'input' => ['source_url' => 'https://github.com/c/d'],
            'status' => 'failed',
            'progress' => [],
            'result' => null,
            'error' => 'Some error',
            'is_public' => false,
            'completed_at' => now(),
        ]);

        // Create a run for a built-in launcher (should NOT be deleted).
        $builtInRun = Run::create([
            'launcher_id' => $builtInLauncherId,
            'user_id' => $user->id,
            'source_url' => 'https://github.com/e/f',
            'input' => ['source_url' => 'https://github.com/e/f'],
            'status' => 'completed',
            'progress' => [],
            'result' => ['summary' => 'Built-in run', 'risk' => 'low', 'findings' => [], 'verification_steps' => []],
            'is_public' => true,
            'completed_at' => now(),
        ]);

        // Delete the user launcher.
        $launcher->delete();

        // Both runs associated with the user launcher should be gone.
        $this->assertDatabaseMissing('runs', ['id' => $run1->id]);
        $this->assertDatabaseMissing('runs', ['id' => $run2->id]);

        // The built-in launcher run should still exist.
        $this->assertDatabaseHas('runs', ['id' => $builtInRun->id]);
    }

    public function test_deleting_user_launcher_does_not_affect_other_user_launchers_runs(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $builtInLauncherId = Launcher::where('slug', 'explain-repository')->value('id');

        $launcherA = UserLauncher::factory()->forUser($user)->create([
            'slug' => 'launcher-a',
            'input_type' => 'repository',
        ]);

        $launcherB = UserLauncher::factory()->forUser($user)->create([
            'slug' => 'launcher-b',
            'input_type' => 'repository',
        ]);

        $runB = Run::create([
            'launcher_id' => $builtInLauncherId,
            'user_launcher_id' => $launcherB->id,
            'user_id' => $user->id,
            'source_url' => 'https://github.com/x/y',
            'input' => ['source_url' => 'https://github.com/x/y'],
            'status' => 'completed',
            'progress' => [],
            'result' => ['summary' => 'Run B', 'risk' => 'low', 'findings' => [], 'verification_steps' => []],
            'is_public' => true,
            'completed_at' => now(),
        ]);

        // Delete launcher A — launcher B's runs should be unaffected.
        $launcherA->delete();

        $this->assertDatabaseHas('runs', ['id' => $runB->id]);
        $this->assertDatabaseHas('user_launchers', ['id' => $launcherB->id]);
    }
}
