<?php

use App\Http\Controllers\Api\Desktop\DesktopAuthController;
use App\Http\Controllers\Api\Desktop\DesktopMyWorkController;
use App\Http\Controllers\Api\Desktop\DesktopNotificationController;
use App\Http\Controllers\Api\Desktop\DesktopProjectTaskController;
use App\Http\Controllers\Api\Desktop\DesktopTaskChecklistController;
use App\Http\Controllers\Api\Desktop\DesktopTaskCompletionController;
use App\Http\Controllers\Api\Desktop\DesktopTaskTimeEntryController;
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

        Route::get('notifications', [DesktopNotificationController::class, 'index'])->name('api.desktop.notifications.index');
        Route::patch('notifications/mark-all-read', [DesktopNotificationController::class, 'markAllAsRead'])
            ->name('api.desktop.notifications.mark-all-read');
        Route::patch('notifications/{notification}', [DesktopNotificationController::class, 'markAsRead'])
            ->name('api.desktop.notifications.mark-as-read');

        Route::scopeBindings()->group(function (): void {
            Route::get('projects/{project}/tasks/form-options', [DesktopProjectTaskController::class, 'formOptions'])
                ->name('api.desktop.projects.tasks.form-options');
            Route::post('projects/{project}/tasks', [DesktopProjectTaskController::class, 'store'])
                ->name('api.desktop.projects.tasks.store');
            Route::get('projects/{project}/tasks/{task}', [DesktopProjectTaskController::class, 'show'])
                ->name('api.desktop.projects.tasks.show');
            Route::patch('projects/{project}/tasks/{task}', [DesktopProjectTaskController::class, 'update'])
                ->name('api.desktop.projects.tasks.update');
            Route::delete('projects/{project}/tasks/{task}', [DesktopProjectTaskController::class, 'destroy'])
                ->name('api.desktop.projects.tasks.destroy');

            Route::post('projects/{project}/tasks/{task}/submit-completion', [DesktopTaskCompletionController::class, 'submit'])
                ->name('api.desktop.projects.tasks.submit-completion');
            Route::post('projects/{project}/tasks/{task}/confirm-completion', [DesktopTaskCompletionController::class, 'confirm'])
                ->name('api.desktop.projects.tasks.confirm-completion');

            Route::post('projects/{project}/tasks/{task}/checklist-items', [DesktopTaskChecklistController::class, 'store'])
                ->name('api.desktop.projects.tasks.checklist-items.store');
            Route::patch('projects/{project}/tasks/{task}/checklist-items/{checklist_item}', [DesktopTaskChecklistController::class, 'update'])
                ->name('api.desktop.projects.tasks.checklist-items.update');
            Route::delete('projects/{project}/tasks/{task}/checklist-items/{checklist_item}', [DesktopTaskChecklistController::class, 'destroy'])
                ->name('api.desktop.projects.tasks.checklist-items.destroy');

            Route::post('projects/{project}/tasks/{task}/time-entries', [DesktopTaskTimeEntryController::class, 'store'])
                ->name('api.desktop.projects.tasks.time-entries.store');
            Route::patch('projects/{project}/tasks/{task}/time-entries/{time_entry}', [DesktopTaskTimeEntryController::class, 'update'])
                ->name('api.desktop.projects.tasks.time-entries.update');
            Route::delete('projects/{project}/tasks/{task}/time-entries/{time_entry}', [DesktopTaskTimeEntryController::class, 'destroy'])
                ->name('api.desktop.projects.tasks.time-entries.destroy');
        });
    });
});
