<?php

use App\Http\Controllers\ChatSessionController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Volt::route('dashboard', 'pages.workspace')->defaults('area', 'dashboard')->name('dashboard');
    Volt::route('targets/create', 'pages.workspace')->defaults('area', 'target-create')->name('targets.create');
    Volt::route('scans/create', 'pages.workspace')->defaults('area', 'scan-create')->name('scans.create');
    foreach (['targets','scans','findings','reports','chat'] as $area) {
        Volt::route($area, 'pages.workspace')->defaults('area', $area)->name($area.'.index');
        Volt::route($area.'/{record}', 'pages.workspace')->defaults('area', $area)->name($area.'.show');
    }
    foreach (['certificates','scan-profiles','tools','vulnerability-types','users'] as $area) {
        Volt::route($area, 'pages.workspace')->defaults('area', $area)->name($area.'.index');
    }
    Volt::route('executions/{record}', 'pages.workspace')->defaults('area', 'executions')->name('executions.show');
    Volt::route('settings', 'pages.workspace')->defaults('area', 'settings')->name('settings');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Authorization pattern for every route: auth + can:<ability>,<route model> (policies in app/Policies).
Route::get('chat-sessions/{chatSession}', [ChatSessionController::class, 'show'])
    ->whereNumber('chatSession')
    ->middleware(['auth', 'can:view,chatSession'])
    ->name('chat-sessions.show');

require __DIR__.'/auth.php';
