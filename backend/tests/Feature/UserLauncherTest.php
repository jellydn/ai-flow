<?php

namespace Tests\Feature;

use App\Jobs\ExecuteLauncherJob;
use App\Models\Launcher;
use App\Models\Run;
use App\Models\User;
use App\Models\UserLauncher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UserLauncherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ─── CRUD Lifecycle ────────────────────────────────────────────

    public function test_authenticated_user_can_create_custom_launcher(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/user/launchers', [
            'slug' => 'my-custom-review',
            'name' => 'My Custom Review',
            'description' => 'A custom workflow for reviewing code changes.',
            'prompt_template' => 'Review the following code changes and look for bugs and security issues.',
            'input_type' => 'pull_request',
            'output_schema' => json_encode([
                'type' => 'object',
                'properties' => [
                    'summary' => ['type' => 'string'],
                    'bugs' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ]),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.slug', 'my-custom-review')
            ->assertJsonPath('data.name', 'My Custom Review')
            ->assertJsonPath('data.is_custom', true);

        $this->assertDatabaseHas('user_launchers', [
            'user_id' => $user->id,
            'slug' => 'my-custom-review',
            'input_type' => 'pull_request',
        ]);
    }

    public function test_guest_cannot_create_custom_launcher(): void
    {
        $response = $this->postJson('/api/user/launchers', [
            'slug' => 'guest-review',
            'name' => 'Guest Review',
            'description' => 'Should not work.',
            'prompt_template' => 'Review changes for bugs and issues.',
            'input_type' => 'pull_request',
            'output_schema' => json_encode(['type' => 'object']),
        ]);

        $response->assertUnauthorized();
    }

    public function test_user_can_list_their_custom_launchers(): void
    {
        $user = User::factory()->create();
        // Stagger created_at so ordering is deterministic.
        UserLauncher::factory()->forUser($user)->create([
            'slug' => 'first',
            'name' => 'First',
            'created_at' => now()->subMinutes(2),
        ]);
        UserLauncher::factory()->forUser($user)->create([
            'slug' => 'second',
            'name' => 'Second',
            'created_at' => now()->subMinute(),
        ]);

        // Another user's launcher should not appear.
        $otherUser = User::factory()->create();
        UserLauncher::factory()->forUser($otherUser)->create(['slug' => 'other', 'name' => 'Other']);

        $response = $this->actingAs($user)->getJson('/api/user/launchers');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'second')  // newest first
            ->assertJsonPath('data.1.slug', 'first');
    }

    public function test_user_can_update_their_custom_launcher(): void
    {
        $user = User::factory()->create();
        $launcher = UserLauncher::factory()->forUser($user)->create([
            'slug' => 'updatable',
            'name' => 'Original Name',
        ]);

        $response = $this->actingAs($user)->putJson("/api/user/launchers/{$launcher->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description for my launcher.',
            'prompt_template' => 'Updated prompt template with enough chars.',
            'input_type' => 'repository',
            'output_schema' => json_encode(['type' => 'object', 'properties' => ['x' => ['type' => 'string']]]),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.input_type', 'repository');
    }

    public function test_user_cannot_update_another_users_launcher(): void
    {
        $owner = User::factory()->create();
        $launcher = UserLauncher::factory()->forUser($owner)->create();

        $attacker = User::factory()->create();
        $this->actingAs($attacker)
            ->putJson("/api/user/launchers/{$launcher->id}", [
                'name' => 'Stolen',
                'description' => 'Should not work.',
                'prompt_template' => 'Prompts with enough characters here.',
                'input_type' => 'repository',
                'output_schema' => json_encode(['type' => 'object']),
            ])
            ->assertForbidden();
    }

    public function test_user_can_delete_their_custom_launcher(): void
    {
        $user = User::factory()->create();
        $launcher = UserLauncher::factory()->forUser($user)->create();

        $this->actingAs($user)
            ->deleteJson("/api/user/launchers/{$launcher->id}")
            ->assertOk()
            ->assertJson(['message' => 'Custom launcher deleted.']);

        $this->assertDatabaseMissing('user_launchers', ['id' => $launcher->id]);
    }

    public function test_user_cannot_delete_another_users_launcher(): void
    {
        $owner = User::factory()->create();
        $launcher = UserLauncher::factory()->forUser($owner)->create();

        $attacker = User::factory()->create();
        $this->actingAs($attacker)
            ->deleteJson("/api/user/launchers/{$launcher->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('user_launchers', ['id' => $launcher->id]);
    }

    // ─── Slug Uniqueness ───────────────────────────────────────────

    public function test_slug_cannot_collide_with_built_in_launcher(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/user/launchers', [
            'slug' => 'review-pr',
            'name' => 'Fake built-in',
            'description' => 'Some description for a custom launcher.',
            'prompt_template' => 'A prompt that is at least twenty characters long.',
            'input_type' => 'pull_request',
            'output_schema' => json_encode(['type' => 'object']),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_slug_must_be_unique_per_user(): void
    {
        $user = User::factory()->create();
        UserLauncher::factory()->forUser($user)->create(['slug' => 'my-flow']);

        $response = $this->actingAs($user)->postJson('/api/user/launchers', [
            'slug' => 'my-flow',
            'name' => 'Duplicate',
            'description' => 'Trying to reuse a slug.',
            'prompt_template' => 'A prompt with at least twenty characters.',
            'input_type' => 'repository',
            'output_schema' => json_encode(['type' => 'object']),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_different_users_can_use_same_custom_slug(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        UserLauncher::factory()->forUser($userA)->create(['slug' => 'shared-slug']);

        $response = $this->actingAs($userB)->postJson('/api/user/launchers', [
            'slug' => 'shared-slug',
            'name' => 'Shared concept',
            'description' => 'Same slug, different user — should be allowed.',
            'prompt_template' => 'A prompt with at least twenty characters.',
            'input_type' => 'repository',
            'output_schema' => json_encode(['type' => 'object']),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('user_launchers', [
            'user_id' => $userB->id,
            'slug' => 'shared-slug',
        ]);
    }

    // ─── Validation ────────────────────────────────────────────────

    public function test_output_schema_must_be_non_empty_json_object(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/user/launchers', [
            'slug' => 'bad-schema',
            'name' => 'Bad schema',
            'description' => 'Testing validation.',
            'prompt_template' => 'Enough characters here to pass the minimum length check.',
            'input_type' => 'repository',
            'output_schema' => json_encode([]),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('output_schema');
    }

    public function test_prompt_template_must_be_at_least_20_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/user/launchers', [
            'slug' => 'short-prompt',
            'name' => 'Short prompt',
            'description' => 'Testing prompt validation.',
            'prompt_template' => 'Too short',
            'input_type' => 'repository',
            'output_schema' => json_encode(['type' => 'object']),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('prompt_template');
    }

    public function test_slug_format_is_validated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/user/launchers', [
            'slug' => 'Invalid Slug!',
            'name' => 'Bad slug',
            'description' => 'Slug format test.',
            'prompt_template' => 'A prompt template with enough characters to pass.',
            'input_type' => 'repository',
            'output_schema' => json_encode(['type' => 'object']),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    // ─── Unified Listing ───────────────────────────────────────────

    public function test_unified_listing_includes_built_in_and_custom_launchers(): void
    {
        $user = User::factory()->create();
        UserLauncher::factory()->forUser($user)->create([
            'slug' => 'my-custom',
            'name' => 'My Custom',
            'created_at' => now()->subMinutes(2),
        ]);
        UserLauncher::factory()->forUser($user)->create([
            'slug' => 'another-one',
            'name' => 'Another One',
            'created_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/launchers');

        $response->assertOk()
            // 4 built-in + 2 custom = 6 total
            ->assertJsonCount(6);

        $items = $response->json();

        // Built-in launchers come first (all is_custom: false).
        $builtIn = array_slice($items, 0, 4);
        foreach ($builtIn as $item) {
            $this->assertFalse($item['is_custom']);
        }

        // Custom launchers are appended (both is_custom: true).
        $custom = array_slice($items, 4, 2);
        $this->assertTrue($custom[0]['is_custom']);
        $this->assertTrue($custom[1]['is_custom']);
        $this->assertSame('another-one', $custom[0]['slug']);
        $this->assertSame('my-custom', $custom[1]['slug']);
    }

    public function test_unified_listing_assigns_icon_and_tone_to_custom_launchers(): void
    {
        $user = User::factory()->create();
        UserLauncher::factory()->forUser($user)->create(['slug' => 'my-flow']);

        $response = $this->actingAs($user)->getJson('/api/launchers');

        // The last item is the custom launcher.
        $response->assertOk();
        $custom = collect($response->json())->firstWhere('is_custom', true);
        $this->assertNotNull($custom['icon']);
        $this->assertNotNull($custom['tone']);
        $this->assertIsString($custom['icon']);
        $this->assertIsString($custom['tone']);
    }

    public function test_unauthenticated_unified_listing_only_shows_built_in(): void
    {
        $user = User::factory()->create();
        UserLauncher::factory()->forUser($user)->create(['slug' => 'private-flow']);

        $response = $this->getJson('/api/launchers');

        $response->assertOk()
            ->assertJsonCount(4)
            ->assertJsonMissing(['is_custom' => true]);
    }

    // ─── Hidden Launcher Toggle ────────────────────────────────────

    public function test_user_can_hide_and_unhide_built_in_launcher(): void
    {
        $user = User::factory()->create();
        $launcher = Launcher::where('slug', 'review-pr')->firstOrFail();

        // Hide
        $this->actingAs($user)
            ->postJson("/api/user/hidden-launchers/{$launcher->slug}")
            ->assertStatus(201)
            ->assertJson(['message' => 'Launcher hidden.']);

        $this->assertDatabaseHas('user_hidden_launchers', [
            'user_id' => $user->id,
            'launcher_id' => $launcher->id,
        ]);

        // Unhide
        $this->actingAs($user)
            ->deleteJson("/api/user/hidden-launchers/{$launcher->slug}")
            ->assertOk()
            ->assertJson(['message' => 'Launcher unhidden.']);

        $this->assertDatabaseMissing('user_hidden_launchers', [
            'user_id' => $user->id,
            'launcher_id' => $launcher->id,
        ]);
    }

    public function test_hidden_launchers_are_filtered_from_unified_listing(): void
    {
        $user = User::factory()->create();
        $launcher = Launcher::where('slug', 'plan-issue')->firstOrFail();

        $this->actingAs($user)
            ->postJson("/api/user/hidden-launchers/{$launcher->slug}")
            ->assertStatus(201);

        $response = $this->actingAs($user)->getJson('/api/launchers');

        $response->assertOk()
            ->assertJsonCount(3)  // 4 built-in - 1 hidden = 3
            ->assertJsonMissing(['slug' => 'plan-issue']);
    }

    public function test_include_hidden_param_shows_all_built_in(): void
    {
        $user = User::factory()->create();
        $launcher = Launcher::where('slug', 'explain-repository')->firstOrFail();

        $this->actingAs($user)
            ->postJson("/api/user/hidden-launchers/{$launcher->slug}")
            ->assertStatus(201);

        $response = $this->actingAs($user)->getJson('/api/launchers?include_hidden=1');

        $response->assertOk()
            ->assertJsonCount(4)  // All built-in shown
            ->assertJsonFragment(['slug' => 'explain-repository']);
    }

    public function test_list_hidden_launchers_returns_slugs(): void
    {
        $user = User::factory()->create();
        $planIssue = Launcher::where('slug', 'plan-issue')->firstOrFail();
        $laravelDoctor = Launcher::where('slug', 'laravel-doctor')->firstOrFail();

        $this->actingAs($user)
            ->postJson("/api/user/hidden-launchers/{$planIssue->slug}")
            ->assertStatus(201);
        $this->actingAs($user)
            ->postJson("/api/user/hidden-launchers/{$laravelDoctor->slug}")
            ->assertStatus(201);

        $response = $this->actingAs($user)->getJson('/api/user/hidden-launchers');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['plan-issue'])
            ->assertJsonFragment(['laravel-doctor']);
    }

    // ─── Run Visibility (is_public) ─────────────────────────────────

    public function test_authenticated_run_defaults_to_private(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
        ]);

        $response->assertStatus(202);
        $this->assertDatabaseHas('runs', [
            'id' => $response->json('id'),
            'user_id' => $user->id,
            'is_public' => false,
        ]);
    }

    public function test_anonymous_run_defaults_to_public(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
        ]);

        $response->assertStatus(202);
        $this->assertDatabaseHas('runs', [
            'id' => $response->json('id'),
            'user_id' => null,
            'is_public' => true,
        ]);
    }

    public function test_authenticated_user_can_mark_run_as_public(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
            'is_public' => true,
        ]);

        $response->assertStatus(202);
        $this->assertDatabaseHas('runs', [
            'id' => $response->json('id'),
            'is_public' => true,
        ]);
    }

    public function test_public_authenticated_run_is_viewable_by_anyone(): void
    {
        $user = User::factory()->create();
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'user_id' => $user->id,
            'source_url' => 'https://github.com/laravel/framework',
            'input' => ['source_url' => 'https://github.com/laravel/framework'],
            'status' => 'completed',
            'progress' => [],
            'result' => ['summary' => 'Public', 'risk' => 'low', 'findings' => [], 'verification_steps' => []],
            'is_public' => true,
            'completed_at' => now(),
        ]);

        // Anonymous viewer can see the public run.
        $this->getJson('/api/runs/'.$run->id)
            ->assertOk()
            ->assertJsonPath('data.id', $run->id);

        // Owner can still see their own run.
        $this->actingAs($user)
            ->getJson('/api/runs/'.$run->id)
            ->assertOk();
    }

    // ─── Custom Launcher Execution ──────────────────────────────────

    public function test_custom_launcher_run_persists_user_launcher_id(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $launcher = UserLauncher::factory()->forUser($user)->create([
            'slug' => 'my-reviewer',
            'input_type' => 'pull_request',
        ]);

        $response = $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'my-reviewer',
            'source_url' => 'https://github.com/laravel/framework/pull/1',
        ]);

        $response->assertStatus(202);
        $run = Run::find($response->json('id'));

        $this->assertNotNull($run->user_launcher_id);
        $this->assertSame($launcher->id, $run->user_launcher_id);
        // Placeholder built-in launcher_id is set.
        $this->assertNotNull($run->launcher_id);
        $this->assertSame($user->id, $run->user_id);
    }

    public function test_custom_launcher_run_dispatches_job(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        UserLauncher::factory()->forUser($user)->create([
            'slug' => 'dispatcher',
            'input_type' => 'repository',
        ]);

        $response = $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'dispatcher',
            'source_url' => 'https://github.com/laravel/framework',
        ]);

        $response->assertStatus(202);
        Queue::assertPushed(ExecuteLauncherJob::class, function (ExecuteLauncherJob $job) use ($response): bool {
            return $job->runId === $response->json('id');
        });
    }

    public function test_guest_cannot_use_custom_launcher(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        UserLauncher::factory()->forUser($user)->create(['slug' => 'private-flow']);

        $response = $this->postJson('/api/runs', [
            'launcher' => 'private-flow',
            'source_url' => 'https://github.com/laravel/framework',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('launcher');
    }

    public function test_custom_launcher_run_stores_prompt_snapshot(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        UserLauncher::factory()->forUser($user)->create([
            'slug' => 'snapshot-test',
            'prompt_template' => 'Custom prompt: find issues.',
            'input_type' => 'repository',
        ]);

        $response = $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'snapshot-test',
            'source_url' => 'https://github.com/laravel/framework',
        ]);

        $response->assertStatus(202);
        $this->assertDatabaseHas('runs', [
            'id' => $response->json('id'),
            'prompt_snapshot' => 'Custom prompt: find issues.',
        ]);
    }

    public function test_run_resource_includes_is_custom_and_correct_launcher_slug(): void
    {
        $user = User::factory()->create();
        $launcher = UserLauncher::factory()->forUser($user)->create([
            'slug' => 'my-custom-flow',
        ]);

        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'user_launcher_id' => $launcher->id,
            'user_id' => $user->id,
            'source_url' => 'https://github.com/laravel/framework',
            'input' => ['source_url' => 'https://github.com/laravel/framework'],
            'status' => 'completed',
            'progress' => [],
            'result' => ['summary' => 'Custom flow result', 'risk' => 'low', 'findings' => [], 'verification_steps' => []],
            'is_public' => true,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/runs/'.$run->id);

        $response->assertOk()
            ->assertJsonPath('data.launcher', 'my-custom-flow')
            ->assertJsonPath('data.is_custom', true);
    }

    public function test_built_in_run_resource_returns_is_custom_false(): void
    {
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'source_url' => 'https://github.com/laravel/framework',
            'input' => ['source_url' => 'https://github.com/laravel/framework'],
            'status' => 'completed',
            'progress' => [],
            'result' => ['summary' => 'Built-in result', 'risk' => 'low', 'findings' => [], 'verification_steps' => []],
            'is_public' => true,
            'completed_at' => now(),
        ]);

        $response = $this->getJson('/api/runs/'.$run->id);

        $response->assertOk()
            ->assertJsonPath('data.is_custom', false);
    }

    public function test_store_run_validates_custom_launcher_slug(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        UserLauncher::factory()->forUser($user)->create([
            'slug' => 'valid-custom',
            'input_type' => 'repository',
        ]);

        // Valid custom slug passes.
        $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'valid-custom',
            'source_url' => 'https://github.com/laravel/framework',
        ])->assertStatus(202);

        // Nonexistent slug fails.
        $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'nonexistent-slug',
            'source_url' => 'https://github.com/laravel/framework',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('launcher');
    }
}
