<?php

declare(strict_types=1);

if (! defined('NGOLOROK_RELAY_TESTING')) {
    define('NGOLOROK_RELAY_TESTING', true);
}

require_once dirname(__DIR__, 2).'/public/install/nglorok-relay/index.php';

test('relay normalizes request paths', function () {
    expect(nglorokRelayRequestPath('/api/v1/license?foo=bar'))->toBe('/api/v1/license')
        ->and(nglorokRelayRequestPath('/api/v1/get-auto-license/'))->toBe('/api/v1/get-auto-license')
        ->and(nglorokRelayRequestPath(''))->toBe('/');
});

test('relay merges query string bodies for get requests', function () {
    $merged = nglorokRelayMergedQuery(
        ['wp_version' => '6.8.1'],
        'php_version=8.3.9&velocity_addons_version=2.2.0',
        'application/x-www-form-urlencoded'
    );

    expect($merged)->toBe([
        'wp_version' => '6.8.1',
        'php_version' => '8.3.9',
        'velocity_addons_version' => '2.2.0',
    ]);
});

test('relay merges json bodies for get requests', function () {
    $merged = nglorokRelayMergedQuery(
        ['source' => 'site.test'],
        '{"license":"APICO-001"}',
        'application/json'
    );

    expect($merged)->toBe([
        'source' => 'site.test',
        'license' => 'APICO-001',
    ]);
});

test('relay builds target urls with query parameters', function () {
    $url = nglorokRelayBuildTargetUrl(
        'https://upstream.example.com',
        '/api/v1/license',
        ['source' => 'demo.test', 'wp_version' => '6.9.0']
    );

    expect($url)->toBe('https://upstream.example.com/api/v1/license?source=demo.test&wp_version=6.9.0');
});

test('relay can return a fallback auto license payload', function () {
    $response = nglorokRelayFallbackAutoLicenseResponse('APICO-LICENSE-9999');

    expect($response)->not->toBeNull()
        ->and($response['status'])->toBe(200);

    $payload = json_decode((string) $response['body'], true);

    expect($payload)->toBe([
        'status' => true,
        'message' => 'Success',
        'data' => [
            'status' => true,
            'is_active' => true,
            'code' => 'APICO-LICENSE-9999',
        ],
    ]);
});
