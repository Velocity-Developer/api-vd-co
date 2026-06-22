<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class VerifyGitHubSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = trim((string) $request->header('X-Signature', ''));

        if ($signature === '') {
            return new JsonResponse([
                'status' => false,
                'message' => 'X-Signature header is required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $expectedSignature = hash_hmac(
            'sha256',
            Carbon::now('Asia/Jakarta')->format('dmY'),
            (string) env('RELEASE_WEBHOOK_SECRET'),
        );

        if (! hash_equals($expectedSignature, $signature)) {
            return new JsonResponse([
                'status' => false,
                'message' => 'X-Signature header is invalid.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
