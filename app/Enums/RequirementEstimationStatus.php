<?php

namespace App\Enums;

enum RequirementEstimationStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
    case Transferred = 'transferred';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending approval',
            self::ChangesRequested => 'Changes requested',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Superseded => 'Superseded',
            self::Transferred => 'Transferred',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested], true);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval, self::ChangesRequested], true);
    }
}
