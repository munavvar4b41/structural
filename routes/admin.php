<?php

use App\Http\Controllers\Admin\CompanySettingsController;
use App\Http\Controllers\Admin\LeaveRequestController;
use App\Http\Controllers\Admin\LeaveSettingsController;
use App\Http\Controllers\Admin\MyWorkController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectRequirementController;
use App\Http\Controllers\Admin\ProjectRequirementMessageController;
use App\Http\Controllers\Admin\ProjectTaskController;
use App\Http\Controllers\Admin\TaskCompletionReviewController;
use App\Http\Controllers\Admin\TaskRatingReportController;
use App\Http\Controllers\Admin\TaskTimeEntryController;
use App\Http\Controllers\Admin\TaskTimerController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TimeReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\EnsureCanManageCompanySettings;
use App\Http\Middleware\EnsureCanManageUsers;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureCanManageCompanySettings::class)
    ->name('company.')->group(function () {
        Route::get('company', [CompanySettingsController::class, 'edit'])->name('edit');
        Route::patch('company', [CompanySettingsController::class, 'update'])->name('update');
    });

Route::middleware(EnsureCanManageCompanySettings::class)
    ->name('leave-settings.')->group(function (): void {
        Route::get('leave-settings', [LeaveSettingsController::class, 'edit'])->name('edit');
        Route::patch('leave-settings', [LeaveSettingsController::class, 'update'])->name('update');
    });

Route::middleware(EnsureCanManageUsers::class)
    ->group(function (): void {
        Route::resource('users', UserController::class)->except(['show']);
        Route::resource('teams', TeamController::class)->except(['show']);
    });

Route::get('leave-requests/manage', [LeaveRequestController::class, 'manage'])->name('leave-requests.manage');
Route::patch('leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
Route::patch('leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('leave-requests.reject');
Route::resource('leave-requests', LeaveRequestController::class)->only(['index', 'store', 'destroy']);

Route::get('my-work', [MyWorkController::class, 'index'])->name('my-work.index');
Route::get('task-reviews', [TaskCompletionReviewController::class, 'index'])->name('task-reviews.index');
Route::get('task-ratings-report', [TaskRatingReportController::class, 'index'])->name('task-ratings-report.index');
Route::resource('projects', ProjectController::class)->except(['show']);
Route::resource('projects.tasks', ProjectTaskController::class)
    ->only(['index', 'show', 'store', 'update', 'destroy'])
    ->scoped();
Route::patch('projects/{project}/requirements/{requirement}/review', [ProjectRequirementController::class, 'markReviewed'])
    ->name('projects.requirements.review');
Route::patch('projects/{project}/requirements/{requirement}/confirm-understanding', [ProjectRequirementController::class, 'confirmUnderstanding'])
    ->name('projects.requirements.confirm-understanding');
Route::post('projects/{project}/requirements/{requirement}/messages', [ProjectRequirementMessageController::class, 'store'])
    ->name('projects.requirements.messages.store');
Route::resource('projects.requirements', ProjectRequirementController::class);

Route::post('projects/{project}/tasks/{task}/submit-completion', [TaskCompletionReviewController::class, 'submit'])
    ->scopeBindings()
    ->name('projects.tasks.submit-completion');
Route::post('projects/{project}/tasks/{task}/confirm-completion', [TaskCompletionReviewController::class, 'confirm'])
    ->scopeBindings()
    ->name('projects.tasks.confirm-completion');
Route::post('projects/{project}/tasks/{task}/timer/start', [TaskTimerController::class, 'start'])
    ->scopeBindings()
    ->name('projects.tasks.timer.start');
Route::post('time-entries/stop', [TaskTimerController::class, 'stop'])->name('time-entries.stop');
Route::resource('projects.tasks.time-entries', TaskTimeEntryController::class)
    ->only(['store', 'update', 'destroy'])
    ->scoped();
Route::get('time-report', [TimeReportController::class, 'index'])->name('time-report.index');
