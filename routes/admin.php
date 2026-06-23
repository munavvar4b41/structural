<?php

use App\Http\Controllers\Admin\CareersSettingsController;
use App\Http\Controllers\Admin\CompanySettingsController;
use App\Http\Controllers\Admin\EstimationReviewController;
use App\Http\Controllers\Admin\JobApplicationController;
use App\Http\Controllers\Admin\JobApplicationResumeController;
use App\Http\Controllers\Admin\JobPostingController;
use App\Http\Controllers\Admin\LeaveRequestController;
use App\Http\Controllers\Admin\LeaveSettingsController;
use App\Http\Controllers\Admin\MyWorkController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectMetadataController;
use App\Http\Controllers\Admin\ProjectProposalController;
use App\Http\Controllers\Admin\ProjectProposalMessageController;
use App\Http\Controllers\Admin\ProjectRequirementController;
use App\Http\Controllers\Admin\ProjectRequirementEstimationController;
use App\Http\Controllers\Admin\ProjectRequirementMessageController;
use App\Http\Controllers\Admin\ProjectTagController;
use App\Http\Controllers\Admin\ProjectTaskChecklistItemController;
use App\Http\Controllers\Admin\ProjectTaskController;
use App\Http\Controllers\Admin\ProposalController;
use App\Http\Controllers\Admin\RequirementController;
use App\Http\Controllers\Admin\SuggestionController;
use App\Http\Controllers\Admin\TaskCompletionReviewController;
use App\Http\Controllers\Admin\TaskController;
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

Route::middleware(EnsureCanManageCompanySettings::class)
    ->name('careers-settings.')->group(function (): void {
        Route::get('careers-settings', [CareersSettingsController::class, 'edit'])->name('edit');
        Route::patch('careers-settings', [CareersSettingsController::class, 'update'])->name('update');
    });

Route::middleware(EnsureCanManageCompanySettings::class)
    ->group(function (): void {
        Route::resource('job-postings', JobPostingController::class)->except(['show']);
        Route::get('job-postings/{jobPosting}/applications', [JobApplicationController::class, 'index'])
            ->name('job-postings.applications');
        Route::get('job-applications/{jobApplication}', [JobApplicationController::class, 'show'])
            ->name('job-applications.show');
        Route::patch('job-applications/{jobApplication}/advance', [JobApplicationController::class, 'advance'])
            ->name('job-applications.advance');
        Route::patch('job-applications/{jobApplication}/reject', [JobApplicationController::class, 'reject'])
            ->name('job-applications.reject');
        Route::get('job-applications/{jobApplication}/resume', [JobApplicationResumeController::class, 'show'])
            ->name('job-applications.resume');
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
Route::patch('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])
    ->name('notifications.mark-all-read');
Route::patch('notifications/{notification}', [NotificationController::class, 'markAsRead'])
    ->name('notifications.read');
Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
Route::get('proposals', [ProposalController::class, 'index'])->name('proposals.index');
Route::get('requirements', [RequirementController::class, 'index'])->name('requirements.index');
Route::get('task-reviews', [TaskCompletionReviewController::class, 'index'])->name('task-reviews.index');
Route::get('estimation-reviews', [EstimationReviewController::class, 'index'])->name('estimation-reviews.index');
Route::get('task-ratings-report', [TaskRatingReportController::class, 'index'])->name('task-ratings-report.index');
Route::get('suggestions', [SuggestionController::class, 'index'])->name('suggestions.index');
Route::resource('projects', ProjectController::class);
Route::post('projects/{project}/tags', [ProjectTagController::class, 'store'])->name('projects.tags.store');
Route::delete('projects/{project}/tags/{tag}', [ProjectTagController::class, 'destroy'])->name('projects.tags.destroy');
Route::post('projects/{project}/metadata', [ProjectMetadataController::class, 'store'])->name('projects.metadata.store');
Route::patch('projects/{project}/metadata/{metadata}', [ProjectMetadataController::class, 'update'])->name('projects.metadata.update');
Route::delete('projects/{project}/metadata/{metadata}', [ProjectMetadataController::class, 'destroy'])->name('projects.metadata.destroy');
Route::resource('projects.tasks', ProjectTaskController::class);
Route::patch('projects/{project}/requirements/{requirement}/review', [ProjectRequirementController::class, 'markReviewed'])
    ->name('projects.requirements.review');
Route::patch('projects/{project}/requirements/{requirement}/confirm-understanding', [ProjectRequirementController::class, 'confirmUnderstanding'])
    ->name('projects.requirements.confirm-understanding');
Route::patch('projects/{project}/requirements/{requirement}/phase-settings', [ProjectRequirementController::class, 'updatePhaseSettings'])
    ->name('projects.requirements.phase-settings');
Route::post('projects/{project}/requirements/{requirement}/messages', [ProjectRequirementMessageController::class, 'store'])
    ->name('projects.requirements.messages.store');
Route::scopeBindings()->group(function (): void {
    Route::get('projects/{project}/requirements/{requirement}/estimation', [ProjectRequirementEstimationController::class, 'show'])
        ->name('projects.requirements.estimation.show');
    Route::post('projects/{project}/requirements/{requirement}/estimation', [ProjectRequirementEstimationController::class, 'store'])
        ->name('projects.requirements.estimation.store');
    Route::put('projects/{project}/requirements/{requirement}/estimation/{estimation}/lines', [ProjectRequirementEstimationController::class, 'syncLines'])
        ->name('projects.requirements.estimation.lines');
    Route::patch('projects/{project}/requirements/{requirement}/estimation/{estimation}/submit', [ProjectRequirementEstimationController::class, 'submit'])
        ->name('projects.requirements.estimation.submit');
    Route::patch('projects/{project}/requirements/{requirement}/estimation/{estimation}/approve', [ProjectRequirementEstimationController::class, 'approve'])
        ->name('projects.requirements.estimation.approve');
    Route::patch('projects/{project}/requirements/{requirement}/estimation/{estimation}/reject', [ProjectRequirementEstimationController::class, 'reject'])
        ->name('projects.requirements.estimation.reject');
    Route::patch('projects/{project}/requirements/{requirement}/estimation/{estimation}/request-changes', [ProjectRequirementEstimationController::class, 'requestChanges'])
        ->name('projects.requirements.estimation.request-changes');
    Route::post('projects/{project}/requirements/{requirement}/estimation/{estimation}/request-revision', [ProjectRequirementEstimationController::class, 'requestRevision'])
        ->name('projects.requirements.estimation.request-revision');
    Route::post('projects/{project}/requirements/{requirement}/estimation/{estimation}/transfer', [ProjectRequirementEstimationController::class, 'transfer'])
        ->name('projects.requirements.estimation.transfer');
});
Route::resource('projects.requirements', ProjectRequirementController::class);
Route::patch('projects/{project}/proposals/{proposal}/submit', [ProjectProposalController::class, 'submit'])
    ->name('projects.proposals.submit');
Route::patch('projects/{project}/proposals/{proposal}/confirm', [ProjectProposalController::class, 'confirm'])
    ->name('projects.proposals.confirm');
Route::patch('projects/{project}/proposals/{proposal}/reject', [ProjectProposalController::class, 'reject'])
    ->name('projects.proposals.reject');
Route::patch('projects/{project}/proposals/{proposal}/reopen', [ProjectProposalController::class, 'reopen'])
    ->name('projects.proposals.reopen');
Route::post('projects/{project}/proposals/{proposal}/messages', [ProjectProposalMessageController::class, 'store'])
    ->name('projects.proposals.messages.store');
Route::resource('projects.proposals', ProjectProposalController::class);

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
Route::post('time-entries/pause', [TaskTimerController::class, 'pause'])->name('time-entries.pause');
Route::post('time-entries/resume', [TaskTimerController::class, 'resume'])->name('time-entries.resume');
Route::resource('projects.tasks.time-entries', TaskTimeEntryController::class)
    ->only(['store', 'update', 'destroy'])
    ->scoped();
Route::resource('projects.tasks.checklist-items', ProjectTaskChecklistItemController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->scoped();
Route::get('time-report', [TimeReportController::class, 'index'])->name('time-report.index');
