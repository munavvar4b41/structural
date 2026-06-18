<?php

namespace Tests\Feature\Careers;

use App\Enums\JobApplicationStatus;
use App\Enums\JobPostingStatus;
use App\Mail\JobApplicationSubmittedMail;
use App\Models\JobPosting;
use App\Settings\CareersSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicJobApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_open_job_postings(): void
    {
        JobPosting::factory()->open()->create(['title' => 'Open Role']);
        JobPosting::factory()->create(['title' => 'Draft Role', 'status' => JobPostingStatus::Draft]);

        $this->get(route('careers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('careers/Index')
                ->has('job_postings', 1)
                ->where('job_postings.0.title', 'Open Role'));
    }

    public function test_public_can_view_open_job_posting(): void
    {
        $posting = JobPosting::factory()->open()->create(['slug' => 'senior-dev']);

        $this->get(route('careers.show', $posting))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('careers/Show')
                ->where('job_posting.slug', 'senior-dev'));
    }

    public function test_draft_posting_is_not_publicly_visible(): void
    {
        $posting = JobPosting::factory()->create([
            'slug' => 'hidden-role',
            'status' => JobPostingStatus::Draft,
        ]);

        $this->get(route('careers.show', $posting))->assertNotFound();
    }

    public function test_public_can_submit_application_with_resume(): void
    {
        Mail::fake();
        Storage::fake('local');

        $settings = app(CareersSettings::class);
        $settings->notification_emails = ['hr@example.com'];
        $settings->save();

        $posting = JobPosting::factory()->open()->create(['slug' => 'backend-engineer']);

        $this->post(route('careers.apply', $posting), [
            'candidate_name' => 'Jane Applicant',
            'candidate_email' => 'jane@example.com',
            'candidate_phone' => '+1 555 0100',
            'skills' => 'PHP, Laravel, Vue',
            'years_of_experience' => 5,
            'salary_expectation' => '90000 USD',
            'preferred_location' => 'Remote',
            'resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        ])
            ->assertRedirect(route('careers.show', $posting));

        $this->assertDatabaseHas('job_applications', [
            'job_posting_id' => $posting->id,
            'candidate_email' => 'jane@example.com',
            'status' => JobApplicationStatus::Received->value,
        ]);

        Mail::assertQueued(JobApplicationSubmittedMail::class, function (JobApplicationSubmittedMail $mail): bool {
            return $mail->hasTo('hr@example.com');
        });
    }

    public function test_cannot_apply_to_closed_posting(): void
    {
        $posting = JobPosting::factory()->closed()->create(['slug' => 'closed-role']);

        $this->post(route('careers.apply', $posting), [
            'candidate_name' => 'Jane Applicant',
            'candidate_email' => 'jane@example.com',
            'candidate_phone' => '+1 555 0100',
            'skills' => 'PHP',
            'years_of_experience' => 3,
            'salary_expectation' => '70000 USD',
            'preferred_location' => 'Remote',
            'resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_application_requires_valid_resume(): void
    {
        $posting = JobPosting::factory()->open()->create();

        $this->post(route('careers.apply', $posting), [
            'candidate_name' => 'Jane Applicant',
            'candidate_email' => 'jane@example.com',
            'candidate_phone' => '+1 555 0100',
            'skills' => 'PHP',
            'years_of_experience' => 3,
            'salary_expectation' => '70000 USD',
            'preferred_location' => 'Remote',
        ])->assertSessionHasErrors('resume');
    }
}
