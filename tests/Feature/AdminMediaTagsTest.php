<?php

use App\Models\Media;
use App\Models\MediaTag;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot manage media tags', function () {
    $this->get(route('media-tags'))->assertRedirect(route('login'));
    $this->post('/ajax/media-tags', ['name' => 'Promo', 'slug' => 'promo'])->assertRedirect(route('login'));

    expect(MediaTag::count())->toBe(0);
});

test('authenticated users can visit the media tags page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('media-tags'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('MediaTags'));
});

test('media tags are listed by name with media count and can be searched', function () {
    $this->actingAs(User::factory()->create());
    $promo = MediaTag::factory()->create(['name' => 'Promo', 'slug' => 'promo']);
    MediaTag::factory()->create(['name' => 'Banner', 'slug' => 'banner']);
    $media = Media::factory()->count(2)->create();
    $promo->media()->attach($media->pluck('id'));
    $media->first()->delete();

    $this->getJson('/ajax/media-tags')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.name', 'Banner')
        ->assertJsonPath('data.0.media_count', 0)
        ->assertJsonPath('data.1.name', 'Promo')
        ->assertJsonPath('data.1.media_count', 1);

    $this->getJson('/ajax/media-tags?search=pro')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'promo');
});

test('users can create update and delete media tags', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson('/ajax/media-tags', ['name' => 'Promo Akhir Tahun', 'slug' => 'promo-akhir-tahun'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'promo-akhir-tahun')
        ->assertJsonPath('data.media_count', 0);

    $tag = MediaTag::where('slug', 'promo-akhir-tahun')->firstOrFail();
    $media = Media::factory()->create();
    $tag->media()->attach($media);

    $this->patchJson("/ajax/media-tags/{$tag->id}", ['name' => 'Promo Tahunan', 'slug' => 'promo-tahunan'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Promo Tahunan')
        ->assertJsonPath('data.media_count', 1);

    $this->deleteJson("/ajax/media-tags/{$tag->id}")->assertNoContent();

    $this->assertModelMissing($tag);
    $this->assertModelExists($media);
    $this->assertDatabaseMissing('media_media_tag', ['media_id' => $media->id]);
});

test('media tag input is validated with json errors', function () {
    $this->actingAs(User::factory()->create());
    $existing = MediaTag::factory()->create(['slug' => 'promo']);

    $this->post('/ajax/media-tags', ['name' => str_repeat('a', 51), 'slug' => 'promo'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'slug']);

    $this->post('/ajax/media-tags', ['name' => 'Salah', 'slug' => 'ada spasi'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);

    $this->patch("/ajax/media-tags/{$existing->id}", ['name' => 'Promo', 'slug' => 'promo'])->assertOk();
});
