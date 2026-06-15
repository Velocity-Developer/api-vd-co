<?php

use App\Models\Category;
use App\Models\License;
use App\Models\Post;

test('news posts can be filtered by category and paginated', function () {
    $license = License::factory()->create([
        'expires_at' => now()->addDay(),
    ]);
    $selectedCategory = Category::factory()->create();
    $otherCategory = Category::factory()->create();
    $selectedPosts = Post::factory()->count(3)->create();
    $otherPost = Post::factory()->create();

    $selectedCategory->posts()->attach($selectedPosts);
    $otherCategory->posts()->attach($otherPost);

    $response = $this->withHeader('License', $license->code)
        ->getJson("/api/v1/news/posts?category_id={$selectedCategory->id}&post_per_page=2");

    $response->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('message', 'Success')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('pagination.current_page', 1)
        ->assertJsonPath('pagination.last_page', 2)
        ->assertJsonPath('pagination.per_page', 2)
        ->assertJsonPath('pagination.total', 3)
        ->assertJsonMissing(['id' => $otherPost->id]);
});

test('news posts validates its filters', function () {
    $license = License::factory()->create([
        'expires_at' => now()->addDay(),
    ]);

    $this->withHeader('License', $license->code)
        ->getJson('/api/v1/news/posts?category_id=999999&post_per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id', 'post_per_page']);
});
