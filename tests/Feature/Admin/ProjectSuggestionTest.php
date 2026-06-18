<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\ProjectMetadata;
use App\Models\ProjectRequirement;
use App\Models\ProjectTag;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectSuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggestions_return_prefix_matches_scoped_to_visible_projects(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($teamA)->create();

        $visibleProject = Project::factory()->create();
        $visibleProject->teams()->sync([$teamA->id]);
        ProjectTag::factory()->create(['project_id' => $visibleProject->id, 'name' => 'manage-user']);
        ProjectMetadata::factory()->create([
            'project_id' => $visibleProject->id,
            'key' => 'framework',
            'value' => 'laravel',
        ]);
        ProjectRequirement::factory()->create([
            'project_id' => $visibleProject->id,
            'title' => 'Framework upgrade',
        ]);
        $task = ProjectTask::factory()->create([
            'project_id' => $visibleProject->id,
            'title' => 'Framework migration',
        ]);
        TaskTimeEntry::factory()->create([
            'project_id' => $visibleProject->id,
            'project_task_id' => $task->id,
            'user_id' => $staff->id,
            'notes' => 'framework research',
        ]);

        $hiddenProject = Project::factory()->create();
        $hiddenProject->teams()->sync([$teamB->id]);
        ProjectTag::factory()->create(['project_id' => $hiddenProject->id, 'name' => 'hidden-tag']);
        ProjectMetadata::factory()->create([
            'project_id' => $hiddenProject->id,
            'key' => 'framework',
            'value' => 'hidden-value',
        ]);

        $this->actingAs($staff)
            ->getJson(route('admin.suggestions.index', ['type' => 'tag', 'q' => 'man']))
            ->assertOk()
            ->assertJson(['suggestions' => ['manage-user']]);

        $this->actingAs($staff)
            ->getJson(route('admin.suggestions.index', ['type' => 'metadata_key', 'q' => 'fra']))
            ->assertOk()
            ->assertJson(['suggestions' => ['framework']]);

        $this->actingAs($staff)
            ->getJson(route('admin.suggestions.index', [
                'type' => 'metadata_value',
                'q' => 'lar',
                'key' => 'framework',
            ]))
            ->assertOk()
            ->assertJson(['suggestions' => ['laravel']]);

        $this->actingAs($staff)
            ->getJson(route('admin.suggestions.index', ['type' => 'requirement_title', 'q' => 'Frame']))
            ->assertOk()
            ->assertJson(['suggestions' => ['Framework upgrade']]);

        $this->actingAs($staff)
            ->getJson(route('admin.suggestions.index', ['type' => 'task_title', 'q' => 'Frame']))
            ->assertOk()
            ->assertJson(['suggestions' => ['Framework migration']]);

        $this->actingAs($staff)
            ->getJson(route('admin.suggestions.index', ['type' => 'time_entry_notes', 'q' => 'frame']))
            ->assertOk()
            ->assertJson(['suggestions' => ['framework research']]);

        $this->actingAs($staff)
            ->getJson(route('admin.suggestions.index', ['type' => 'tag', 'q' => 'hid']))
            ->assertOk()
            ->assertJson(['suggestions' => []]);
    }

    public function test_invalid_suggestion_type_returns_empty_list(): void
    {
        $admin = User::factory()->admin()->create(['primary_team_id' => null]);

        $this->actingAs($admin)
            ->getJson(route('admin.suggestions.index', ['type' => 'unknown', 'q' => 'x']))
            ->assertOk()
            ->assertJson(['suggestions' => []]);
    }
}
