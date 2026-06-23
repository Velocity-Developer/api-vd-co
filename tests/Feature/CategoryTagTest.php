<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\TagSeeder;
use Illuminate\Support\Facades\Schema;

test('categories and tags tables have the expected columns', function () {
    expect(Schema::hasColumns('categories', [
        'id',
        'name',
        'slug',
        'description',
        'export_counter',
        'created_at',
        'updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('tags', [
            'id',
            'name',
            'slug',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('post category pivot table has the expected columns', function () {
    expect(Schema::hasColumns('category_post', [
        'category_id',
        'post_id',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('post tag pivot table has the expected columns', function () {
    expect(Schema::hasColumns('post_tag', [
        'post_id',
        'tag_id',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('posts can be attached to categories and tags', function () {
    $post = Post::factory()->create();
    $category = Category::factory()->create();
    $tag = Tag::factory()->create();

    $post->categories()->attach($category);
    $post->tags()->attach($tag);

    expect($post->categories()->first())
        ->toBeInstanceOf(Category::class)
        ->id->toBe($category->id)
        ->and($post->tags()->first())
        ->toBeInstanceOf(Tag::class)
        ->id->toBe($tag->id);
});

test('category and tag seeders create default taxonomy data once', function () {
    $this->seed([CategorySeeder::class, TagSeeder::class]);
    $this->seed([CategorySeeder::class, TagSeeder::class]);

    expect(Category::where('slug', 'nasional')->count())->toBe(1)
        ->and(Category::where('slug', 'olahraga')->count())->toBe(1)
        ->and(Tag::where('slug', 'breaking-news')->count())->toBe(1)
        ->and(Tag::where('slug', 'sepak-bola')->count())->toBe(1);
});

test('category store ignores export counter from user input', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/categories', [
            'name' => 'Kategori Baru',
            'slug' => 'kategori-baru',
            'description' => 'Deskripsi kategori.',
            'export_counter' => 88,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('categories', [
        'name' => 'Kategori Baru',
        'slug' => 'kategori-baru',
        'export_counter' => 0,
    ]);
});
