<?php

use App\Http\Controllers\ApiPublic\V1\TgmPluginController;
use App\Http\Controllers\ApiPublic\V1\ProjectPublicController;
use Illuminate\Support\Facades\Route;

Route::middleware('public.ai.signature')->prefix('public/v1')->group(function () {
    Route::get('/project/{slug}', [ProjectPublicController::class, 'show']);
    Route::get('/tgm-plugins', TgmPluginController::class);
});
