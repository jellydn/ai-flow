<?php

namespace Database\Seeders;

use App\Launchers\ExplainRepositoryLauncher;
use App\Launchers\LaravelDoctorLauncher;
use App\Launchers\PlanIssueLauncher;
use App\Launchers\ReviewPullRequestLauncher;
use App\Models\Launcher;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach ([ReviewPullRequestLauncher::class, PlanIssueLauncher::class, ExplainRepositoryLauncher::class, LaravelDoctorLauncher::class] as $class) {
            $metadata = $class::metadata();
            Launcher::updateOrCreate(['slug' => $metadata['slug']], ['name' => $metadata['name'], 'description' => $metadata['description'], 'input_type' => $metadata['inputType'], 'prompt_template' => $metadata['prompt'], 'output_schema' => $metadata['outputSchema'], 'class_name' => $class, 'active' => true]);
        }
    }
}
