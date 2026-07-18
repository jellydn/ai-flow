<?php

namespace Database\Factories;

use App\Models\Launcher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Launcher>
 */
class LauncherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The shape mirrors BaseLauncher::make() + DatabaseSeeder so factory-
     * created launchers are valid for runs (slug unique, output_schema
     * castable to array, active by default).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = $this->faker->unique()->slug(2);

        return [
            'slug' => $slug,
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'prompt_template' => 'Review this repository for correctness, security, and missing tests.',
            'input_type' => $this->faker->randomElement(['repository', 'pull_request', 'issue']),
            'output_schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['summary', 'risk', 'findings', 'verification_steps'],
                'properties' => [
                    'summary' => ['type' => 'string'],
                    'risk' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
                    'findings' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['severity', 'title', 'description', 'recommendation'],
                            'properties' => [
                                'severity' => ['type' => 'string', 'enum' => ['info', 'low', 'medium', 'high', 'critical']],
                                'title' => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                                'recommendation' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'verification_steps' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
            'active' => true,
        ];
    }

    /**
     * A pull-request launcher (matches ReviewPullRequestLauncher shape).
     */
    public function pullRequest(): static
    {
        return $this->state(fn (array $attributes) => [
            'input_type' => 'pull_request',
        ]);
    }

    /**
     * An issue launcher (matches PlanIssueLauncher shape).
     */
    public function issue(): static
    {
        return $this->state(fn (array $attributes) => [
            'input_type' => 'issue',
        ]);
    }
}
