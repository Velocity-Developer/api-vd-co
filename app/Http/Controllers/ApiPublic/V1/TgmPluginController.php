<?php

namespace App\Http\Controllers\ApiPublic\V1;

use App\Http\Controllers\Controller;
use App\Services\TgmPluginService;
use Illuminate\Http\JsonResponse;

class TgmPluginController extends Controller
{
    public function __invoke(TgmPluginService $tgmPluginService): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => $tgmPluginService->get(),
        ]);
    }
}
