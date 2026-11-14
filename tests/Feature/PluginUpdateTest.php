<?php

use App\Models\Project;
use App\Models\ProjectChangelog;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function forkBb(array $isi = []): Project
{
    return Project::factory()->create($isi + [
        'name' => 'Beaver Builder (fork Velocity)',
        'slug' => 'bb-plugin',
        'type' => 'wp_plugin',
        'github_url' => 'https://github.com/Velocity-Developer/bb-plugin-vd',
        'package_external_url' => null,
        'paket_privat' => true,
        'version' => '2.11.2.1.2',
        'package_file' => 'paket-privat/bb/bb-plugin-2.11.2.1.2.zip',
        'versi_stabil' => '2.11.2.1.1',
        'paket_stabil' => 'paket-privat/bb/bb-plugin-2.11.2.1.1.zip',
        'domain_uji' => ['uji.velocitydeveloper.com'],
        'requires_wp' => '6.0',
        'requires_php' => '7.4',
    ]);
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    Storage::disk('local')->put('paket-privat/bb/bb-plugin-2.11.2.1.2.zip', 'PK-uji');
    Storage::disk('local')->put('paket-privat/bb/bb-plugin-2.11.2.1.1.zip', 'PK-stabil');
    Server::factory()->create(['server_ip' => '127.0.0.1']);
});

test('situs di server terdaftar mendapat rilis stabil, domain uji mendapat rilis terbaru', function () {
    $project = forkBb();
    ProjectChangelog::create(['project_id' => $project->id, 'project_version' => '2.11.2.1.1', 'changelog_content' => '<p>Backport security 2.12</p>']);

    $stabil = $this->getJson('/api/v1/plugin-updates?slugs=bb-plugin,bb-theme-builder&source=www.klien.com')->assertOk();
    expect($stabil->json('data.bb-plugin'))->version->toBe('2.11.2.1.1')->kanal->toBe('stabil')
        ->changelog->toBe('2.11.2.1.1: Backport security 2.12')
        ->and($stabil->json('data.bb-theme-builder'))->toBeNull();

    $uji = $this->getJson('/api/v1/plugin-updates?slugs=bb-plugin', ['source' => 'https://www.uji.velocitydeveloper.com/'])->assertOk();
    expect($uji->json('data.bb-plugin'))->version->toBe('2.11.2.1.2')->kanal->toBe('uji');

    // tautan unduh bertanda tangan
    $this->get($stabil->json('data.bb-plugin.package'))->assertOk()->assertDownload('bb-plugin-2.11.2.1.1.zip');
    $this->get($uji->json('data.bb-plugin.package'))->assertOk();
});

test('tanpa rilis stabil tidak ada tawaran update untuk situs biasa', function () {
    forkBb(['versi_stabil' => null, 'paket_stabil' => null]);

    expect($this->getJson('/api/v1/plugin-updates?slugs=bb-plugin&source=klien.com')->assertOk()->json('data'))->toBe([]);
});

test('unduhan ditolak tanpa tanda tangan, dengan tanda tangan kedaluwarsa, atau versi yang tidak tersimpan', function () {
    forkBb();
    $tautan = $this->getJson('/api/v1/plugin-updates?slugs=bb-plugin&source=klien.com')->json('data.bb-plugin.package');

    $this->get('/api/v1/plugin-package/bb-plugin/2.11.2.1.1')->assertForbidden();
    $this->get(str_replace('2.11.2.1.1', '2.11.2.1.9', $tautan))->assertForbidden();
    $this->travel(61)->minutes();
    $this->get($tautan)->assertForbidden();
});

test('IP di luar server terdaftar ditolak', function () {
    forkBb();
    Server::query()->delete();

    $this->getJson('/api/v1/plugin-updates?slugs=bb-plugin')->assertForbidden();
});

test('paket privat tidak tampil di API publik dan tidak punya URL publik', function () {
    forkBb();
    $publik = Project::factory()->create(['slug' => 'velocity-addons', 'type' => 'wp_plugin', 'paket_privat' => false]);
    $tanda = ['signature' => md5(now()->format('dmY'))];

    $daftar = $this->getJson('/api/v1/plugins?per_page=100', $tanda)->assertOk()->json('data');
    expect(collect($daftar)->pluck('slug')->all())->toBe(['velocity-addons']);
    $this->getJson('/api/v1/project/bb-plugin', $tanda)->assertNotFound();
    $this->getJson('/api/v1/project/'.$publik->slug, $tanda)->assertOk();
});

test('sinkron rilis GitHub paket privat menyimpan zip di disk lokal dan mempertahankan paket stabil', function () {
    $project = forkBb(['version' => '2.11.2.1.1', 'package_file' => 'paket-privat/bb/bb-plugin-2.11.2.1.1.zip']);
    config(['services.github.token' => 'tkn']);
    Http::fake([
        'https://api.github.com/repos/Velocity-Developer/bb-plugin-vd' => Http::response(['private' => true]),
        'https://api.github.com/repos/Velocity-Developer/bb-plugin-vd/releases/latest' => Http::response([
            'tag_name' => 'v2.11.2.1.3',
            'assets' => [['id' => 7, 'name' => 'bb-plugin-2.11.2.1.3.zip', 'browser_download_url' => 'https://github.com/x.zip']],
        ]),
        'https://api.github.com/repos/Velocity-Developer/bb-plugin-vd/releases/assets/7' => Http::response("PK\x03\x04".str_repeat('z', 40)),
    ]);

    $this->artisan('projects:sync-github')->assertSuccessful();

    $project->refresh();
    expect($project->version)->toBe('2.11.2.1.3')
        ->and($project->package_file)->toBe('paket-privat/beaver-builder-fork-velocity/bb-plugin-2.11.2.1.3.zip')
        ->and(Storage::disk('local')->exists($project->package_file))->toBeTrue()
        ->and(Storage::disk('public')->exists($project->package_file))->toBeFalse()
        // paket stabil lama (2.11.2.1.1) tidak ikut terhapus walau package_file lama sama
        ->and(Storage::disk('local')->exists('paket-privat/bb/bb-plugin-2.11.2.1.1.zip'))->toBeTrue()
        ->and($project->versi_stabil)->toBe('2.11.2.1.1');
});

test('admin menaikkan rilis terbaru ke stabil dan mengatur domain uji', function () {
    $project = forkBb();
    $admin = User::factory()->create();

    $this->actingAs($admin)->postJson("/ajax/projects/{$project->id}/stabilkan")->assertOk()
        ->assertJsonPath('data.versi_stabil', '2.11.2.1.2');
    expect($project->refresh()->paket_stabil)->toBe('paket-privat/bb/bb-plugin-2.11.2.1.2.zip')
        ->and(Storage::disk('local')->exists('paket-privat/bb/bb-plugin-2.11.2.1.1.zip'))->toBeFalse();

    $this->actingAs($admin)->patchJson("/ajax/projects/{$project->id}", ['domain_uji' => "https://www.Demo1.com/\ndemo2.com, demo1.com"])->assertOk();
    expect($project->refresh()->domain_uji)->toBe(['demo1.com', 'demo2.com']);

    $biasa = Project::factory()->create(['paket_privat' => false]);
    $this->actingAs($admin)->postJson("/ajax/projects/{$biasa->id}/stabilkan")->assertStatus(422);
});

test('paket privat tidak bisa diisi unggahan manual atau URL luar', function () {
    $admin = User::factory()->create();
    $project = forkBb();

    $this->actingAs($admin)->patchJson("/ajax/projects/{$project->id}", ['package_external_url' => 'https://contoh.com/bb.zip'])->assertOk();
    expect($project->refresh()->package_external_url)->toBeNull();

    $this->actingAs($admin)->patchJson("/ajax/projects/{$project->id}", ['github_url' => null])->assertSessionHasErrors('github_url');
    expect($project->refresh()->github_url)->not->toBeNull();
});

test('paket privat tidak muncul di daftar TGM dan halaman changelog publik', function () {
    forkBb();
    Project::factory()->create(['slug' => 'velocity-addons', 'type' => 'wp_plugin', 'paket_privat' => false]);
    $tanda = ['signature' => md5(now()->format('dmY'))];

    $tgm = $this->getJson('/api/v1/tgm-plugins', $tanda)->assertOk()->json();
    expect(json_encode($tgm))->not->toContain('bb-plugin')->toContain('velocity-addons');
    $this->get('/project/changelog/bb-plugin')->assertNotFound();
    $this->get('/project/changelog/velocity-addons')->assertOk();
});

test('unggah zip manual ke project privat ditolak walau field paket_privat tidak dikirim', function () {
    $admin = User::factory()->create();
    $project = forkBb();
    Storage::fake('public');

    $this->actingAs($admin)->post("/ajax/projects/{$project->id}", [
        '_method' => 'PATCH',
        'package_file' => UploadedFile::fake()->create('bb.zip', 10, 'application/zip'),
    ])->assertSessionHasErrors('package_file');
    expect(Storage::disk('public')->allFiles())->toBe([])
        ->and($project->refresh()->package_file)->toBe('paket-privat/bb/bb-plugin-2.11.2.1.2.zip');

    $this->actingAs($admin)->patchJson("/ajax/projects/{$project->id}", ['type' => 'wp_theme'])->assertSessionHasErrors('type');
});

test('project privat yang dijadikan publik menghapus berkas privat dan kanal stabil', function () {
    $admin = User::factory()->create();
    $project = forkBb();

    $this->actingAs($admin)->patchJson("/ajax/projects/{$project->id}", ['paket_privat' => false])->assertOk();
    $project->refresh();
    expect($project->paket_privat)->toBeFalse()
        ->and($project->package_file)->toBeNull()
        ->and($project->versi_stabil)->toBeNull()
        ->and($project->paket_stabil)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
