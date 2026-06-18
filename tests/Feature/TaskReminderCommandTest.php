<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Team;
use App\Models\User;
use App\Notifications\TaskReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_task_reminder_is_sent_once_to_assignee_and_project_lead(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-27 12:00:00'));

        $team = Team::factory()->create();
        $lead = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $assignee = User::factory()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create([
            'client_user_id' => $client->id,
            'lead_user_id' => $lead->id,
        ]);
        $project->teams()->sync([$team->id]);

        $task = ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $lead->id,
            'assignee_user_id' => $assignee->id,
            'notify_at' => now()->subMinute(),
        ]);

        $this->artisan('tasks:send-due-reminders')->assertSuccessful();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'type' => TaskReminderNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $assignee->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => TaskReminderNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $lead->id,
        ]);
        $this->assertNotNull($task->fresh()->notified_at);

        $this->artisan('tasks:send-due-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 2);

        Carbon::setTestNow();
    }

    public function test_due_task_reminder_is_skipped_without_recipients(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-27 12:00:00'));

        $team = Team::factory()->create();
        $creator = User::factory()->teamHead()->withPrimaryTeam($team)->create();
        $client = User::factory()->client()->create();
        $project = Project::factory()->create([
            'client_user_id' => $client->id,
            'lead_user_id' => null,
        ]);
        $project->teams()->sync([$team->id]);

        $task = ProjectTask::factory()->forProject($project)->create([
            'created_by_user_id' => $creator->id,
            'assignee_user_id' => null,
            'notify_at' => now()->subMinute(),
        ]);

        $this->artisan('tasks:send-due-reminders')->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
        $this->assertNull($task->fresh()->notified_at);

        Carbon::setTestNow();
    }
}
