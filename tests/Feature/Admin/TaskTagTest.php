<?php

namespace Tests\Feature\Admin;

use App\Enums\TaskTagColor;
use App\Enums\TaskTagShade;
use App\Models\ProjectTask;
use App\Models\TaskTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_tags_index(): void
    {
        $this->get(route('admin.task-tags.index'))
            ->assertRedirect(route('login'));
    }

    public function test_staff_cannot_view_tags_index(): void
    {
        $user = User::factory()->withPrimaryTeam()->create();

        $this->actingAs($user)
            ->get(route('admin.task-tags.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_tags_index(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        TaskTag::factory()->create(['name' => 'High', 'sort_order' => 1]);

        $this->actingAs($admin)
            ->get(route('admin.task-tags.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/task-tags/Index')
                ->has('tags.data', 1)
                ->where('tags.data.0.name', 'High'));
    }

    public function test_admin_can_store_tag(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();

        $this->actingAs($admin)
            ->post(route('admin.task-tags.store'), [
                'name' => 'Urgent',
                'color' => TaskTagColor::Red->value,
                'shade' => TaskTagShade::Dark->value,
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.task-tags.index'));

        $this->assertDatabaseHas('task_tags', [
            'name' => 'Urgent',
            'color' => TaskTagColor::Red->value,
            'shade' => TaskTagShade::Dark->value,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_cannot_store_tag_with_invalid_color(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();

        $this->actingAs($admin)
            ->post(route('admin.task-tags.store'), [
                'name' => 'Urgent',
                'color' => 'magenta',
                'shade' => TaskTagShade::Light->value,
                'sort_order' => 1,
            ])
            ->assertSessionHasErrors('color');

        $this->assertDatabaseMissing('task_tags', [
            'name' => 'Urgent',
        ]);
    }

    public function test_admin_can_update_tag(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        $tag = TaskTag::factory()->create([
            'name' => 'Old',
            'color' => TaskTagColor::Blue,
            'shade' => TaskTagShade::Light,
            'sort_order' => 2,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.task-tags.update', $tag), [
                'name' => 'Medium',
                'color' => TaskTagColor::Amber->value,
                'shade' => TaskTagShade::Dark->value,
                'sort_order' => 3,
            ])
            ->assertRedirect(route('admin.task-tags.index'));

        $tag->refresh();

        $this->assertSame('Medium', $tag->name);
        $this->assertSame(TaskTagColor::Amber, $tag->color);
        $this->assertSame(TaskTagShade::Dark, $tag->shade);
        $this->assertSame(3, $tag->sort_order);
    }

    public function test_admin_can_delete_unused_tag(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        $tag = TaskTag::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.task-tags.destroy', $tag))
            ->assertRedirect(route('admin.task-tags.index'));

        $this->assertDatabaseMissing('task_tags', ['id' => $tag->id]);
    }

    public function test_admin_cannot_delete_tag_while_tasks_use_it(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        $tag = TaskTag::factory()->create();
        ProjectTask::factory()->create()->tags()->attach($tag);

        $this->actingAs($admin)
            ->delete(route('admin.task-tags.destroy', $tag))
            ->assertSessionHasErrors('tag');

        $this->assertDatabaseHas('task_tags', ['id' => $tag->id]);
    }
}
