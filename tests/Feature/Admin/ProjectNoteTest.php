<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_on_assigned_team_can_create_a_note_and_see_it_on_the_project(): void
    {
        [$staff, $project] = $this->staffOnProject();

        $this->actingAs($staff)
            ->post(route('admin.projects.notes.store', $project), [
                'title' => '  Kickoff ideas  ',
                'body' => "  Capture the billing rules.\nAsk about tax.  ",
                'created_by_user_id' => User::factory()->create()->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $note = ProjectNote::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertSame('Kickoff ideas', $note->title);
        $this->assertSame("Capture the billing rules.\nAsk about tax.", $note->body);
        $this->assertSame($staff->id, $note->created_by_user_id);

        $this->actingAs($staff)
            ->get(route('admin.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/Show')
                ->where('can_create_notes', true)
                ->has('notes', 1)
                ->where('notes.0.id', $note->id)
                ->where('notes.0.title', 'Kickoff ideas')
                ->where('notes.0.body', "Capture the billing rules.\nAsk about tax.")
                ->where('notes.0.creator.id', $staff->id)
                ->where('notes.0.creator.name', $staff->name)
                ->where('notes.0.can_update', true)
                ->where('notes.0.can_delete', true));
    }

    public function test_author_can_update_and_delete_their_own_note(): void
    {
        [$staff, $project] = $this->staffOnProject();
        $note = ProjectNote::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $staff->id,
            'title' => 'Original',
            'body' => 'First draft.',
        ]);

        $this->actingAs($staff)
            ->patch(route('admin.projects.notes.update', [$project, $note]), [
                'title' => 'Revised',
                'body' => 'Second draft.',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertDatabaseHas('project_notes', [
            'id' => $note->id,
            'title' => 'Revised',
            'body' => 'Second draft.',
            'created_by_user_id' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->delete(route('admin.projects.notes.destroy', [$project, $note]))
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertDatabaseMissing('project_notes', ['id' => $note->id]);
    }

    public function test_other_staff_cannot_update_or_delete_someone_elses_note(): void
    {
        $team = Team::factory()->create();
        $author = User::factory()->withPrimaryTeam($team)->create();
        $otherStaff = User::factory()->withPrimaryTeam($team)->create();
        $project = Project::factory()->create();
        $project->teams()->sync([$team->id]);
        $note = ProjectNote::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $author->id,
        ]);

        $this->actingAs($otherStaff)
            ->get(route('admin.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('notes.0.can_update', false)
                ->where('notes.0.can_delete', false));

        $this->actingAs($otherStaff)
            ->patch(route('admin.projects.notes.update', [$project, $note]), [
                'title' => 'Changed',
                'body' => 'Should not stick.',
            ])
            ->assertForbidden();

        $this->actingAs($otherStaff)
            ->delete(route('admin.projects.notes.destroy', [$project, $note]))
            ->assertForbidden();

        $this->assertDatabaseHas('project_notes', [
            'id' => $note->id,
            'title' => $note->title,
            'body' => $note->body,
        ]);
    }

    public function test_team_head_can_update_and_delete_another_users_note(): void
    {
        $team = Team::factory()->create();
        $author = User::factory()->withPrimaryTeam($team)->create();
        $teamHead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $project = Project::factory()->create();
        $project->teams()->sync([$team->id]);
        $note = ProjectNote::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $author->id,
            'title' => 'Author note',
            'body' => 'Keep this detail.',
        ]);

        $this->actingAs($teamHead)
            ->patch(route('admin.projects.notes.update', [$project, $note]), [
                'title' => 'Manager edit',
                'body' => 'Updated by the team head.',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->actingAs($teamHead)
            ->delete(route('admin.projects.notes.destroy', [$project, $note]))
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertDatabaseMissing('project_notes', ['id' => $note->id]);
    }

    public function test_admin_can_update_and_delete_another_users_note(): void
    {
        $author = User::factory()->create();
        $admin = User::factory()->admin()->create(['primary_team_id' => null]);
        $project = Project::factory()->create();
        $note = ProjectNote::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $author->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.projects.notes.update', [$project, $note]), [
                'title' => 'Admin edit',
                'body' => 'Corrected detail.',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertDatabaseHas('project_notes', [
            'id' => $note->id,
            'title' => 'Admin edit',
            'body' => 'Corrected detail.',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.projects.notes.destroy', [$project, $note]))
            ->assertRedirect();

        $this->assertDatabaseMissing('project_notes', ['id' => $note->id]);
    }

    public function test_client_cannot_see_or_manage_notes(): void
    {
        $client = User::factory()->client()->create();
        $project = Project::factory()->create(['client_user_id' => $client->id]);
        $note = ProjectNote::factory()->create([
            'project_id' => $project->id,
            'title' => 'Internal only',
            'body' => 'Do not show this to the client.',
        ]);

        $this->actingAs($client)
            ->get(route('admin.projects.show', $project))
            ->assertOk()
            ->assertDontSee('Internal only')
            ->assertDontSee('Do not show this to the client.')
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_create_notes', false)
                ->has('notes', 0));

        $this->actingAs($client)
            ->post(route('admin.projects.notes.store', $project), [
                'title' => 'Client note',
                'body' => 'Should be rejected.',
            ])
            ->assertForbidden();

        $this->actingAs($client)
            ->patch(route('admin.projects.notes.update', [$project, $note]), [
                'title' => 'Client edit',
                'body' => 'Should be rejected.',
            ])
            ->assertForbidden();

        $this->actingAs($client)
            ->delete(route('admin.projects.notes.destroy', [$project, $note]))
            ->assertForbidden();

        $this->assertDatabaseHas('project_notes', [
            'id' => $note->id,
            'title' => 'Internal only',
        ]);
    }

    public function test_staff_outside_the_project_cannot_create_a_note(): void
    {
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $project = Project::factory()->create();
        $project->teams()->sync([$otherTeam->id]);

        $this->actingAs($staff)
            ->post(route('admin.projects.notes.store', $project), [
                'title' => 'Outside',
                'body' => 'Should be rejected.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('project_notes', 0);
    }

    public function test_note_from_another_project_is_not_found(): void
    {
        $admin = User::factory()->admin()->create(['primary_team_id' => null]);
        $project = Project::factory()->create();
        $otherNote = ProjectNote::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.projects.notes.update', [$project, $otherNote]), [
                'title' => 'Wrong project',
                'body' => 'Should 404.',
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('admin.projects.notes.destroy', [$project, $otherNote]))
            ->assertNotFound();

        $this->assertDatabaseHas('project_notes', ['id' => $otherNote->id]);
    }

    public function test_missing_title_or_body_fails_validation(): void
    {
        [$staff, $project] = $this->staffOnProject();

        $this->actingAs($staff)
            ->from(route('admin.projects.show', $project))
            ->post(route('admin.projects.notes.store', $project), [
                'title' => '   ',
                'body' => '',
            ])
            ->assertRedirect(route('admin.projects.show', $project))
            ->assertSessionHasErrors(['title', 'body']);

        $this->assertDatabaseCount('project_notes', 0);
    }

    public function test_notes_are_listed_newest_first(): void
    {
        [$staff, $project] = $this->staffOnProject();
        $older = ProjectNote::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $staff->id,
            'title' => 'Older',
            'created_at' => now()->subDay(),
        ]);
        $newer = ProjectNote::factory()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $staff->id,
            'title' => 'Newer',
            'created_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('admin.projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('notes.0.id', $newer->id)
                ->where('notes.1.id', $older->id));
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function staffOnProject(): array
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $project = Project::factory()->create();
        $project->teams()->sync([$team->id]);

        return [$staff, $project];
    }
}
