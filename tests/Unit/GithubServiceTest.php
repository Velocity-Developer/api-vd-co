<?php

use App\Models\Project;
use App\Services\GithubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('github service syncs the latest release into the project version and package url', function () {
    $project = Project::factory()->create([
        'github_url' => 'https://github.com/example/velocity-addons',
        'version' => '1.0.0',
        'package_external_url' => null,
    ]);

    Http::fake([
        'https://api.github.com/repos/example/velocity-addons' => Http::response([
            'private' => false,
        ], 200),
        'https://api.github.com/repos/example/velocity-addons/releases/latest' => Http::response([
            'tag_name' => 'v2.4.0',
            'name' => 'Velocity Addons 2.4.0',
            'published_at' => '2026-06-25T08:00:00Z',
            'zipball_url' => 'https://api.github.com/repos/example/velocity-addons/zipball/2.4.0',
            'assets' => [
                [
                    'name' => 'velocity-addons.zip',
                    'size' => 1200,
                    'browser_download_url' => 'https://github.com/example/velocity-addons/releases/download/2.4.0/velocity-addons.zip',
                ],
            ],
        ], 200),
    ]);

    $syncedProject = app(GithubService::class)->syncGithubProjectRelease($project->id);

    expect($syncedProject)->not->toBeNull();

    $project->refresh();

    expect($project->version)->toBe('2.4.0')
        ->and($project->package_external_url)->toBe('https://github.com/example/velocity-addons/releases/download/2.4.0/velocity-addons.zip');
});

test('github service uploads the latest release package for a private repository', function () {
    Storage::fake('public');

    config(['services.github.token' => 'test-token']);

    $project = Project::factory()->create([
        'name' => 'Velocity Addons',
        'github_url' => 'https://github.com/example/private-addons',
        'version' => '1.0.0',
        'package_file' => 'project-packages/velocity-addons/old-package.zip',
        'package_external_url' => 'https://downloads.example.com/original.zip',
    ]);

    Storage::disk('public')->put('project-packages/velocity-addons/old-package.zip', 'old package');

    Http::fake([
        'https://api.github.com/repos/example/private-addons' => Http::response([], 404),
        'https://api.github.com/repos/example/private-addons/releases/latest' => Http::response([
            'tag_name' => 'v2.5.0',
            'name' => 'Velocity Addons 2.5.0',
            'published_at' => '2026-06-25T08:00:00Z',
            'assets' => [
                [
                    'id' => 987654,
                    'name' => 'velocity-addons-private.zip',
                    'size' => 1500,
                    'browser_download_url' => 'https://github.com/example/private-addons/releases/download/2.5.0/velocity-addons-private.zip',
                ],
            ],
        ], 200),
        'https://api.github.com/repos/example/private-addons/releases/assets/987654' => Http::response("PK\x03\x04".str_repeat('z', 40), 200),
    ]);

    $syncedProject = app(GithubService::class)->syncGithubProjectRelease($project->id);

    expect($syncedProject)->not->toBeNull();

    $project->refresh();

    expect($project->version)->toBe('2.5.0')
        ->and($project->package_external_url)->toBeNull()
        ->and($project->package_file)->toBe('project-packages/velocity-addons/velocity-addons-private.zip');

    Storage::disk('public')->assertMissing('project-packages/velocity-addons/old-package.zip');
    Storage::disk('public')->assertExists('project-packages/velocity-addons/velocity-addons-private.zip');
    expect(Storage::disk('public')->get('project-packages/velocity-addons/velocity-addons-private.zip'))->toBe("PK\x03\x04".str_repeat('z', 40));

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://api.github.com/repos/example/private-addons/releases/assets/987654'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->hasHeader('Accept', 'application/octet-stream');
    });
});

test('github service does not sync a project without github url', function () {
    $project = Project::factory()->create([
        'github_url' => null,
        'version' => '1.0.0',
        'package_external_url' => 'https://downloads.example.com/original.zip',
    ]);

    Http::fake();

    $syncedProject = app(GithubService::class)->syncGithubProjectRelease($project->id);

    expect($syncedProject)->toBeNull();

    Http::assertNothingSent();

    $project->refresh();

    expect($project->version)->toBe('1.0.0')
        ->and($project->package_external_url)->toBe('https://downloads.example.com/original.zip');
});

test('github service keeps the project unchanged when latest release cannot be fetched', function () {
    $project = Project::factory()->create([
        'github_url' => 'https://github.com/example/velocity-addons',
        'version' => '1.0.0',
        'package_external_url' => 'https://downloads.example.com/original.zip',
    ]);

    Http::fake([
        'https://api.github.com/repos/example/velocity-addons' => Http::response([
            'private' => false,
        ], 200),
        'https://api.github.com/repos/example/velocity-addons/releases/latest' => Http::response([], 404),
    ]);

    $service = app(GithubService::class);
    $syncedProject = $service->syncGithubProjectRelease($project->id);

    expect($syncedProject)->toBeNull()
        ->and($service->lastSyncError())->toBe('Latest release not found or GitHub API failed.');

    $project->refresh();

    expect($project->version)->toBe('1.0.0')
        ->and($project->package_external_url)->toBe('https://downloads.example.com/original.zip');
});

test('github service returns specific error when private release asset id is missing', function () {
    Storage::fake('public');

    config(['services.github.token' => 'test-token']);

    $project = Project::factory()->create([
        'name' => 'Velocity Addons',
        'github_url' => 'https://github.com/example/private-addons',
        'version' => '1.0.0',
    ]);

    Http::fake([
        'https://api.github.com/repos/example/private-addons' => Http::response([], 404),
        'https://api.github.com/repos/example/private-addons/releases/latest' => Http::response([
            'tag_name' => 'v2.5.0',
            'assets' => [
                [
                    'name' => 'velocity-addons-private.zip',
                    'size' => 1500,
                ],
            ],
        ], 200),
    ]);

    $service = app(GithubService::class);
    $syncedProject = $service->syncGithubProjectRelease($project->id);

    expect($syncedProject)->toBeNull()
        ->and($service->lastSyncError())->toBe('Release asset ID not found.');
});

test('github service returns specific error when private asset download fails', function () {
    Storage::fake('public');

    config(['services.github.token' => 'test-token']);

    $project = Project::factory()->create([
        'name' => 'Velocity Addons',
        'github_url' => 'https://github.com/example/private-addons',
        'version' => '1.0.0',
    ]);

    Http::fake([
        'https://api.github.com/repos/example/private-addons' => Http::response([], 404),
        'https://api.github.com/repos/example/private-addons/releases/latest' => Http::response([
            'tag_name' => 'v2.5.0',
            'assets' => [
                [
                    'id' => 987654,
                    'name' => 'velocity-addons-private.zip',
                    'size' => 1500,
                ],
            ],
        ], 200),
        'https://api.github.com/repos/example/private-addons/releases/assets/987654' => Http::response([], 403),
    ]);

    $service = app(GithubService::class);
    $syncedProject = $service->syncGithubProjectRelease($project->id);

    expect($syncedProject)->toBeNull()
        ->and($service->lastSyncError())->toBe('GitHub asset download asset id 987654 failed with status 403.');
});

test('github service identifies a repository as private when github returns 404', function () {
    Http::fake([
        'https://api.github.com/repos/example/private-repo' => Http::response([], 404),
    ]);

    expect(app(GithubService::class)->isRepositoryPrivate('example', 'private-repo'))->toBeTrue();
});

test('github service reads the private flag with the token to avoid anonymous rate limits', function () {
    config(['services.github.token' => 'test-token']);

    Http::fake([
        'https://api.github.com/repos/example/private-repo' => Http::response(['private' => true], 200),
        'https://api.github.com/repos/example/public-repo' => Http::response(['private' => false], 200),
    ]);

    $service = app(GithubService::class);

    expect($service->isRepositoryPrivate('example', 'private-repo'))->toBeTrue()
        ->and($service->isRepositoryPrivate('example', 'public-repo'))->toBeFalse();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.github.com/repos/example/public-repo'
        && $request->hasHeader('Authorization', 'Bearer test-token'));
});

test('github service does not touch the project when visibility cannot be determined', function () {
    $project = Project::factory()->create([
        'github_url' => 'https://github.com/example/velocity-addons',
        'version' => '1.0.0',
        'package_external_url' => 'https://downloads.example.com/original.zip',
    ]);

    Http::fake([
        'https://api.github.com/repos/example/velocity-addons' => Http::response(['message' => 'API rate limit exceeded'], 403),
        'https://api.github.com/repos/example/velocity-addons/releases/latest' => Http::response(['tag_name' => 'v2.0.0', 'assets' => []], 200),
    ]);

    $service = app(GithubService::class);

    expect($service->repositoryVisibility('example', 'velocity-addons'))->toBeNull()
        ->and($service->syncGithubProjectRelease($project->id))->toBeNull()
        ->and($service->lastSyncError())->toBe('GitHub repository visibility check failed.');

    $project->refresh();

    expect($project->version)->toBe('1.0.0')
        ->and($project->package_external_url)->toBe('https://downloads.example.com/original.zip');
});

test('github service skips a project whose version is already current when asked', function () {
    $project = Project::factory()->create([
        'github_url' => 'https://github.com/example/velocity-addons',
        'version' => '2.4.0',
        'package_external_url' => 'https://downloads.example.com/current.zip',
    ]);

    Http::fake([
        'https://api.github.com/repos/example/velocity-addons/releases/latest' => Http::response([
            'tag_name' => 'v2.4.0',
            'assets' => [['id' => 1, 'name' => 'a.zip', 'browser_download_url' => 'https://github.com/x/a.zip']],
        ], 200),
    ]);

    $service = app(GithubService::class);

    expect($service->syncGithubProjectRelease($project->id, skipIfCurrent: true))->not->toBeNull()
        ->and($service->lastSyncUnchanged())->toBeTrue();

    Http::assertSentCount(1);

    expect($project->refresh()->package_external_url)->toBe('https://downloads.example.com/current.zip');
});

test('github service keeps the old package when the downloaded asset is not a zip', function () {
    Storage::fake('public');
    config(['services.github.token' => 'test-token']);

    $project = Project::factory()->create([
        'name' => 'Velocity Addons',
        'github_url' => 'https://github.com/example/private-addons',
        'version' => '1.0.0',
        'package_file' => 'project-packages/velocity-addons/old-package.zip',
    ]);
    Storage::disk('public')->put('project-packages/velocity-addons/old-package.zip', 'old package');

    Http::fake([
        'https://api.github.com/repos/example/private-addons' => Http::response(['private' => true], 200),
        'https://api.github.com/repos/example/private-addons/releases/latest' => Http::response([
            'tag_name' => 'v2.5.0',
            'assets' => [['id' => 987654, 'name' => 'velocity-addons-private.zip']],
        ], 200),
        'https://api.github.com/repos/example/private-addons/releases/assets/987654' => Http::response('<html>login</html>', 200),
    ]);

    $service = app(GithubService::class);

    expect($service->syncGithubProjectRelease($project->id))->toBeNull()
        ->and($service->lastSyncError())->toBe('GitHub asset id 987654 is not a valid zip file.');

    expect($project->refresh()->version)->toBe('1.0.0')
        ->and($project->package_file)->toBe('project-packages/velocity-addons/old-package.zip');
    Storage::disk('public')->assertExists('project-packages/velocity-addons/old-package.zip');
});

test('github service clears a stored package when a public release is synced', function () {
    Storage::fake('public');

    $project = Project::factory()->create([
        'name' => 'Velocity Addons',
        'github_url' => 'https://github.com/example/velocity-addons',
        'version' => '1.0.0',
        'package_file' => 'project-packages/velocity-addons/old-package.zip',
    ]);
    Storage::disk('public')->put('project-packages/velocity-addons/old-package.zip', 'old package');

    Http::fake([
        'https://api.github.com/repos/example/velocity-addons' => Http::response(['private' => false], 200),
        'https://api.github.com/repos/example/velocity-addons/releases/latest' => Http::response([
            'tag_name' => 'v2.4.0',
            'assets' => [['id' => 1, 'name' => 'a.zip', 'browser_download_url' => 'https://github.com/example/velocity-addons/releases/download/v2.4.0/a.zip']],
        ], 200),
    ]);

    expect(app(GithubService::class)->syncGithubProjectRelease($project->id))->not->toBeNull();

    expect($project->refresh()->package_file)->toBeNull()
        ->and($project->package_external_url)->toBe('https://github.com/example/velocity-addons/releases/download/v2.4.0/a.zip');
    Storage::disk('public')->assertMissing('project-packages/velocity-addons/old-package.zip');
});

test('github service uses the owner specific token when one is configured', function () {
    config([
        'services.github.token' => 'org-token',
        'services.github.owner_tokens' => ['velocitydeveloper' => 'personal-token'],
    ]);

    Http::fake([
        'https://api.github.com/repos/VelocityDeveloper/velocity-sekolah' => Http::response(['private' => true], 200),
        'https://api.github.com/repos/Velocity-Developer/velocity-addons' => Http::response(['private' => false], 200),
    ]);

    $service = app(GithubService::class);
    $service->repositoryVisibility('VelocityDeveloper', 'velocity-sekolah');
    $service->repositoryVisibility('Velocity-Developer', 'velocity-addons');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.github.com/repos/VelocityDeveloper/velocity-sekolah'
        && $request->hasHeader('Authorization', 'Bearer personal-token'));
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.github.com/repos/Velocity-Developer/velocity-addons'
        && $request->hasHeader('Authorization', 'Bearer org-token'));
});
