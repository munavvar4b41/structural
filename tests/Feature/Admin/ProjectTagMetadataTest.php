<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\ProjectMetadata;
use App\Models\ProjectTag;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTagMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_and_remove_project_tag(): void
    {
        $admin = User::factory()->admin()->create(['primary_team_id' => null]);
        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.projects.tags.store', $project), ['name' => 'Manage User'])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertDatabaseHas('project_tags', [
            'project_id' => $project->id,
            'name' => 'manage-user',
        ]);

        $tag = ProjectTag::query()->where('project_id', $project->id)->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.projects.tags.destroy', [$project, $tag]))
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertDatabaseMissing('project_tags', ['id' => $tag->id]);
    }

    public function test_duplicate_tag_on_same_project_is_rejected(): void
    {
        $admin = User::factory()->admin()->create(['primary_team_id' => null]);
        $project = Project::factory()->create();
        ProjectTag::factory()->create(['project_id' => $project->id, 'name' => 'manage-user']);

        $this->actingAs($admin)
            ->from(route('admin.projects.show', $project))
            ->post(route('admin.projects.tags.store', $project), ['name' => 'manage-user'])
            ->assertSessionHasErrors('name');
    }

    public function test_staff_cannot_manage_tags_on_assigned_project(): void
    {
        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $project = Project::factory()->create();
        $project->teams()->sync([$team->id]);

        $this->actingAs($staff)
            ->post(route('admin.projects.tags.store', $project), ['name' => 'manage-user'])
            ->assertForbidden();
    }

    public function test_admin_can_add_update_and_remove_project_metadata(): void
    {
        $admin = User::factory()->admin()->create(['primary_team_id' => null]);
        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.projects.metadata.store', $project), [
                'key' => 'Framework',
                'value' => 'laravel',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $metadata = ProjectMetadata::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertSame('framework', $metadata->key);
        $this->assertSame('laravel', $metadata->value);

        $this->actingAs($admin)
            ->patch(route('admin.projects.metadata.update', [$project, $metadata]), [
                'value' => 'mysql',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertDatabaseHas('project_metadata', [
            'id' => $metadata->id,
            'key' => 'framework',
            'value' => 'mysql',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.projects.metadata.destroy', [$project, $metadata]))
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertDatabaseMissing('project_metadata', ['id' => $metadata->id]);
    }

    public function test_duplicate_metadata_key_on_same_project_is_rejected(): void
    {
        $admin = User::factory()->admin()->create(['primary_team_id' => null]);
        $project = Project::factory()->create();
        ProjectMetadata::factory()->create([
            'project_id' => $project->id,
            'key' => 'framework',
            'value' => 'laravel',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.projects.show', $project))
            ->post(route('admin.projects.metadata.store', $project), [
                'key' => 'framework',
                'value' => 'symfony',
            ])
            ->assertSessionHasErrors('key');
    }
}
