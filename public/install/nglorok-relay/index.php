<?php

declare(strict_types=1);

/**
 * Standalone relay for Velocity Addons license endpoints.
 *
 * Deploy this folder as the document root for api.nglorok.com
 * and set NGOLOROK_UPSTREAM_BASE_URL to the main API server.
 */

function nglorokRelayConfig(array $server): array
{
    $upstreamBaseUrl = rtrim(
        (string) ($server['NGOLOROK_UPSTREAM_BASE_URL'] ?? getenv('NGOLOROK_UPSTREAM_BASE_URL') ?: 'https://api.velocitydeveloper.co'),
        '/'
    );

    return [
        'upstream_base_url' => $upstreamBaseUrl,
        'connect_timeout' => max(1, (int) ($server['NGOLOROK_CONNECT_TIMEOUT'] ?? getenv('NGOLOROK_CONNECT_TIMEOUT') ?: 5)),
        'request_timeout' => max(3, (int) ($server['NGOLOROK_REQUEST_TIMEOUT'] ?? getenv('NGOLOROK_REQUEST_TIMEOUT') ?: 20)),
        'fallback_auto_license_key' => trim((string) ($server['NGOLOROK_FALLBACK_AUTO_LICENSE_KEY'] ?? getenv('NGOLOROK_FALLBACK_AUTO_LICENSE_KEY') ?: '')),
    ];
}

function nglorokRelayAllowedPaths(): array
{
    return [
        '/api/v1/license',
        '/api/v1/get-auto-license',
    ];
}

function nglorokRelayRequestPath(string $requestUri): string
{
    $path = (string) parse_url($requestUri, PHP_URL_PATH);

    if ($path === '') {
        return '/';
    }

    return rtrim($path, '/') ?: '/';
}

function nglorokRelayMergedQuery(array $query, string $rawBody, string $contentType): array
{
    $bodyParams = [];
    $normalizedContentType = strtolower(trim(strtok($contentType, ';') ?: ''));

    if ($rawBody !== '') {
        if ($normalizedContentType === 'application/json') {
            $decoded = json_decode($rawBody, true);

            if (is_array($decoded)) {
                $bodyParams = $decoded;
            }
        } else {
            parse_str($rawBody, $bodyParams);
        }
    }

    return array_replace_recursive($query, $bodyParams);
}

function nglorokRelayBuildTargetUrl(string $baseUrl, string $path, array $query = []): string
{
    $targetUrl = rtrim($baseUrl, '/').$path;

    if ($query === []) {
        return $targetUrl;
    }

    return $targetUrl.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function nglorokRelayForwardHeaders(array $server, bool $includeContentType): array
{
    $headers = [];
    $allowed = [
        'HTTP_LICENSE' => 'License',
        'HTTP_SOURCE' => 'Source',
        'HTTP_ACCEPT' => 'Accept',
        'HTTP_USER_AGENT' => 'User-Agent',
    ];

    foreach ($allowed as $serverKey => $headerName) {
        if (! isset($server[$serverKey]) || trim((string) $server[$serverKey]) === '') {
            continue;
        }

        $headers[] = $headerName.': '.trim((string) $server[$serverKey]);
    }

    if (
        $includeContentType
        && isset($server['CONTENT_TYPE'])
        && trim((string) $server['CONTENT_TYPE']) !== ''
    ) {
        $headers[] = 'Content-Type: '.trim((string) $server['CONTENT_TYPE']);
    }

    return $headers;
}

function nglorokRelayResponseArray(int $status, string $body, array $headers = []): array
{
    return [
        'status' => $status,
        'body' => $body,
        'headers' => $headers,
    ];
}

function nglorokRelayJsonResponse(int $status, array $payload): array
{
    return nglorokRelayResponseArray(
        $status,
        json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
        ['Content-Type' => 'application/json; charset=utf-8']
    );
}

function nglorokRelayFallbackAutoLicenseResponse(string $licenseKey): ?array
{
    $licenseKey = trim($licenseKey);

    if ($licenseKey === '') {
        return null;
    }

    return nglorokRelayJsonResponse(200, [
        'status' => true,
        'message' => 'Success',
        'data' => [
            'status' => true,
            'is_active' => true,
            'code' => $licenseKey,
        ],
    ]);
}

function nglorokRelayForward(array $config, string $method, string $path, array $server, string $rawBody): array
{
    $query = $_GET;

    if ($method === 'GET') {
        $query = nglorokRelayMergedQuery(
            is_array($_GET) ? $_GET : [],
            $rawBody,
            (string) ($server['CONTENT_TYPE'] ?? '')
        );
    }

    $targetUrl = nglorokRelayBuildTargetUrl(
        (string) $config['upstream_base_url'],
        $path,
        $query
    );

    $requestBody = $method === 'GET' ? '' : $rawBody;
    $headers = nglorokRelayForwardHeaders($server, $requestBody !== '');

    if (function_exists('curl_init')) {
        $responseHeaders = [];
        $curlHandle = curl_init($targetUrl);

        if ($curlHandle === false) {
            return nglorokRelayUpstreamFailure($config, $path, 'Unable to initialize cURL.');
        }

        curl_setopt_array($curlHandle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_CONNECTTIMEOUT => (int) $config['connect_timeout'],
            CURLOPT_TIMEOUT => (int) $config['request_timeout'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
                $trimmed = trim($headerLine);

                if ($trimmed !== '' && str_contains($trimmed, ':')) {
                    [$name, $value] = explode(':', $trimmed, 2);
                    $responseHeaders[trim($name)] = trim($value);
                }

                return strlen($headerLine);
            },
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($requestBody !== '') {
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $requestBody);
        }

        $body = curl_exec($curlHandle);
        $httpCode = (int) curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
        $errorMessage = curl_error($curlHandle);
        curl_close($curlHandle);

        if ($body === false || $httpCode === 0) {
            return nglorokRelayUpstreamFailure(
                $config,
                $path,
                $errorMessage !== '' ? $errorMessage : 'Upstream request failed.'
            );
        }

        return nglorokRelayResponseArray($httpCode, (string) $body, $responseHeaders);
    }

    $contextOptions = [
        'http' => [
            'method' => $method,
            'timeout' => (int) $config['request_timeout'],
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
        ],
    ];

    if ($requestBody !== '') {
        $contextOptions['http']['content'] = $requestBody;
    }

    $context = stream_context_create($contextOptions);
    $body = @file_get_contents($targetUrl, false, $context);
    $responseHeaders = [];
    $httpCode = 0;

    foreach ($http_response_header ?? [] as $headerLine) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $headerLine, $matches) === 1) {
            $httpCode = (int) $matches[1];
            continue;
        }

        if (str_contains($headerLine, ':')) {
            [$name, $value] = explode(':', $headerLine, 2);
            $responseHeaders[trim($name)] = trim($value);
        }
    }

    if ($body === false || $httpCode === 0) {
        return nglorokRelayUpstreamFailure($config, $path, 'Unable to reach upstream server.');
    }

    return nglorokRelayResponseArray($httpCode, (string) $body, $responseHeaders);
}

function nglorokRelayUpstreamFailure(array $config, string $path, string $message): array
{
    if ($path === '/api/v1/get-auto-license') {
        $fallback = nglorokRelayFallbackAutoLicenseResponse((string) $config['fallback_auto_license_key']);

        if ($fallback !== null) {
            return $fallback;
        }
    }

    return nglorokRelayJsonResponse(502, [
        'status' => false,
        'message' => 'Relay gagal menghubungi upstream: '.$message,
    ]);
}

function nglorokRelayEmit(array $response): void
{
    http_response_code((int) $response['status']);

    foreach ($response['headers'] as $name => $value) {
        if (in_array(strtolower((string) $name), ['content-length', 'transfer-encoding', 'connection'], true)) {
            continue;
        }

        header($name.': '.$value);
    }

    echo (string) $response['body'];
}

if (! defined('NGOLOROK_RELAY_TESTING')) {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $requestPath = nglorokRelayRequestPath((string) ($_SERVER['REQUEST_URI'] ?? '/'));

    if (! in_array($requestPath, nglorokRelayAllowedPaths(), true)) {
        nglorokRelayEmit(nglorokRelayJsonResponse(404, [
            'status' => false,
            'message' => 'Endpoint tidak ditemukan.',
        ]));

        exit;
    }

    if (! in_array($method, ['GET', 'POST'], true)) {
        nglorokRelayEmit(nglorokRelayJsonResponse(405, [
            'status' => false,
            'message' => 'Method tidak didukung.',
        ]));

        exit;
    }

    $rawBody = (string) file_get_contents('php://input');
    $config = nglorokRelayConfig($_SERVER);

    nglorokRelayEmit(nglorokRelayForward($config, $method, $requestPath, $_SERVER, $rawBody));
}
