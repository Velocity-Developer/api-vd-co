<?php

use App\Models\Media;
use App\Models\MediaTag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot visit the admin media page', function () {
    $this->get(route('media'))->assertRedirect(route('login'));
    $this->getJson('/ajax/media')->assertRedirect(route('login'));
});

test('authenticated users can visit the admin media page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('media'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Media'));
});

test('media index lists uploaded media newest first with public url', function () {
    $user = User::factory()->create();
    $older = Media::factory()->create(['created_by' => $user->id, 'created_at' => now()->subDay()]);
    $newer = Media::factory()->create();
    $tag = MediaTag::factory()->create(['name' => 'Banner', 'slug' => 'banner']);
    $newer->tags()->attach($tag);

    $this->actingAs($user)
        ->getJson('/ajax/media')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.0.url', asset('storage/'.$newer->path))
        ->assertJsonPath('data.0.tags.0.name', 'Banner')
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('data.1.creator.name', $user->name)
        ->assertJsonPath('meta.total', 2);
});

test('media index can be searched and filtered by type', function () {
    Media::factory()->create(['original_name' => 'logo-klien.png', 'mime_type' => 'image/png']);
    Media::factory()->create(['original_name' => 'profil-video.mp4', 'mime_type' => 'video/mp4']);
    Media::factory()->document()->create(['original_name' => 'company-profile.pdf']);

    $this->actingAs(User::factory()->create());

    $this->getJson('/ajax/media?search=logo')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.original_name', 'logo-klien.png');

    $this->getJson('/ajax/media?type=video')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.original_name', 'profil-video.mp4');

    $this->getJson('/ajax/media?type=document')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.original_name', 'company-profile.pdf');

    $this->getJson('/ajax/media?type=unknown')->assertSessionHasErrors('type');
});

test('soft deleted media is hidden from the gallery', function () {
    Media::factory()->create()->delete();

    $this->actingAs(User::factory()->create())
        ->getJson('/ajax/media')
        ->assertJsonCount(0, 'data');
});

test('users can upload multiple files to the media library', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/ajax/media', [
        'files' => [
            UploadedFile::fake()->image('banner.jpg', 1200, 630),
            UploadedFile::fake()->create('brosur.pdf', 120, 'application/pdf'),
        ],
    ]);

    $response->assertCreated()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.original_name', 'banner.jpg')
        ->assertJsonPath('data.0.metadata', ['width' => 1200, 'height' => 630])
        ->assertJsonPath('data.0.creator.name', $user->name)
        ->assertJsonPath('data.1.original_name', 'brosur.pdf')
        ->assertJsonPath('data.1.metadata', null);

    $media = Media::where('original_name', 'banner.jpg')->firstOrFail();

    expect($media->created_by)->toBe($user->id)
        ->and($media->path)->toStartWith('media/'.now()->format('Y/m').'/');
    Storage::disk('public')->assertExists($media->path);
});

test('media upload rejects disallowed files with a json validation error', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());

    $this->post('/ajax/media', ['files' => [UploadedFile::fake()->create('skrip.php', 1, 'application/x-php')]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files.0']);

    $this->post('/ajax/media', ['files' => [UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml')]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files.0']);

    $this->post('/ajax/media', ['files' => [UploadedFile::fake()->create('besar.pdf', 20 * 1024 + 1, 'application/pdf')]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files.0']);

    $this->post('/ajax/media', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files']);

    expect(Media::count())->toBe(0);
});

test('users can delete media together with its file', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());

    $media = Media::createFromUpload(UploadedFile::fake()->image('hapus.jpg'));
    Storage::disk('public')->assertExists($media->path);

    $this->delete("/ajax/media/{$media->id}")->assertNoContent();

    Storage::disk('public')->assertMissing($media->path);
    $this->assertSoftDeleted($media);
    $this->getJson('/ajax/media')->assertJsonCount(0, 'data');
});

test('guests cannot upload or delete media', function () {
    $media = Media::factory()->create();

    $this->post('/ajax/media', ['files' => [UploadedFile::fake()->image('a.jpg')]])->assertRedirect(route('login'));
    $this->delete("/ajax/media/{$media->id}")->assertRedirect(route('login'));

    $this->assertNotSoftDeleted($media);
});

test('users can update and clear the media caption', function () {
    $this->actingAs(User::factory()->create());
    $media = Media::factory()->create(['caption' => null, 'title' => 'Judul Tetap']);

    $this->patch("/ajax/media/{$media->id}", ['caption' => 'Suasana kantor Velocity'])
        ->assertOk()
        ->assertJsonPath('data.caption', 'Suasana kantor Velocity')
        ->assertJsonPath('data.title', 'Judul Tetap');

    expect($media->fresh()->caption)->toBe('Suasana kantor Velocity');

    $this->patch("/ajax/media/{$media->id}", ['caption' => ''])
        ->assertOk()
        ->assertJsonPath('data.caption', null);
});

test('media update only accepts editable fields with a json validation error', function () {
    $this->actingAs(User::factory()->create());
    $media = Media::factory()->create(['path' => 'media/2026/09/asli.jpg']);

    $this->patch("/ajax/media/{$media->id}", ['caption' => str_repeat('a', 1001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['caption']);

    $this->patch("/ajax/media/{$media->id}", ['path' => 'media/lain.jpg'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media']);

    expect($media->fresh()->path)->toBe('media/2026/09/asli.jpg');
});

test('guests cannot update media captions', function () {
    $media = Media::factory()->create(['caption' => null]);

    $this->patch("/ajax/media/{$media->id}", ['caption' => 'Tidak boleh'])->assertRedirect(route('login'));

    expect($media->fresh()->caption)->toBeNull();
});

test('users can set media tags creating new tags and reusing existing ones by slug', function () {
    $this->actingAs(User::factory()->create());
    $existing = MediaTag::factory()->create(['name' => 'Promo', 'slug' => 'promo']);
    $media = Media::factory()->create(['caption' => 'Tetap']);

    $this->patch("/ajax/media/{$media->id}", ['tags' => [' promo ', 'Banner  Utama', 'PROMO', '!!!']])
        ->assertOk()
        ->assertJsonCount(2, 'data.tags')
        ->assertJsonPath('data.tags.0.id', $existing->id)
        ->assertJsonPath('data.tags.1.name', 'Banner Utama')
        ->assertJsonPath('data.tags.1.slug', 'banner-utama')
        ->assertJsonPath('data.caption', 'Tetap');

    expect(MediaTag::count())->toBe(2);

    $this->patch("/ajax/media/{$media->id}", ['tags' => ['Banner Utama']])
        ->assertOk()
        ->assertJsonCount(1, 'data.tags');

    $this->patch("/ajax/media/{$media->id}", ['tags' => []])
        ->assertOk()
        ->assertJsonCount(0, 'data.tags');

    expect(MediaTag::count())->toBe(2)
        ->and($media->fresh()->caption)->toBe('Tetap');
});

test('media tags are validated with json errors', function () {
    $this->actingAs(User::factory()->create());
    $media = Media::factory()->create();

    $this->patch("/ajax/media/{$media->id}", ['tags' => [str_repeat('a', 51)]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tags.0']);

    $this->patch("/ajax/media/{$media->id}", ['tags' => array_map(fn (int $i): string => "tag {$i}", range(1, 31))])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tags']);

    expect($media->tags()->count())->toBe(0);
});

test('users can update and clear the media title without touching other fields', function () {
    $this->actingAs(User::factory()->create());
    $media = Media::factory()->create(['title' => 'Judul Lama', 'caption' => 'Caption tetap']);

    $this->patch("/ajax/media/{$media->id}", ['title' => 'Banner Promo September'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Banner Promo September')
        ->assertJsonPath('data.caption', 'Caption tetap');

    $this->patch("/ajax/media/{$media->id}", ['title' => ''])
        ->assertOk()
        ->assertJsonPath('data.title', null);

    $this->patch("/ajax/media/{$media->id}", ['title' => str_repeat('a', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);

    expect($media->fresh())
        ->title->toBeNull()
        ->caption->toBe('Caption tetap');
});
