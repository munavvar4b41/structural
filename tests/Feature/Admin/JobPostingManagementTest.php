<?php

namespace Tests\Feature\Admin;

use App\Enums\JobEmploymentType;
use App\Enums\JobPostingStatus;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobPostingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_job_postings(): void
    {
        $admin = User::factory()->admin()->create();
        JobPosting::factory()->count(2)->create(['created_by_user_id' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('admin.job-postings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/job-postings/Index')
                ->has('job_postings.data', 2));
    }

    public function test_staff_cannot_access_job_postings(): void
    {
        $staff = User::factory()->withPrimaryTeam()->create();

        $this->actingAs($staff)
            ->get(route('admin.job-postings.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_job_posting(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.job-postings.store'), [
                'title' => 'Senior Developer',
                'slug' => 'senior-developer',
                'location' => 'Remote',
                'employment_type' => JobEmploymentType::FullTime->value,
                'description' => json_encode(['type' => 'doc', 'content' => []]),
                'requirements' => json_encode(['type' => 'doc', 'content' => []]),
                'status' => JobPostingStatus::Open->value,
                'published_at' => now()->subDay()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('admin.job-postings.index'));

        $this->assertDatabaseHas('job_postings', [
            'slug' => 'senior-developer',
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_update_job_posting(): void
    {
        $admin = User::factory()->admin()->create();
        $posting = JobPosting::factory()->create([
            'created_by_user_id' => $admin->id,
            'title' => 'Old Title',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.job-postings.update', $posting), [
                'title' => 'New Title',
                'slug' => $posting->slug,
                'location' => $posting->location,
                'employment_type' => $posting->employment_type->value,
                'description' => $posting->description,
                'requirements' => $posting->requirements,
                'status' => JobPostingStatus::Closed->value,
            ])
            ->assertRedirect(route('admin.job-postings.index'));

        $this->assertDatabaseHas('job_postings', [
            'id' => $posting->id,
            'title' => 'New Title',
            'status' => JobPostingStatus::Closed->value,
        ]);
    }

    public function test_admin_can_delete_job_posting(): void
    {
        $admin = User::factory()->admin()->create();
        $posting = JobPosting::factory()->create(['created_by_user_id' => $admin->id]);

        $this->actingAs($admin)
            ->delete(route('admin.job-postings.destroy', $posting))
            ->assertRedirect(route('admin.job-postings.index'));

        $this->assertDatabaseMissing('job_postings', ['id' => $posting->id]);
    }
}
