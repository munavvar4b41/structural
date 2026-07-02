<?php

namespace Tests\Feature\Admin;

use App\Models\CaseStudy;
use App\Models\CaseStudyAttachment;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CaseStudyManagementTest extends TestCase
{
    use RefreshDatabase;

    private function tipTapJson(string $plainText): string
    {
        return (string) json_encode([
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => $plainText],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array{team: Team, head: User, client: User, project: Project}
     */
    private function projectWithTeamHead(): array
    {
        $team = Team::factory()->create();
        $head = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $project->teams()->sync([$team->id]);

        return ['team' => $team, 'head' => $head, 'client' => $client, 'project' => $project];
    }

    public function test_guest_is_redirected_from_case_studies_index(): void
    {
        $this->get(route('admin.case-studies.index'))
            ->assertRedirect(route('login'));
    }

    public function test_client_cannot_view_case_studies_index(): void
    {
        extract($this->projectWithTeamHead());

        $this->actingAs($client)
            ->get(route('admin.case-studies.index'))
            ->assertForbidden();
    }

    public function test_client_cannot_view_project_case_studies_index(): void
    {
        extract($this->projectWithTeamHead());

        $this->actingAs($client)
            ->get(route('admin.projects.case-studies.index', $project))
            ->assertForbidden();
    }

    public function test_team_head_can_view_global_case_studies_index(): void
    {
        extract($this->projectWithTeamHead());

        CaseStudy::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $head->id,
            'title' => 'Automation case study',
        ]);

        $this->actingAs($head)
            ->get(route('admin.case-studies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/case-studies/Index')
                ->has('case_studies.data', 1)
                ->where('case_studies.data.0.title', 'Automation case study'));
    }

    public function test_team_head_can_store_case_study_with_task_link(): void
    {
        extract($this->projectWithTeamHead());

        $task = ProjectTask::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $head->id,
        ]);

        $this->actingAs($head)
            ->post(route('admin.projects.case-studies.store', $project), [
                'title' => 'Reduced manual reporting',
                'project_task_id' => $task->id,
                'overview' => $this->tipTapJson('Saved hours every week'),
                'client_issue' => $this->tipTapJson('Manual exports took hours and delayed client decisions'),
                'our_solution' => $this->tipTapJson('Mapped the workflow and proposed an automated dashboard'),
                'implementation' => $this->tipTapJson('Built scheduled exports'),
                'other_details' => $this->tipTapJson('Legacy spreadsheets were retired'),
                'result_and_impact' => $this->tipTapJson('Reports now run automatically'),
                'conclusion' => $this->tipTapJson('Team spends less time exporting'),
            ])
            ->assertRedirect();

        $caseStudy = CaseStudy::query()->where('title', 'Reduced manual reporting')->first();
        $this->assertNotNull($caseStudy);
        $this->assertSame($task->id, $caseStudy->project_task_id);
        $this->assertSame($head->id, $caseStudy->created_by_user_id);
    }

    public function test_project_task_id_must_belong_to_project(): void
    {
        extract($this->projectWithTeamHead());

        $otherTeam = Team::factory()->create();
        $otherHead = User::factory()->teamHead()->withPrimaryTeam($otherTeam)->create();
        $otherClient = User::factory()->client()->create();
        $otherProject = Project::factory()->create(['client_user_id' => $otherClient->id]);
        $otherProject->teams()->sync([$otherTeam->id]);

        $foreignTask = ProjectTask::factory()->create([
            'project_id' => $otherProject->id,
            'created_by_user_id' => $otherHead->id,
        ]);

        $this->actingAs($head)
            ->post(route('admin.projects.case-studies.store', $project), [
                'title' => 'Invalid task link',
                'project_task_id' => $foreignTask->id,
            ])
            ->assertSessionHasErrors('project_task_id');

        $this->assertDatabaseMissing('case_studies', [
            'title' => 'Invalid task link',
        ]);
    }

    public function test_show_page_returns_narrative_sections(): void
    {
        extract($this->projectWithTeamHead());

        $caseStudy = CaseStudy::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $head->id,
            'title' => 'Show page case study',
            'client_issue' => $this->tipTapJson('Client issue text'),
            'our_solution' => $this->tipTapJson('Proposed fix'),
            'conclusion' => $this->tipTapJson('Issue resolved'),
        ]);

        $this->actingAs($head)
            ->get(route('admin.projects.case-studies.show', [$project, $caseStudy]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/case-studies/Show')
                ->where('case_study.title', 'Show page case study')
                ->where('case_study.client_issue', $caseStudy->client_issue)
                ->where('case_study.our_solution', $caseStudy->our_solution)
                ->where('case_study.conclusion', $caseStudy->conclusion)
                ->where('can_update', true)
                ->where('can_delete', true));
    }

    public function test_store_with_titled_documents_and_authorized_download(): void
    {
        Storage::fake('local');

        extract($this->projectWithTeamHead());

        $file = UploadedFile::fake()->create('diagram.pdf', 100, 'application/pdf');

        $this->actingAs($head)
            ->post(route('admin.projects.case-studies.store', $project), [
                'title' => 'Case study with files',
                'documents' => [
                    [
                        'title' => 'Workflow diagram',
                        'file' => $file,
                    ],
                ],
            ])
            ->assertRedirect();

        $caseStudy = CaseStudy::query()->where('title', 'Case study with files')->first();
        $this->assertNotNull($caseStudy);
        $this->assertCount(1, $caseStudy->attachments);

        $attachment = $caseStudy->attachments->first();
        $this->assertNotNull($attachment);
        $this->assertSame('Workflow diagram', $attachment->title);
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($head)
            ->get(route('admin.case-studies.attachments.show', [$caseStudy, $attachment]))
            ->assertOk();
    }

    public function test_update_can_remove_attachments_and_delete_case_study_cleans_storage(): void
    {
        Storage::fake('local');

        extract($this->projectWithTeamHead());

        $caseStudy = CaseStudy::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $head->id,
            'title' => 'Attachment cleanup',
        ]);

        $attachment = CaseStudyAttachment::factory()->create([
            'case_study_id' => $caseStudy->id,
            'path' => 'case-studies/'.$caseStudy->id.'/test.pdf',
        ]);

        Storage::disk('local')->put($attachment->path, 'pdf-content');

        $this->actingAs($head)
            ->patch(route('admin.projects.case-studies.update', [$project, $caseStudy]), [
                'title' => 'Attachment cleanup',
                'remove_attachment_ids' => [$attachment->id],
            ])
            ->assertRedirect(route('admin.projects.case-studies.show', [$project, $caseStudy]));

        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertDatabaseMissing('case_study_attachments', ['id' => $attachment->id]);

        $replacement = CaseStudyAttachment::factory()->create([
            'case_study_id' => $caseStudy->id,
            'path' => 'case-studies/'.$caseStudy->id.'/keep.pdf',
        ]);
        Storage::disk('local')->put($replacement->path, 'keep');

        $this->actingAs($head)
            ->delete(route('admin.projects.case-studies.destroy', [$project, $caseStudy]))
            ->assertRedirect(route('admin.case-studies.index', ['project_id' => $project->id]));

        Storage::disk('local')->assertMissing($replacement->path);
        $this->assertDatabaseMissing('case_studies', ['id' => $caseStudy->id]);
    }

    public function test_task_show_includes_linked_case_studies(): void
    {
        extract($this->projectWithTeamHead());

        $task = ProjectTask::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $head->id,
        ]);

        CaseStudy::factory()->forTask($task)->create([
            'created_by_user_id' => $head->id,
            'title' => 'Linked case study',
        ]);

        $this->actingAs($head)
            ->get(route('admin.projects.tasks.show', [$project, $task]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('case_studies', 1)
                ->where('case_studies.0.title', 'Linked case study')
                ->where('can_create_case_study', true));
    }
}
