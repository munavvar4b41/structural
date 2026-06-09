<?php

namespace Tests\Feature\Admin;

use App\Enums\JobApplicationStatus;
use App\Mail\JobApplicationAdvancedMail;
use App\Mail\JobApplicationRejectedMail;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JobApplicationReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_applications_for_posting(): void
    {
        $admin = User::factory()->admin()->create();
        $posting = JobPosting::factory()->open()->create(['created_by_user_id' => $admin->id]);
        JobApplication::factory()->create(['job_posting_id' => $posting->id]);

        $this->actingAs($admin)
            ->get(route('admin.job-postings.applications', $posting))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/job-postings/Applications')
                ->has('applications', 1));
    }

    public function test_admin_can_advance_application_and_queues_email(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $application = JobApplication::factory()->create([
            'status' => JobApplicationStatus::Received,
            'candidate_email' => 'candidate@example.com',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.job-applications.advance', $application))
            ->assertRedirect();

        $application->refresh();

        $this->assertSame(JobApplicationStatus::Screening, $application->status);
        $this->assertSame($admin->id, $application->reviewed_by_user_id);

        Mail::assertQueued(JobApplicationAdvancedMail::class, function (JobApplicationAdvancedMail $mail): bool {
            return $mail->hasTo('candidate@example.com');
        });
    }

    public function test_admin_can_reject_application_and_queues_email(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $application = JobApplication::factory()->create([
            'status' => JobApplicationStatus::Interview,
            'candidate_email' => 'candidate@example.com',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.job-applications.reject', $application), [
                'rejection_reason' => 'Position filled.',
            ])
            ->assertRedirect();

        $application->refresh();

        $this->assertSame(JobApplicationStatus::Rejected, $application->status);
        $this->assertSame('Position filled.', $application->rejection_reason);

        Mail::assertQueued(JobApplicationRejectedMail::class, function (JobApplicationRejectedMail $mail): bool {
            return $mail->hasTo('candidate@example.com');
        });
    }

    public function test_staff_cannot_download_resume(): void
    {
        Storage::fake('local');

        $staff = User::factory()->withPrimaryTeam()->create();
        $application = JobApplication::factory()->create();
        Storage::disk('local')->put($application->resume_path, 'resume-content');

        $this->actingAs($staff)
            ->get(route('admin.job-applications.resume', $application))
            ->assertForbidden();
    }

    public function test_admin_can_download_resume(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $application = JobApplication::factory()->create([
            'resume_path' => 'careers/resumes/test.pdf',
            'resume_original_name' => 'my-resume.pdf',
            'resume_mime' => 'application/pdf',
        ]);
        Storage::disk('local')->put($application->resume_path, 'resume-content');

        $this->actingAs($admin)
            ->get(route('admin.job-applications.resume', $application))
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
