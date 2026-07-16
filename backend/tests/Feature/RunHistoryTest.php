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

    public function test_per_page_above_100_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user/runs?per_page=200')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_per_page_minimum_is_1(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user/runs?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_valid_per_page_passes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user/runs?per_page=50')
            ->assertOk();
    }

    public function test_invalid_status_returns_validation_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user/runs?status=archived')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_valid_statuses_pass(): void
    {
        $user = User::factory()->create();
        $launcherId = Launcher::where('slug', 'explain-repository')->value('id');

        Run::create([
            'launcher_id' => $launcherId, 'user_id' => $user->id,
            'source_url' => 'https://github.com/a/b', 'input' => [], 'status' => 'completed', 'progress' => [],
        ]);

        foreach (['completed', 'failed', 'queued', 'running'] as $status) {
            $this->actingAs($user)
                ->getJson('/api/user/runs?status='.$status)
                ->assertOk();
        }
    }

    public function test_invalid_date_format_returns_validation_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user/runs?date_from=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_from']);
    }

    public function test_date_from_after_date_to_returns_validation_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user/runs?date_from=2026-06-01&date_to=2026-01-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_date_range_with_equal_dates_passes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user/runs?date_from=2026-06-01&date_to=2026-06-01')
            ->assertOk();
    }

    public function test_search_max_length_is_enforced(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user/runs?search='.str_repeat('x', 501))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);
    }
}
