<?php

use App\Ai\Agents\ArticleGenerator;
use App\Exceptions\AiProviderException;
use App\Models\User;
use App\Services\AiProviderService;

test('authenticated users can generate article content from a topic', function () {
    $user = User::factory()->create();
    $expectedArticle = [
        'title' => 'Panduan Laravel Testing',
        'content' => '<p>Isi artikel pengujian.</p>',
        'excerpt' => 'Ringkasan artikel pengujian.',
        'tags' => ['laravel', 'testing'],
        'image_keyword' => 'testing',
    ];

    $service = Mockery::mock(AiProviderService::class);
    $service->shouldReceive('article_generator')
        ->once()
        ->with('Laravel testing')
        ->andReturn($expectedArticle);

    app()->instance(AiProviderService::class, $service);

    $this->actingAs($user)
        ->withoutMiddleware()
        ->postJson('/ajax/article-generator', [
            'topic' => 'Laravel testing',
        ])
        ->assertOk()
        ->assertJson([
            'data' => $expectedArticle,
        ]);
});

test('article generator validates the topic field', function () {
    $user = User::factory()->create();
    $session = app('session');
    $session->start();
    $token = $session->token();

    $this->actingAs($user)
        ->withSession([
            '_token' => $token,
        ])
        ->withHeader('X-CSRF-TOKEN', $token)
        ->post('/ajax/article-generator', [
            '_token' => $token,
            'topic' => '',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['topic']);
});

test('authenticated users can generate article content from a topic using the article generator agent', function () {
    $user = User::factory()->create();
    $expectedArticle = [
        'title' => 'Panduan Laravel Testing',
        'content' => '<p>Isi artikel pengujian.</p>',
        'excerpt' => 'Ringkasan artikel pengujian.',
        'tags' => ['laravel', 'testing'],
        'image_keyword' => 'testing',
    ];

    ArticleGenerator::fake([$expectedArticle]);

    $this->actingAs($user)
        ->withoutMiddleware()
        ->postJson('/ajax/article-generator-by-agent', [
            'topic' => 'Laravel testing',
        ])
        ->assertOk()
        ->assertJson([
            'data' => $expectedArticle,
        ]);

    ArticleGenerator::assertPrompted(
        'Buatkan artikel menarik tentang: Laravel testing',
    );
});

test('article generator answers with a json 502 and the reason when the ai provider fails', function () {
    $service = Mockery::mock(AiProviderService::class);
    $service->shouldReceive('article_generator')
        ->once()
        ->andThrow(new AiProviderException('AI provider menolak permintaan (HTTP 500).'));

    app()->instance(AiProviderService::class, $service);

    $this->actingAs(User::factory()->create())
        ->postJson('/ajax/article-generator', ['topic' => 'Laravel testing'])
        ->assertStatus(502)
        ->assertJsonPath('message', 'AI provider menolak permintaan (HTTP 500).');
});
