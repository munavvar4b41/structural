<?php

use App\Http\Controllers\Api\Desktop\DesktopAuthController;
use App\Http\Controllers\Api\Desktop\DesktopMyWorkController;
use App\Http\Controllers\Api\Desktop\DesktopTimerController;
use App\Http\Controllers\Api\Desktop\DesktopTrayController;
use Illuminate\Support\Facades\Route;

Route::prefix('desktop')->group(function (): void {
    Route::post('login', [DesktopAuthController::class, 'login'])->name('api.desktop.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [DesktopAuthController::class, 'logout'])->name('api.desktop.logout');
        Route::get('tray', [DesktopTrayController::class, 'show'])->name('api.desktop.tray');
        Route::get('my-work', [DesktopMyWorkController::class, 'index'])->name('api.desktop.my-work');
        Route::post('timer/start', [DesktopTimerController::class, 'start'])->name('api.desktop.timer.start');
        Route::post('timer/stop', [DesktopTimerController::class, 'stop'])->name('api.desktop.timer.stop');
        Route::post('timer/pause', [DesktopTimerController::class, 'pause'])->name('api.desktop.timer.pause');
        Route::post('timer/resume', [DesktopTimerController::class, 'resume'])->name('api.desktop.timer.resume');
    });
});
