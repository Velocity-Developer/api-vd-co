<?php

use App\Models\Media;
use App\Models\MediaCategory;
use App\Models\MediaTag;
use App\Models\User;

function mediaApiHeaders(): array
{
    return ['signature' => md5(now()->format('dmY'))];
}

test('v1 media API requires a valid signature header', function () {
    $this->getJson('/api/v1/media')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Signature header is required.');

    $this->getJson('/api/v1/media', ['signature' => 'salah'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Signature header is invalid.');
});

test('v1 media API lists media newest first with public url, categories and tags', function () {
    $user = User::factory()->create(['name' => 'Rahasia Admin']);
    $older = Media::factory()->create(['created_at' => now()->subDay(), 'created_by' => $user->id]);
    $newer = Media::factory()->create(['title' => 'Banner Promo', 'caption' => 'Caption promo']);
    $newer->categories()->attach(MediaCategory::factory()->create(['name' => 'Produk', 'slug' => 'produk']));
    $newer->tags()->attach(MediaTag::factory()->create(['name' => 'Promo', 'slug' => 'promo']));
    Media::factory()->create()->delete();

    $this->getJson('/api/v1/media', mediaApiHeaders())
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.0.title', 'Banner Promo')
        ->assertJsonPath('data.0.caption', 'Caption promo')
        ->assertJsonPath('data.0.url', asset('storage/'.$newer->path))
        ->assertJsonPath('data.0.categories.0.slug', 'produk')
        ->assertJsonPath('data.0.tags.0.slug', 'promo')
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonMissingPath('data.1.creator')
        ->assertDontSee('Rahasia Admin');
});

test('v1 media API filters by category slug including subcategories, tag, type and search', function () {
    $produk = MediaCategory::factory()->create(['slug' => 'produk']);
    $banner = MediaCategory::factory()->create(['slug' => 'banner', 'parent_id' => $produk->id]);
    $promo = MediaTag::factory()->create(['slug' => 'promo']);

    $inProduk = Media::factory()->create(['original_name' => 'produk.jpg']);
    $inBanner = Media::factory()->create(['original_name' => 'banner.jpg']);
    $video = Media::factory()->create(['original_name' => 'profil.mp4', 'mime_type' => 'video/mp4']);
    Media::factory()->document()->create(['original_name' => 'brosur.pdf']);

    $inProduk->categories()->attach($produk);
    $inBanner->categories()->attach($banner);
    $inBanner->tags()->attach($promo);
    $video->tags()->attach($promo);

    $names = fn (string $query): array => collect(
        $this->getJson("/api/v1/media?{$query}", mediaApiHeaders())->assertOk()->json('data'),
    )->pluck('original_name')->sort()->values()->all();

    expect($names('category=produk'))->toBe(['banner.jpg', 'produk.jpg'])
        ->and($names('category=banner'))->toBe(['banner.jpg'])
        ->and($names('category=tidak-ada'))->toBe([])
        ->and($names('tag=promo'))->toBe(['banner.jpg', 'profil.mp4'])
        ->and($names('tag=promo&type=video'))->toBe(['profil.mp4'])
        ->and($names('type=document'))->toBe(['brosur.pdf'])
        ->and($names('search=brosur'))->toBe(['brosur.pdf']);
});

test('v1 media API paginates and validates query parameters as json', function () {
    Media::factory()->count(3)->create();

    $this->getJson('/api/v1/media?per_page=2&page=2', mediaApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson('/api/v1/media?type=audio&per_page=500', mediaApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'per_page']);
});

test('v1 gallery API requires a valid signature header', function () {
    $this->getJson('/api/v1/gallery')->assertUnauthorized();
    $this->getJson('/api/v1/gallery', ['signature' => 'salah'])->assertForbidden();
});

test('v1 gallery API lists only images, newest first, with the media filters', function () {
    $produk = MediaCategory::factory()->create(['slug' => 'produk']);
    $promo = MediaTag::factory()->create(['slug' => 'promo']);

    $older = Media::factory()->create(['original_name' => 'lama.jpg', 'created_at' => now()->subDay()]);
    $newer = Media::factory()->create(['original_name' => 'baru.png', 'mime_type' => 'image/png', 'metadata' => ['width' => 800, 'height' => 600]]);
    $video = Media::factory()->create(['original_name' => 'video.mp4', 'mime_type' => 'video/mp4']);
    Media::factory()->document()->create(['original_name' => 'brosur.pdf']);
    Media::factory()->create(['original_name' => 'terhapus.jpg'])->delete();

    $newer->categories()->attach($produk);
    $newer->tags()->attach($promo);
    $video->categories()->attach($produk);

    $this->getJson('/api/v1/gallery', mediaApiHeaders())
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.0.url', asset('storage/'.$newer->path))
        ->assertJsonPath('data.0.metadata', ['width' => 800, 'height' => 600])
        ->assertJsonPath('data.1.id', $older->id);

    $names = fn (string $query): array => collect(
        $this->getJson("/api/v1/gallery?{$query}", mediaApiHeaders())->assertOk()->json('data'),
    )->pluck('original_name')->sort()->values()->all();

    expect($names('category=produk'))->toBe(['baru.png'])
        ->and($names('tag=promo'))->toBe(['baru.png'])
        ->and($names('search=lama'))->toBe(['lama.jpg'])
        ->and($names('type=video'))->toBe(['baru.png', 'lama.jpg']);

    $this->getJson('/api/v1/gallery?per_page=1&page=2', mediaApiHeaders())
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson('/api/v1/gallery?per_page=500', mediaApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
});
