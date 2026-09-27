<?php

use App\Exceptions\AiProviderException;
use App\Services\AiProviderService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('ai provider service generates article content using the configured openai compatible endpoint', function () {
    config()->set('services.ai_provider.url', 'https://free.wsd.my.id/v1');
    config()->set('services.ai_provider.key', 'test-ai-provider-key');
    config()->set('services.ai_provider.model', 'free.wsd');

    Http::fake([
        'free.wsd.my.id/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'title' => 'Panduan Laravel Testing',
                            'content' => '<p>Isi artikel pengujian.</p>',
                            'excerpt' => 'Ringkasan artikel pengujian.',
                            'tags' => ['laravel', 'testing'],
                            'image_keyword' => 'testing',
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        ]),
    ]);

    $result = app(AiProviderService::class)->article_generator('Laravel testing');

    expect($result)
        ->toBeArray()
        ->and($result['title'])->toBe('Panduan Laravel Testing')
        ->and($result['tags'])->toBe(['laravel', 'testing'])
        ->and($result['image_keyword'])->toBe('testing');

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        return $request->url() === 'https://free.wsd.my.id/v1/chat/completions'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-ai-provider-key')
            && $request->hasHeader('Accept', 'application/json')
            && $payload['model'] === 'free.wsd'
            && $payload['messages'][0]['role'] === 'system'
            && $payload['messages'][0]['content'] === 'Anda adalah seorang blogger profesional, penulis artikel SEO, dan pakar konten digital yang andal.'
            && $payload['messages'][1]['role'] === 'user'
            && $payload['messages'][1]['content'] === 'Buatkan artikel menarik tentang: Laravel testing'
            && $payload['stream'] === false
            && $payload['response_format']['type'] === 'json_schema'
            && $payload['response_format']['json_schema']['name'] === 'article_generator'
            && $payload['response_format']['json_schema']['schema']['required'] === [
                'title',
                'content',
                'excerpt',
                'tags',
                'image_keyword',
            ];
    });
});

test('ai provider service reads articles wrapped in a json code fence', function () {
    Http::fake([
        '*/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => "```json\n".json_encode([
                'title' => 'Judul',
                'content' => '<p>Isi</p>',
                'excerpt' => 'Ringkas',
                'tags' => ['a'],
                'image_keyword' => 'cat',
            ])."\n```"]]],
        ]),
    ]);

    expect(app(AiProviderService::class)->article_generator('Kucing'))
        ->toMatchArray(['title' => 'Judul', 'content' => '<p>Isi</p>']);
});

test('ai provider service throws a clear error when the provider fails or answers without an article', function (mixed $fakeResponse, string $message) {
    Http::fake(['*/chat/completions' => $fakeResponse]);

    expect(fn () => app(AiProviderService::class)->article_generator('Kucing'))
        ->toThrow(AiProviderException::class, $message);
})->with([
    'server error' => [fn () => Http::response(['error' => 'down'], 500), 'HTTP 500'],
    'not json' => [fn () => Http::response(['choices' => [['message' => ['content' => 'Maaf, saya tidak bisa.']]]]), 'bukan artikel yang valid'],
    'missing content' => [fn () => Http::response(['choices' => []]), 'bukan artikel yang valid'],
    'connection' => [fn () => throw new ConnectionException('timeout'), 'tidak bisa dihubungi'],
]);
