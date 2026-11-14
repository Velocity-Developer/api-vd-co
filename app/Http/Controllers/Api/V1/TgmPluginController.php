<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\TgmPluginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TgmPluginController extends Controller
{
    public function __invoke(Request $request, TgmPluginService $tgmPluginService): JsonResponse
    {
        // paket privat (fork BB/Themer/UABB) hanya untuk situs di server kita
        $ip = $request->ip();
        $serverTerdaftar = $ip !== null && Server::query()->where('server_ip', $ip)->exists();

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => $tgmPluginService->get($serverTerdaftar, $request->header('source')),
        ]);
    }
}
