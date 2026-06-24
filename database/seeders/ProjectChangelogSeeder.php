<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectChangelog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProjectChangelogSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<int, array{
     *     project: array{name: string, slug: string, type: string, version: string, description: string},
     *     changelog: array{project_version: string, changelog_content: string}
     * }>
     */
    private const SeedData = [
        [
            'project' => [
                'name' => 'Velocity Core',
                'slug' => 'velocity-core',
                'type' => 'project_internal',
                'version' => '1.0.0',
                'description' => 'Core internal platform package.',
            ],
            'changelog' => [
                'project_version' => '1.0.0',
                'changelog_content' => 'Initial release with dashboard foundation and shared API utilities.',
            ],
        ],
        [
            'project' => [
                'name' => 'Velocity Theme',
                'slug' => 'velocity-theme',
                'type' => 'wp_theme',
                'version' => '2.3.0',
                'description' => 'Primary WordPress theme package.',
            ],
            'changelog' => [
                'project_version' => '2.3.0',
                'changelog_content' => 'Improved homepage sections, updated template compatibility, and fixed mobile menu styling.',
            ],
        ],
        [
            'project' => [
                'name' => 'Velocity Addons',
                'slug' => 'velocity-addons',
                'type' => 'wp_plugin',
                'version' => '3.1.0',
                'description' => 'Plugin bundle for client feature extensions.',
            ],
            'changelog' => [
                'project_version' => '3.1.0',
                'changelog_content' => 'Added shortcode settings, improved admin validation, and patched export compatibility.',
            ],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect(self::SeedData)->each(function (array $seedItem): void {
            $project = Project::firstOrCreate(
                ['slug' => $seedItem['project']['slug']],
                $seedItem['project'],
            );

            if ($project->version !== $seedItem['project']['version']) {
                $project->update(['version' => $seedItem['project']['version']]);
            }

            ProjectChangelog::firstOrCreate(
                [
                    'project_id' => $project->id,
                    'project_version' => $seedItem['changelog']['project_version'],
                ],
                ['changelog_content' => $seedItem['changelog']['changelog_content']],
            );
        });
    }
}
