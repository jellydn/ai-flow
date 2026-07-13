<?php

namespace Tests\Feature;

use App\Models\Launcher;
use App\Models\Run;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RunOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authenticated_run_sets_user_id(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
        ]);

        $response->assertStatus(202);
        $run = Run::find($response->json('id'));
        $this->assertSame($user->id, $run->user_id);
    }

    public function test_anonymous_run_has_null_user_id(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/runs', [
            'launcher' => 'explain-repository',
            'source_url' => 'https://github.com/laravel/framework',
        ]);

        $response->assertStatus(202);
        $run = Run::find($response->json('id'));
        $this->assertNull($run->user_id);
    }

    public function test_owner_can_see_provider_and_model_on_their_run(): void
    {
        $user = User::factory()->create();
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'user_id' => $user->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'source_url' => 'https://github.com/laravel/framework',
            'input' => ['source_url' => 'https://github.com/laravel/framework'],
            'status' => 'queued',
            'progress' => [],
        ]);

        $this->actingAs($user)
            ->getJson('/api/runs/'.$run->id)
            ->assertOk()
            ->assertJsonPath('data.provider', 'openai')
            ->assertJsonPath('data.model', 'gpt-4o');
    }

    public function test_anonymous_user_cannot_see_provider_and_model(): void
    {
        $user = User::factory()->create();
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'user_id' => $user->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'source_url' => 'https://github.com/laravel/framework',
            'input' => ['source_url' => 'https://github.com/laravel/framework'],
            'status' => 'queued',
            'progress' => [],
        ]);

        $this->getJson('/api/runs/'.$run->id)
            ->assertForbidden();
    }

    public function test_other_authenticated_user_cannot_see_private_run(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'user_id' => $owner->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'source_url' => 'https://github.com/laravel/framework',
            'input' => ['source_url' => 'https://github.com/laravel/framework'],
            'status' => 'queued',
            'progress' => [],
        ]);

        $this->actingAs($otherUser)
            ->getJson('/api/runs/'.$run->id)
            ->assertForbidden();
    }

    public function test_public_run_is_viewable_by_anyone(): void
    {
        $run = Run::create([
            'launcher_id' => Launcher::where('slug', 'explain-repository')->value('id'),
            'user_id' => null,
            'source_url' => 'https://github.com/laravel/framework',
            'input' => ['source_url' => 'https://github.com/laravel/framework'],
            'status' => 'queued',
            'progress' => [],
        ]);

        $this->getJson('/api/runs/'.$run->id)
            ->assertOk()
            ->assertJsonPath('data.id', $run->id);
    }
}
