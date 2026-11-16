<?php

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\ChatAiController;
use Illuminate\Support\Facades\Route;

// All routes here are served under the /api prefix (see bootstrap/app.php).

Route::post('login', [AuthTokenController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('api.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('logout', [AuthTokenController::class, 'destroy'])->name('api.logout');
    Route::post('chat-ai', [ChatAiController::class, 'store'])->name('api.chat-ai');
});
