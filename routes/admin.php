<?php

use App\Http\Controllers\Admin\CompanySettingsController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectRequirementController;
use App\Http\Controllers\Admin\ProjectRequirementMessageController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\EnsureCanManageCompanySettings;
use App\Http\Middleware\EnsureCanManageUsers;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureCanManageCompanySettings::class)
    ->name('company.')->group(function () {
        Route::get('company', [CompanySettingsController::class, 'edit'])->name('edit');
        Route::patch('company', [CompanySettingsController::class, 'update'])->name('update');
    });

Route::middleware(EnsureCanManageUsers::class)
    ->group(function (): void {
        Route::resource('users', UserController::class)->except(['show']);
        Route::resource('teams', TeamController::class)->except(['show']);
    });

Route::resource('projects', ProjectController::class)->except(['show']);
Route::patch('projects/{project}/requirements/{requirement}/review', [ProjectRequirementController::class, 'markReviewed'])
    ->name('projects.requirements.review');
Route::patch('projects/{project}/requirements/{requirement}/confirm-understanding', [ProjectRequirementController::class, 'confirmUnderstanding'])
    ->name('projects.requirements.confirm-understanding');
Route::post('projects/{project}/requirements/{requirement}/messages', [ProjectRequirementMessageController::class, 'store'])
    ->name('projects.requirements.messages.store');
Route::resource('projects.requirements', ProjectRequirementController::class);
