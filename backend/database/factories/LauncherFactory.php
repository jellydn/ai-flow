<?php

namespace Database\Factories;

use App\Launchers\BaseLauncher;
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
     * castable to array, active by default). The output schema is shared
     * from BaseLauncher::outputSchema() rather than duplicated here.
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
            'output_schema' => BaseLauncher::outputSchema(),
            'active' => true,
        ];
    }
}
