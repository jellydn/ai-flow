<?php

namespace Tests\Feature;

use App\Jobs\ExecuteLauncherJob;
use App\Models\Launcher;
use App\Models\Run;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RunHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authenticated_user_can_list_their_runs(): void
    {
        $user = User::factory()->create();
        $launcherId = Launcher::where('slug', 'explain-repository')->value('id');

        Run::create([
            'launcher_id' => $launcherId, 'user_id' => $user->id,
            'source_url' => 'https://github.com/a/b', 'input' => [], 'status' => 'completed', 'progress' => [],
        ]);

        $this->actingAs($user)
            ->getJson('/api/user/runs')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_cannot_see_other_users_runs_in_history(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $launcherId = Launcher::where('slug', 'explain-repository')->value('id');

        Run::create([
            'launcher_id' => $launcherId, 'user_id' => $other->id,
            'source_url' => 'https://github.com/a/b', 'input' => [], 'status' => 'completed', 'progress' => [],
        ]);

        $this->actingAs($user)
            ->getJson('/api/user/runs')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_history_can_filter_by_status(): void
    {
        $user = User::factory()->create();
        $launcherId = Launcher::where('slug', 'explain-repository')->value('id');

        Run::create([
            'launcher_id' => $launcherId, 'user_id' => $user->id,
            'source_url' => 'https://github.com/a/b', 'input' => [], 'status' => 'completed', 'progress' => [],
        ]);
        Run::create([
            'launcher_id' => $launcherId, 'user_id' => $user->id,
            'source_url' => 'https://github.com/a/c', 'input' => [], 'status' => 'failed', 'progress' => [],
        ]);

        $this->actingAs($user)
            ->getJson('/api/user/runs?status=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_history_requires_authentication(): void
    {
        $this->getJson('/api/user/runs')->assertUnauthorized();
    }

    public function test_user_can_retry_their_run(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $launcherId = Launcher::where('slug', 'explain-repository')->value('id');

        $run = Run::create([
            'launcher_id' => $launcherId, 'user_id' => $user->id,
            'source_url' => 'https://github.com/a/b', 'input' => [], 'status' => 'failed', 'progress' => [], 'provider' => 'openai', 'model' => 'gpt-4o',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/user/runs/'.$run->id.'/retry')
            ->assertStatus(202);

        $this->assertDatabaseHas('runs', ['id' => $response->json('id'), 'status' => 'queued']);
        Queue::assertPushed(ExecuteLauncherJob::class);
    }

    public function test_user_cannot_retry_other_users_run(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $launcherId = Launcher::where('slug', 'explain-repository')->value('id');

        $run = Run::create([
            'launcher_id' => $launcherId, 'user_id' => $owner->id,
            'source_url' => 'https://github.com/a/b', 'input' => [], 'status' => 'completed', 'progress' => [],
        ]);

        $this->actingAs($other)
            ->postJson('/api/user/runs/'.$run->id.'/retry')
            ->assertForbidden();
    }

    public function test_user_can_delete_their_run(): void
    {
        $user = User::factory()->create();
        $launcherId = Launcher::where('slug', 'explain-repository')->value('id');

        $run = Run::create([
            'launcher_id' => $launcherId, 'user_id' => $user->id,
            'source_url' => 'https://github.com/a/b', 'input' => [], 'status' => 'completed', 'progress' => [],
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/user/runs/'.$run->id)
            ->assertOk();

        $this->assertDatabaseMissing('runs', ['id' => $run->id]);
    }
}
