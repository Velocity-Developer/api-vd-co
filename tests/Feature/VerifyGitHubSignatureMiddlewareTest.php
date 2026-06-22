<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    putenv('RELEASE_WEBHOOK_SECRET=test-webhook-secret');
    $_ENV['RELEASE_WEBHOOK_SECRET'] = 'test-webhook-secret';
    $_SERVER['RELEASE_WEBHOOK_SECRET'] = 'test-webhook-secret';

    Route::post('/api/github/webhook/ping', fn () => response()->json([
        'status' => true,
        'message' => 'OK',
    ]))->middleware('github.signature');
});

test('github webhook route requires an x-signature header', function () {
    $this->postJson('/api/github/webhook/ping')
        ->assertUnauthorized()
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'X-Signature header is required.');
});

test('github webhook route rejects an invalid x-signature header', function () {
    Carbon::setTestNow('2026-06-22 12:00:00');

    $this->withHeader('X-Signature', 'invalid-signature')
        ->postJson('/api/github/webhook/ping')
        ->assertForbidden()
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'X-Signature header is invalid.');

    Carbon::setTestNow();
});

test('github webhook route accepts a valid x-signature header', function () {
    Carbon::setTestNow('2026-06-22 12:00:00');

    $signature = hash_hmac(
        'sha256',
        Carbon::now('Asia/Jakarta')->format('dmY'),
        'test-webhook-secret',
    );

    $this->withHeader('X-Signature', $signature)
        ->postJson('/api/github/webhook/ping')
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('message', 'OK');

    Carbon::setTestNow();
});
