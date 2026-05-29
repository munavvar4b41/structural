<?php

namespace Tests\Feature\Admin;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Team;
use App\Models\User;
use App\Notifications\TaskReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationMarkAsReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_mark_their_notification_as_read(): void
    {
        $team = Team::factory()->create();
        $lead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create([
            'client_user_id' => $client->id,
            'lead_user_id' => $lead->id,
        ]);
        $project->teams()->sync([$team->id]);

        $task = ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $lead->id,
            'assignee_user_id' => $staff->id,
            'status' => ProjectTaskStatus::ToDo,
        ]);

        $staff->notify(new TaskReminderNotification($task));

        $notification = $staff->unreadNotifications->first();
        $this->assertNotNull($notification);

        $this->actingAs($staff)
            ->from(route('admin.my-work.index'))
            ->patch(route('admin.notifications.read', $notification->id))
            ->assertRedirect(route('admin.my-work.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $team = Team::factory()->create();
        $owner = User::factory()->withPrimaryTeam($team)->create();
        $other = User::factory()->withPrimaryTeam($team)->create();

        $owner->notify(new TaskReminderNotification(
            ProjectTask::factory()->create([
                'created_by_user_id' => $owner->id,
                'assignee_user_id' => $owner->id,
            ]),
        ));

        $notification = $owner->unreadNotifications->first();
        $this->assertNotNull($notification);

        $this->actingAs($other)
            ->patch(route('admin.notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }
}
