<?php

use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TerminalAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/terminal-login', [TerminalAuthController::class, 'login'])
        ->middleware('throttle:15,1')
        ->name('api.v1.terminal.login');

    Route::middleware(['auth:sanctum', 'store.context'])->prefix('sync')->group(function (): void {
        Route::get('/bootstrap', [SyncController::class, 'bootstrap'])->name('api.v1.sync.bootstrap');
        Route::get('/pull', [SyncController::class, 'pull'])->name('api.v1.sync.pull');
        Route::post('/push', [SyncController::class, 'push'])->name('api.v1.sync.push');
    });
});
