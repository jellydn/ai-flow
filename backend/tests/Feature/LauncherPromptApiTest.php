<?php

namespace Tests\Feature;

use App\Models\Launcher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LauncherPromptApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->user = User::factory()->create();
    }

    public function test_lists_launchers_with_defaults_for_authenticated_user(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/user/launcher-prompts');

        $response->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.slug', 'explain-repository')
            ->assertJsonPath('data.0.uses_override', false)
            ->assertJsonStructure(['data' => [['slug', 'name', 'default_prompt_template', 'override_prompt_template', 'uses_override']]]);
    }

    public function test_can_upsert_and_delete_override(): void
    {
        $custom = trim(str_repeat('Custom Laravel doctor instructions. ', 3));

        $this->actingAs($this->user)
            ->putJson('/api/user/launcher-prompts/laravel-doctor', ['prompt_template' => $custom])
            ->assertOk();

        $list = $this->actingAs($this->user)->getJson('/api/user/launcher-prompts')->assertOk();
        $doctor = collect($list->json('data'))->firstWhere('slug', 'laravel-doctor');
        $this->assertSame(true, $doctor['uses_override']);
        $this->assertSame($custom, $doctor['override_prompt_template']);

        $this->actingAs($this->user)
            ->deleteJson('/api/user/launcher-prompts/laravel-doctor')
            ->assertOk();

        $this->assertDatabaseMissing('launcher_prompt_overrides', [
            'user_id' => $this->user->id,
            'launcher_id' => Launcher::where('slug', 'laravel-doctor')->value('id'),
        ]);
    }

    public function test_rejects_short_prompt(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/user/launcher-prompts/review-pr', ['prompt_template' => 'too short'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['prompt_template']);
    }

    public function test_guest_cannot_access_launcher_prompts(): void
    {
        $this->getJson('/api/user/launcher-prompts')->assertUnauthorized();
    }
}
