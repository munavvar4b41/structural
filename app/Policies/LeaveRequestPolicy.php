<?php

namespace App\Policies;

use App\Enums\LeaveRequestStatus;
use App\Enums\UserRole;
use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canApproveLeaveRequests();
    }

    public function view(User $actor, LeaveRequest $leaveRequest): bool
    {
        if ($leaveRequest->user_id === $actor->id) {
            return $actor->role !== UserRole::Client;
        }

        return $actor->role->canApproveLeaveRequests();
    }

    public function create(User $actor): bool
    {
        return $actor->isInternal() && $actor->role !== UserRole::Client;
    }

    public function cancel(User $actor, LeaveRequest $leaveRequest): bool
    {
        if ($leaveRequest->user_id !== $actor->id) {
            return false;
        }

        return $leaveRequest->status === LeaveRequestStatus::Pending;
    }

    public function approve(User $actor, LeaveRequest $leaveRequest): bool
    {
        if (! $actor->role->canApproveLeaveRequests()) {
            return false;
        }

        return $leaveRequest->status === LeaveRequestStatus::Pending;
    }

    public function reject(User $actor, LeaveRequest $leaveRequest): bool
    {
        return $this->approve($actor, $leaveRequest);
    }
}
