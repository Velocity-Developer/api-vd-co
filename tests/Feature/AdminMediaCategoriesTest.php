<?php

use App\Models\Media;
use App\Models\MediaCategory;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot manage media categories', function () {
    $this->get(route('media-categories'))->assertRedirect(route('login'));
    $this->post('/ajax/media-categories', ['name' => 'Banner', 'slug' => 'banner'])->assertRedirect(route('login'));

    expect(MediaCategory::count())->toBe(0);
});

test('authenticated users can visit the media categories page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('media-categories'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('MediaCategories'));
});

test('media categories are listed by name with parent and media count', function () {
    $this->actingAs(User::factory()->create());
    $parent = MediaCategory::factory()->create(['name' => 'Produk', 'slug' => 'produk']);
    $child = MediaCategory::factory()->create(['name' => 'Banner', 'slug' => 'banner', 'parent_id' => $parent->id]);
    $media = Media::factory()->count(2)->create();
    $child->media()->attach($media->pluck('id'));
    $media->first()->delete();

    $this->getJson('/ajax/media-categories')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.name', 'Banner')
        ->assertJsonPath('data.0.parent.name', 'Produk')
        ->assertJsonPath('data.0.media_count', 1)
        ->assertJsonPath('data.1.name', 'Produk')
        ->assertJsonPath('data.1.parent', null);

    $this->getJson('/ajax/media-categories?all=1')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('meta');
});

test('users can create update and delete media categories', function () {
    $this->actingAs(User::factory()->create());
    $parent = MediaCategory::factory()->create(['name' => 'Produk', 'slug' => 'produk']);

    $this->postJson('/ajax/media-categories', [
        'name' => 'Foto Produk',
        'slug' => 'foto-produk',
        'description' => 'Foto katalog',
        'parent_id' => $parent->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'foto-produk')
        ->assertJsonPath('data.parent.id', $parent->id)
        ->assertJsonPath('data.media_count', 0);

    $category = MediaCategory::where('slug', 'foto-produk')->firstOrFail();
    $media = Media::factory()->create();
    $category->media()->attach($media);

    $this->patchJson("/ajax/media-categories/{$category->id}", [
        'name' => 'Foto Katalog',
        'slug' => 'foto-produk',
        'description' => null,
        'parent_id' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Foto Katalog')
        ->assertJsonPath('data.parent_id', null)
        ->assertJsonPath('data.media_count', 1);

    $this->deleteJson("/ajax/media-categories/{$category->id}")->assertNoContent();

    $this->assertModelMissing($category);
    $this->assertModelExists($media);
    $this->assertDatabaseMissing('media_media_category', ['media_id' => $media->id]);
});

test('deleting a parent category moves its children to the top level', function () {
    $this->actingAs(User::factory()->create());
    $parent = MediaCategory::factory()->create();
    $child = MediaCategory::factory()->create(['parent_id' => $parent->id]);

    $this->deleteJson("/ajax/media-categories/{$parent->id}")->assertNoContent();

    expect($child->fresh()->parent_id)->toBeNull();
});

test('media category input is validated with json errors', function () {
    $this->actingAs(User::factory()->create());
    MediaCategory::factory()->create(['slug' => 'banner']);

    $this->post('/ajax/media-categories', ['name' => '', 'slug' => 'banner', 'parent_id' => 999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'slug', 'parent_id']);

    $this->post('/ajax/media-categories', ['name' => 'Salah', 'slug' => 'ada spasi'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

test('a media category cannot become its own ancestor', function () {
    $this->actingAs(User::factory()->create());
    $root = MediaCategory::factory()->create();
    $child = MediaCategory::factory()->create(['parent_id' => $root->id]);
    $grandchild = MediaCategory::factory()->create(['parent_id' => $child->id]);

    foreach ([$root->id, $child->id, $grandchild->id] as $parentId) {
        $this->patch("/ajax/media-categories/{$root->id}", [
            'name' => $root->name,
            'slug' => $root->slug,
            'parent_id' => $parentId,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    }

    $this->patch("/ajax/media-categories/{$grandchild->id}", [
        'name' => $grandchild->name,
        'slug' => $grandchild->slug,
        'parent_id' => $root->id,
    ])->assertOk();

    expect($root->fresh()->parent_id)->toBeNull();
});
