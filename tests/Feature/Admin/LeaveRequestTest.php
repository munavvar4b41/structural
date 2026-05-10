<?php

namespace Tests\Feature\Admin;

use App\Enums\LeaveHalfDayPeriod;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveType;
use App\Mail\LeaveRequestSubmittedMail;
use App\Models\LeaveRequest;
use App\Models\Team;
use App\Models\User;
use App\Settings\LeaveSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_submit_full_day_leave_and_queues_notification_mail(): void
    {
        Mail::fake();

        $settings = app(LeaveSettings::class);
        $settings->notification_emails = ['notify@example.com'];
        $settings->save();

        $staff = User::factory()->withPrimaryTeam()->create();

        $tomorrow = now()->addDay()->toDateString();

        $this->actingAs($staff)
            ->post(route('admin.leave-requests.store'), [
                'type' => LeaveType::FullDay->value,
                'date' => $tomorrow,
                'reason' => 'Travel',
            ])
            ->assertRedirect(route('admin.leave-requests.index'));

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $staff->id,
            'type' => LeaveType::FullDay->value,
            'status' => LeaveRequestStatus::Pending->value,
        ]);

        Mail::assertQueued(LeaveRequestSubmittedMail::class);
    }

    public function test_team_head_on_shared_team_receives_leave_mail(): void
    {
        Mail::fake();

        $team = Team::factory()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $head = User::factory()->teamHead()->create();
        $head->teams()->attach($team->id);

        $settings = app(LeaveSettings::class);
        $settings->notification_emails = [];
        $settings->save();

        $tomorrow = now()->addDay()->toDateString();

        $this->actingAs($staff)
            ->post(route('admin.leave-requests.store'), [
                'type' => LeaveType::FullDay->value,
                'date' => $tomorrow,
            ])
            ->assertRedirect(route('admin.leave-requests.index'));

        Mail::assertQueued(LeaveRequestSubmittedMail::class, function (LeaveRequestSubmittedMail $mail) use ($head): bool {
            return $mail->hasTo($head->email);
        });
    }

    public function test_client_cannot_access_leave_request_index(): void
    {
        $client = User::factory()->client()->withPrimaryTeam()->create();

        $this->actingAs($client)
            ->get(route('admin.leave-requests.index'))
            ->assertForbidden();
    }

    public function test_admin_can_approve_pending_leave(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        $staff = User::factory()->withPrimaryTeam()->create();

        $leave = LeaveRequest::factory()->for($staff)->create([
            'status' => LeaveRequestStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.leave-requests.approve', $leave))
            ->assertRedirect(route('admin.leave-requests.manage'));

        $leave->refresh();
        $this->assertSame(LeaveRequestStatus::Approved, $leave->status);
        $this->assertSame($admin->id, $leave->reviewed_by_user_id);
    }

    public function test_team_head_cannot_approve_leave(): void
    {
        $head = User::factory()->teamHead()->withPrimaryTeam()->create();
        $staff = User::factory()->withPrimaryTeam()->create();

        $leave = LeaveRequest::factory()->for($staff)->create([
            'status' => LeaveRequestStatus::Pending,
        ]);

        $this->actingAs($head)
            ->patch(route('admin.leave-requests.approve', $leave))
            ->assertForbidden();
    }

    public function test_staff_can_cancel_pending_leave(): void
    {
        $staff = User::factory()->withPrimaryTeam()->create();
        $leave = LeaveRequest::factory()->for($staff)->create([
            'status' => LeaveRequestStatus::Pending,
        ]);

        $this->actingAs($staff)
            ->delete(route('admin.leave-requests.destroy', $leave))
            ->assertRedirect(route('admin.leave-requests.index'));

        $leave->refresh();
        $this->assertSame(LeaveRequestStatus::Cancelled, $leave->status);
    }

    public function test_break_must_be_exactly_one_hour(): void
    {
        $staff = User::factory()->withPrimaryTeam()->create();
        $date = now()->addDay()->toDateString();
        $start = now()->parse($date.' 10:00:00', config('app.timezone'));

        $this->actingAs($staff)
            ->post(route('admin.leave-requests.store'), [
                'type' => LeaveType::Break->value,
                'date' => $date,
                'break_starts_at' => $start->toIso8601String(),
                'break_ends_at' => $start->copy()->addMinutes(30)->toIso8601String(),
            ])
            ->assertSessionHasErrors('break_ends_at');
    }

    public function test_super_admin_can_update_leave_notification_settings(): void
    {
        $admin = User::factory()->superAdmin()->withPrimaryTeam()->create();

        $this->actingAs($admin)
            ->patch(route('admin.leave-settings.update'), [
                'notification_emails_text' => "One@Example.com\r\n two@example.com ",
            ])
            ->assertRedirect(route('admin.leave-settings.edit'));

        $settings = app(LeaveSettings::class);
        $this->assertSame(['one@example.com', 'two@example.com'], $settings->notification_emails);
    }

    public function test_half_day_requires_period(): void
    {
        $staff = User::factory()->withPrimaryTeam()->create();

        $this->actingAs($staff)
            ->post(route('admin.leave-requests.store'), [
                'type' => LeaveType::HalfDay->value,
                'date' => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors('half_day_period');
    }

    public function test_valid_half_day_is_accepted(): void
    {
        Mail::fake();

        $staff = User::factory()->withPrimaryTeam()->create();

        $this->actingAs($staff)
            ->post(route('admin.leave-requests.store'), [
                'type' => LeaveType::HalfDay->value,
                'date' => now()->addDay()->toDateString(),
                'half_day_period' => LeaveHalfDayPeriod::SecondHalf->value,
            ])
            ->assertRedirect(route('admin.leave-requests.index'));

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $staff->id,
            'half_day_period' => LeaveHalfDayPeriod::SecondHalf->value,
        ]);
    }
}
