<?php

use App\Http\Controllers\JobApplicationPublicController;
use App\Http\Controllers\JobPostingPublicController;
use App\Http\Controllers\TeamSelectionController;
use App\Http\Middleware\EnsureHasPrimaryTeam;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('careers', [JobPostingPublicController::class, 'index'])->name('careers.index');
Route::get('careers/{jobPosting:slug}', [JobPostingPublicController::class, 'show'])->name('careers.show');
Route::post('careers/{jobPosting:slug}/apply', [JobApplicationPublicController::class, 'store'])
    ->name('careers.apply');

Route::middleware('auth')->group(function (): void {
    Route::get('select-team', [TeamSelectionController::class, 'create'])->name('teams.select.create');
    Route::post('select-team', [TeamSelectionController::class, 'store'])->name('teams.select.store');
});

Route::middleware(['auth', 'verified', EnsureHasPrimaryTeam::class])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
