<?php

namespace Database\Factories;

use App\Models\Launcher;
use App\Models\Run;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Run>
 */
class RunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $repoSlug = $this->faker->userName().'/'.$this->faker->slug(1);

        return [
            'launcher_id' => Launcher::factory(),
            'user_id' => null,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'source_url' => 'https://github.com/'.$repoSlug,
            'repo_slug' => $repoSlug,
            'repo_type' => $this->faker->randomElement(['repository', 'pull_request', 'issue']),
            'status' => 'queued',
            'progress' => [],
            'input' => [],
            'prompt_snapshot' => 'Review this repository for defects.',
        ];
    }

    /**
     * Run owned by a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * A completed run with a result payload.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'result' => [
                'summary' => $this->faker->sentence(),
                'risk' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
                'findings' => [
                    [
                        'severity' => 'medium',
                        'title' => 'Sample finding',
                        'description' => $this->faker->paragraph(),
                        'recommendation' => $this->faker->sentence(),
                    ],
                ],
                'verification_steps' => ['Verify the fix locally.'],
            ],
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    /**
     * A failed run with an error message.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error' => 'Run failed: '.$this->faker->sentence(),
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);
    }

    /**
     * A public (anonymous, unowned) run.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }
}
