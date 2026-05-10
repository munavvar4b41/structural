<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeaveHalfDayPeriod;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLeaveRequestRequest;
use App\Mail\LeaveRequestSubmittedMail;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\LeaveRequestMailRecipients;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User || ! $actor->can('create', LeaveRequest::class), 403);

        $requests = LeaveRequest::query()
            ->where('user_id', $actor->id)
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('admin/leave-requests/Index', [
            'leave_requests' => $requests->map(fn (LeaveRequest $r): array => $this->leaveRequestPayload($r))->all(),
            'type_options' => $this->typeOptions(),
            'half_day_period_options' => $this->halfDayPeriodOptions(),
        ]);
    }

    public function manage(Request $request): Response
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User || ! $actor->can('viewAny', LeaveRequest::class), 403);

        $requests = LeaveRequest::query()
            ->with([
                'user:id,name,email',
                'reviewedBy:id,name',
            ])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('admin/leave-requests/Manage', [
            'leave_requests' => $requests->map(fn (LeaveRequest $r): array => $this->leaveRequestPayload($r))->all(),
        ]);
    }

    public function store(
        StoreLeaveRequestRequest $request,
        LeaveRequestMailRecipients $recipients,
    ): RedirectResponse {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $data = $request->validated();
        $type = LeaveType::from($data['type']);

        $attributes = [
            'user_id' => $actor->id,
            'type' => $type,
            'date' => $data['date'],
            'status' => LeaveRequestStatus::Pending,
            'reason' => $data['reason'] ?? null,
            'half_day_period' => null,
            'break_starts_at' => null,
            'break_ends_at' => null,
        ];

        if ($type === LeaveType::HalfDay) {
            $attributes['half_day_period'] = $data['half_day_period'];
        }

        if ($type === LeaveType::Break) {
            $attributes['break_starts_at'] = $data['break_starts_at'];
            $attributes['break_ends_at'] = $data['break_ends_at'];
        }

        $leaveRequest = LeaveRequest::query()->create($attributes);
        $leaveRequest->load('user:id,name,email');

        $emails = $recipients->forRequester($actor);
        if ($emails !== []) {
            Mail::to($emails)->queue(new LeaveRequestSubmittedMail($leaveRequest));
        }

        return to_route('admin.leave-requests.index')->with('toast', __('Leave request submitted.'));
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User || ! $actor->can('cancel', $leaveRequest), 403);

        $leaveRequest->forceFill([
            'status' => LeaveRequestStatus::Cancelled,
        ])->save();

        return to_route('admin.leave-requests.index')->with('toast', __('Leave request cancelled.'));
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User || ! $actor->can('approve', $leaveRequest), 403);

        $leaveRequest->forceFill([
            'status' => LeaveRequestStatus::Approved,
            'reviewed_by_user_id' => $actor->id,
            'reviewed_at' => now(),
        ])->save();

        return to_route('admin.leave-requests.manage')->with('toast', __('Leave request approved.'));
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User || ! $actor->can('reject', $leaveRequest), 403);

        $leaveRequest->forceFill([
            'status' => LeaveRequestStatus::Rejected,
            'reviewed_by_user_id' => $actor->id,
            'reviewed_at' => now(),
        ])->save();

        return to_route('admin.leave-requests.manage')->with('toast', __('Leave request rejected.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function leaveRequestPayload(LeaveRequest $r): array
    {
        return [
            'id' => $r->id,
            'type' => $r->type->value,
            'type_label' => $r->type->label(),
            'date' => $r->date->toDateString(),
            'half_day_period' => $r->half_day_period?->value,
            'half_day_period_label' => $r->half_day_period?->label(),
            'break_starts_at' => $r->break_starts_at?->toIso8601String(),
            'break_ends_at' => $r->break_ends_at?->toIso8601String(),
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'reason' => $r->reason,
            'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            'reviewed_by' => $r->relationLoaded('reviewedBy') && $r->reviewedBy !== null
                ? [
                    'id' => $r->reviewedBy->id,
                    'name' => $r->reviewedBy->name,
                ]
                : null,
            'user' => $r->relationLoaded('user') && $r->user !== null
                ? [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                    'email' => $r->user->email,
                ]
                : null,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return collect(LeaveType::cases())
            ->map(static fn (LeaveType $t): array => [
                'value' => $t->value,
                'label' => $t->label(),
            ])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function halfDayPeriodOptions(): array
    {
        return collect(LeaveHalfDayPeriod::cases())
            ->map(static fn (LeaveHalfDayPeriod $p): array => [
                'value' => $p->value,
                'label' => $p->label(),
            ])
            ->all();
    }
}
