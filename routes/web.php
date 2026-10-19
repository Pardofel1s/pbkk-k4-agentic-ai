<?php

use App\Http\Controllers\ChatSessionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::get('chat-sessions/{chatSession}', [ChatSessionController::class, 'show'])
    ->whereNumber('chatSession')
    ->middleware(['auth'])
    ->name('chat-sessions.show');

require __DIR__.'/auth.php';
