<?php

use App\Models\Project;
use App\Models\ProjectChangelog;
use Database\Seeders\ProjectChangelogSeeder;
use Illuminate\Support\Facades\Schema;

test('project changelogs table has the expected columns', function () {
    expect(Schema::hasColumns('project_changelogs', [
        'id',
        'project_id',
        'project_version',
        'changelog_content',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('a project changelog belongs to a project', function () {
    $project = Project::factory()->create();
    $projectChangelog = ProjectChangelog::factory()->for($project)->create();

    expect($projectChangelog->project)
        ->toBeInstanceOf(Project::class)
        ->id->toBe($project->id)
        ->and($project->changelogs->first())
        ->toBeInstanceOf(ProjectChangelog::class)
        ->id->toBe($projectChangelog->id);
});

test('project changelog seeder creates records once', function () {
    $this->seed(ProjectChangelogSeeder::class);
    $this->seed(ProjectChangelogSeeder::class);

    expect(Project::whereIn('slug', [
        'velocity-core',
        'velocity-theme',
        'velocity-addons',
    ])->count())->toBe(3)
        ->and(ProjectChangelog::count())->toBe(3)
        ->and(ProjectChangelog::where('project_version', '1.0.0')->exists())->toBeTrue()
        ->and(ProjectChangelog::where('project_version', '2.3.0')->exists())->toBeTrue()
        ->and(ProjectChangelog::where('project_version', '3.1.0')->exists())->toBeTrue();
});
