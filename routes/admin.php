<?php

use App\Http\Controllers\Admin\CompanySettingsController;
use App\Http\Middleware\EnsureCanManageCompanySettings;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureCanManageCompanySettings::class)
    ->name('company.')->group(function () {
        Route::get('company', [CompanySettingsController::class, 'edit'])->name('edit');
        Route::patch('company', [CompanySettingsController::class, 'update'])->name('update');
    });
