<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectChangelog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectChangelog>
 */
class ProjectChangelogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'project_version' => fake()->semver(),
            'changelog_content' => fake()->paragraph(),
        ];
    }
}
