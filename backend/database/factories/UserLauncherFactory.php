<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserLauncher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserLauncher>
 */
class UserLauncherFactory extends Factory
{
    protected $model = UserLauncher::class;

    public function definition(): array
    {
        $slug = $this->faker->unique()->slug(2);

        return [
            'user_id' => User::factory(),
            'slug' => $slug,
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'prompt_template' => 'Custom review for this project: look for bugs and security issues.',
            'input_type' => $this->faker->randomElement(['repository', 'pull_request', 'issue']),
            'output_schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['summary', 'risk'],
                'properties' => [
                    'summary' => ['type' => 'string'],
                    'risk' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                ],
            ],
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }
}
