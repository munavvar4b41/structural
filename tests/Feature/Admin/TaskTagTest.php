<?php

namespace Tests\Feature\Admin;

use App\Enums\TaskPriorityColor;
use App\Enums\TaskPriorityShade;
use App\Models\ProjectTask;
use App\Models\TaskPriority;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskPriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_priorities_index(): void
    {
        $this->get(route('admin.task-priorities.index'))
            ->assertRedirect(route('login'));
    }

    public function test_staff_cannot_view_priorities_index(): void
    {
        $user = User::factory()->withPrimaryTeam()->create();

        $this->actingAs($user)
            ->get(route('admin.task-priorities.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_priorities_index(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        TaskPriority::factory()->create(['name' => 'High', 'sort_order' => 1]);

        $this->actingAs($admin)
            ->get(route('admin.task-priorities.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/task-priorities/Index')
                ->has('priorities.data', 1)
                ->where('priorities.data.0.name', 'High'));
    }

    public function test_admin_can_store_priority(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();

        $this->actingAs($admin)
            ->post(route('admin.task-priorities.store'), [
                'name' => 'Urgent',
                'color' => TaskPriorityColor::Red->value,
                'shade' => TaskPriorityShade::Dark->value,
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.task-priorities.index'));

        $this->assertDatabaseHas('task_priorities', [
            'name' => 'Urgent',
            'color' => TaskPriorityColor::Red->value,
            'shade' => TaskPriorityShade::Dark->value,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_cannot_store_priority_with_invalid_color(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();

        $this->actingAs($admin)
            ->post(route('admin.task-priorities.store'), [
                'name' => 'Urgent',
                'color' => 'magenta',
                'shade' => TaskPriorityShade::Light->value,
                'sort_order' => 1,
            ])
            ->assertSessionHasErrors('color');

        $this->assertDatabaseMissing('task_priorities', [
            'name' => 'Urgent',
        ]);
    }

    public function test_admin_can_update_priority(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        $priority = TaskPriority::factory()->create([
            'name' => 'Old',
            'color' => TaskPriorityColor::Blue,
            'shade' => TaskPriorityShade::Light,
            'sort_order' => 2,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.task-priorities.update', $priority), [
                'name' => 'Medium',
                'color' => TaskPriorityColor::Amber->value,
                'shade' => TaskPriorityShade::Dark->value,
                'sort_order' => 3,
            ])
            ->assertRedirect(route('admin.task-priorities.index'));

        $priority->refresh();

        $this->assertSame('Medium', $priority->name);
        $this->assertSame(TaskPriorityColor::Amber, $priority->color);
        $this->assertSame(TaskPriorityShade::Dark, $priority->shade);
        $this->assertSame(3, $priority->sort_order);
    }

    public function test_admin_can_delete_unused_priority(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        $priority = TaskPriority::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.task-priorities.destroy', $priority))
            ->assertRedirect(route('admin.task-priorities.index'));

        $this->assertDatabaseMissing('task_priorities', ['id' => $priority->id]);
    }

    public function test_admin_cannot_delete_priority_while_tasks_use_it(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        $priority = TaskPriority::factory()->create();
        ProjectTask::factory()->create(['task_priority_id' => $priority->id]);

        $this->actingAs($admin)
            ->delete(route('admin.task-priorities.destroy', $priority))
            ->assertSessionHasErrors('priority');

        $this->assertDatabaseHas('task_priorities', ['id' => $priority->id]);
    }
}
