<?php

use App\Models\RequestLog;
use App\Models\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/api/ai/public/ping', fn () => response()->json([
        'status' => true,
        'message' => 'OK',
    ]))->middleware('public.ai.signature');
});

test('ai public route requires a signature header', function () {
    $this->getJson('/api/ai/public/ping')
        ->assertUnauthorized()
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'Signature header is required.');

    $requestLog = RequestLog::sole();

    expect($requestLog->route)->toBe('/api/ai/public/ping')
        ->and($requestLog->method)->toBe('GET')
        ->and($requestLog->status)->toBe(401)
        ->and($requestLog->website_id)->toBeNull()
        ->and($requestLog->license_id)->toBeNull();
});

test('ai public route rejects an invalid signature header', function () {
    Carbon::setTestNow('2026-06-20 09:00:00');

    $this->withHeaders([
        'signature' => 'invalid-signature',
        'resource' => 'client.example.com',
    ])
        ->getJson('/api/ai/public/ping')
        ->assertForbidden()
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'Signature header is invalid.');

    $website = Website::where('domain', 'client.example.com')->sole();
    $requestLog = RequestLog::sole();

    expect($website->status)->toBe('active')
        ->and($website->license_key)->toStartWith('AI-PUBLIC-')
        ->and($requestLog->status)->toBe(403)
        ->and($requestLog->website_id)->toBe($website->id)
        ->and($requestLog->license_id)->toBeNull();

    Carbon::setTestNow();
});

test('ai public route accepts a valid signature header', function () {
    Carbon::setTestNow('2026-06-20 09:00:00');

    $signature = md5(now()->format('dmY'));

    $this->withHeader('signature', $signature)
        ->getJson('/api/ai/public/ping')
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('message', 'OK');

    $requestLog = RequestLog::sole();

    expect($requestLog->status)->toBe(200)
        ->and($requestLog->website_id)->toBeNull();

    Carbon::setTestNow();
});

test('ai public route reuses website from the resource header when signature is valid', function () {
    Carbon::setTestNow('2026-06-20 09:00:00');

    $signature = md5(now()->format('dmY'));

    $this->withHeaders([
        'signature' => $signature,
        'resource' => 'resource.example.com',
    ])
        ->getJson('/api/ai/public/ping')
        ->assertOk();

    $website = Website::where('domain', 'resource.example.com')->sole();
    $requestLog = RequestLog::sole();

    expect($requestLog->status)->toBe(200)
        ->and($requestLog->website_id)->toBe($website->id)
        ->and($website->license_key)->toStartWith('AI-PUBLIC-')
        ->and($website->status)->toBe('active');

    Carbon::setTestNow();
});
