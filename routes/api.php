<?php

use App\Http\Controllers\Api\ChatAiController;
use Illuminate\Support\Facades\Route;

// All routes here are served under the /api prefix (see bootstrap/app.php).

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('chat-ai', [ChatAiController::class, 'store'])->name('api.chat-ai');
});
