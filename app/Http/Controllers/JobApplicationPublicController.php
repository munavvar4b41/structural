<?php

namespace App\Http\Controllers;

use App\Enums\JobApplicationStatus;
use App\Http\Requests\StoreJobApplicationRequest;
use App\Mail\JobApplicationSubmittedMail;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Support\CareersMailRecipients;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class JobApplicationPublicController extends Controller
{
    public function store(
        StoreJobApplicationRequest $request,
        JobPosting $jobPosting,
        CareersMailRecipients $recipients,
    ): RedirectResponse {
        $resume = $request->file('resume');
        abort_if($resume === null, 422);

        $extension = $resume->getClientOriginalExtension() ?: $resume->extension() ?: 'bin';
        $path = $resume->storeAs(
            'careers/resumes',
            Str::uuid()->toString().'.'.$extension,
            'local',
        );

        $application = JobApplication::query()->create([
            'job_posting_id' => $jobPosting->id,
            'status' => JobApplicationStatus::Received,
            'candidate_name' => $request->validated('candidate_name'),
            'candidate_email' => $request->validated('candidate_email'),
            'candidate_phone' => $request->validated('candidate_phone'),
            'linkedin_url' => $request->validated('linkedin_url'),
            'portfolio_url' => $request->validated('portfolio_url'),
            'cover_letter' => $request->validated('cover_letter'),
            'skills' => $request->validated('skills'),
            'years_of_experience' => $request->validated('years_of_experience'),
            'salary_expectation' => $request->validated('salary_expectation'),
            'preferred_location' => $request->validated('preferred_location'),
            'resume_path' => $path,
            'resume_original_name' => $resume->getClientOriginalName(),
            'resume_mime' => (string) $resume->getMimeType(),
            'applied_at' => now(),
        ]);

        $application->load(['jobPosting:id,title,slug']);

        $emails = $recipients->forNewApplication();
        if ($emails !== []) {
            Mail::to($emails)->queue(new JobApplicationSubmittedMail($application));
        }

        return to_route('careers.show', $jobPosting)
            ->with('toast', __('Your application has been submitted. Thank you!'));
    }
}
