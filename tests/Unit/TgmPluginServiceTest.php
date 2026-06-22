<?php

use App\Models\Project;
use App\Services\TgmPluginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('tgm plugin service returns wordpress plugins in tgm format', function () {
    Storage::fake('public');

    $localPlugin = Project::factory()->create([
        'name' => 'Beaver Builder',
        'slug' => 'bb-plugin',
        'type' => 'wp_plugin',
        'plugin_wp_required' => false,
        'version' => '2.10.0.7',
        'package_external_url' => 'https://api.velocitydeveloper.id/plugins/bb-plugin-standard.zip',
        'package_file' => 'project-packages/bb-plugin/bb-plugin-standard.zip',
    ]);

    $requiredPlugin = Project::factory()->create([
        'name' => 'Velocity Blocks',
        'slug' => 'velocity-blocks',
        'type' => 'wp_plugin',
        'plugin_wp_required' => true,
        'version' => null,
        'package_external_url' => null,
        'package_file' => 'project-packages/velocity-blocks/velocity-blocks.zip',
    ]);

    Project::factory()->create([
        'name' => 'Velocity Theme',
        'slug' => 'velocity-theme',
        'type' => 'wp_theme',
    ]);

    $plugins = app(TgmPluginService::class)->get();

    expect($plugins)
        ->toHaveCount(2)
        ->and($plugins[0])->toBe([
            'name' => $localPlugin->name,
            'slug' => $localPlugin->slug,
            'source' => 'https://api.velocitydeveloper.id/plugins/bb-plugin-standard.zip',
            'required' => false,
            'version' => '2.10.0.7',
            'force_activation' => false,
            'force_deactivation' => false,
            'external_url' => '',
            'is_callable' => '',
        ])
        ->and($plugins[1])->toBe([
            'name' => $requiredPlugin->name,
            'slug' => $requiredPlugin->slug,
            'source' => Storage::disk('public')->url('project-packages/velocity-blocks/velocity-blocks.zip'),
            'required' => true,
            'version' => '',
            'force_activation' => false,
            'force_deactivation' => false,
            'external_url' => '',
            'is_callable' => '',
        ]);
});
