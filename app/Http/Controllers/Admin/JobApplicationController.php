<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JobApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectJobApplicationRequest;
use App\Mail\JobApplicationAdvancedMail;
use App\Mail\JobApplicationRejectedMail;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class JobApplicationController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, JobPosting $jobPosting): Response
    {
        $this->authorize('viewAny', JobApplication::class);

        $statusQuery = $request->query('status');
        $statusFilter = is_string($statusQuery) ? $statusQuery : '';
        $statusApplied = $statusFilter !== '' && in_array($statusFilter, JobApplicationStatus::values(), true);

        $applications = JobApplication::query()
            ->where('job_posting_id', $jobPosting->id)
            ->with(['reviewedBy:id,name'])
            ->when($statusApplied, static fn ($query) => $query->where('status', $statusFilter))
            ->orderByRaw("CASE status WHEN 'received' THEN 0 WHEN 'screening' THEN 1 WHEN 'interview' THEN 2 WHEN 'offer' THEN 3 WHEN 'hired' THEN 4 ELSE 5 END")
            ->orderByDesc('applied_at')
            ->get()
            ->map(fn (JobApplication $application): array => $this->applicationListPayload($application));

        return Inertia::render('admin/job-postings/Applications', [
            'job_posting' => [
                'id' => $jobPosting->id,
                'title' => $jobPosting->title,
                'slug' => $jobPosting->slug,
                'status' => $jobPosting->status->value,
                'status_label' => $jobPosting->status->label(),
            ],
            'applications' => $applications,
            'filters' => [
                'status' => $statusApplied ? $statusFilter : '',
            ],
            'status_options' => $this->statusOptions(),
        ]);
    }

    public function show(JobApplication $jobApplication): Response
    {
        $this->authorize('view', $jobApplication);

        $jobApplication->load(['jobPosting:id,title,slug', 'reviewedBy:id,name']);

        return Inertia::render('admin/job-applications/Show', [
            'application' => $this->applicationDetailPayload($jobApplication),
        ]);
    }

    public function advance(Request $request, JobApplication $jobApplication): RedirectResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User || ! $actor->can('advance', $jobApplication), 403);

        $nextStage = $jobApplication->status->nextStage();
        abort_if($nextStage === null, 422);

        $jobApplication->forceFill([
            'status' => $nextStage,
            'reviewed_by_user_id' => $actor->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ])->save();

        $jobApplication->load(['jobPosting:id,title']);

        Mail::to($jobApplication->candidate_email)->queue(new JobApplicationAdvancedMail($jobApplication));

        return back()->with('toast', __('Application advanced to :stage.', ['stage' => $nextStage->label()]));
    }

    public function reject(RejectJobApplicationRequest $request, JobApplication $jobApplication): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $jobApplication->forceFill([
            'status' => JobApplicationStatus::Rejected,
            'reviewed_by_user_id' => $actor->id,
            'reviewed_at' => now(),
            'rejection_reason' => $request->validated('rejection_reason'),
        ])->save();

        $jobApplication->load(['jobPosting:id,title']);

        Mail::to($jobApplication->candidate_email)->queue(new JobApplicationRejectedMail($jobApplication));

        return back()->with('toast', __('Application rejected.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationListPayload(JobApplication $application): array
    {
        return [
            'id' => $application->id,
            'candidate_name' => $application->candidate_name,
            'candidate_email' => $application->candidate_email,
            'status' => $application->status->value,
            'status_label' => $application->status->label(),
            'years_of_experience' => $application->years_of_experience,
            'applied_at' => $application->applied_at->toIso8601String(),
            'reviewed_by' => $application->reviewedBy ? [
                'id' => $application->reviewedBy->id,
                'name' => $application->reviewedBy->name,
            ] : null,
            'can_advance' => $application->status->canAdvance(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationDetailPayload(JobApplication $application): array
    {
        $nextStage = $application->status->nextStage();

        return [
            'id' => $application->id,
            'candidate_name' => $application->candidate_name,
            'candidate_email' => $application->candidate_email,
            'candidate_phone' => $application->candidate_phone,
            'linkedin_url' => $application->linkedin_url,
            'portfolio_url' => $application->portfolio_url,
            'cover_letter' => $application->cover_letter,
            'skills' => $application->skills,
            'years_of_experience' => $application->years_of_experience,
            'salary_expectation' => $application->salary_expectation,
            'preferred_location' => $application->preferred_location,
            'status' => $application->status->value,
            'status_label' => $application->status->label(),
            'rejection_reason' => $application->rejection_reason,
            'applied_at' => $application->applied_at->toIso8601String(),
            'reviewed_at' => $application->reviewed_at?->toIso8601String(),
            'resume_original_name' => $application->resume_original_name,
            'job_posting' => [
                'id' => $application->jobPosting->id,
                'title' => $application->jobPosting->title,
                'slug' => $application->jobPosting->slug,
            ],
            'reviewed_by' => $application->reviewedBy ? [
                'id' => $application->reviewedBy->id,
                'name' => $application->reviewedBy->name,
            ] : null,
            'can_advance' => $application->status->canAdvance(),
            'next_stage_label' => $nextStage?->label(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            static fn (JobApplicationStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            JobApplicationStatus::cases(),
        );
    }
}
