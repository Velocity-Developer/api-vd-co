<?php

namespace App\Http\Middleware;

use App\Models\Server;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegisteredServerIp
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ipAddress = $request->ip();

        if ($ipAddress === null || ! Server::query()->where('server_ip', $ipAddress)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'IP address is not registered.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
