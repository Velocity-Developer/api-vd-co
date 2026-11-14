<?php

use App\Models\Project;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('sync github command updates changed public and private projects and skips current ones', function () {
    Storage::fake('public');
    config(['services.github.token' => 'test-token']);

    $public = Project::factory()->create([
        'name' => 'Public Plugin',
        'github_url' => 'https://github.com/example/public-plugin',
        'version' => '1.0.0',
    ]);
    $private = Project::factory()->create([
        'name' => 'Private Plugin',
        'github_url' => 'https://github.com/example/private-plugin',
        'version' => '1.0.0',
        'package_external_url' => null,
    ]);
    $current = Project::factory()->create([
        'name' => 'Current Theme',
        'github_url' => 'https://github.com/example/current-theme',
        'version' => '3.0.0',
        'package_external_url' => 'https://downloads.example.com/current.zip',
    ]);

    Http::fake([
        'https://api.github.com/repos/example/public-plugin' => Http::response(['private' => false], 200),
        'https://api.github.com/repos/example/public-plugin/releases/latest' => Http::response([
            'tag_name' => 'v1.1.0',
            'assets' => [['id' => 11, 'name' => 'public-plugin.zip', 'browser_download_url' => 'https://github.com/example/public-plugin/releases/download/v1.1.0/public-plugin.zip']],
        ], 200),
        'https://api.github.com/repos/example/private-plugin' => Http::response(['private' => true], 200),
        'https://api.github.com/repos/example/private-plugin/releases/latest' => Http::response([
            'tag_name' => 'v2.0.0',
            'assets' => [['id' => 22, 'name' => 'private-plugin.zip', 'browser_download_url' => 'https://github.com/example/private-plugin/releases/download/v2.0.0/private-plugin.zip']],
        ], 200),
        'https://api.github.com/repos/example/private-plugin/releases/assets/22' => Http::response("PK\x03\x04".str_repeat('z', 40), 200),
        'https://api.github.com/repos/example/current-theme/releases/latest' => Http::response([
            'tag_name' => 'v3.0.0',
            'assets' => [['id' => 33, 'name' => 'current-theme.zip', 'browser_download_url' => 'https://github.com/x/current-theme.zip']],
        ], 200),
    ]);

    $this->artisan('projects:sync-github')
        ->expectsOutputToContain('1 unchanged')
        ->assertSuccessful();

    expect($public->refresh()->version)->toBe('1.1.0')
        ->and($public->package_external_url)->toBe('https://github.com/example/public-plugin/releases/download/v1.1.0/public-plugin.zip')
        ->and($private->refresh()->version)->toBe('2.0.0')
        ->and($private->package_file)->toBe('project-packages/private-plugin/private-plugin.zip')
        ->and($current->refresh()->package_external_url)->toBe('https://downloads.example.com/current.zip');
});

test('sync github runs hourly from the scheduler', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'projects:sync-github'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('17 * * * *');
});

test('sync github command skips repositories the token cannot access without failing', function () {
    Project::factory()->create([
        'name' => 'Hidden Theme',
        'github_url' => 'https://github.com/example/hidden-theme',
        'version' => '1.0.0',
    ]);

    Http::fake([
        'https://api.github.com/repos/example/hidden-theme/releases/latest' => Http::response(['message' => 'Not Found'], 404),
    ]);

    $this->artisan('projects:sync-github')
        ->expectsOutputToContain('Skipped Hidden Theme')
        ->expectsOutputToContain('1 skipped, 0 failed')
        ->assertSuccessful();
});
