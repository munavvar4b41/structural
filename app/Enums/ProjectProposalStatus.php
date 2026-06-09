<?php

namespace App\Enums;

enum ProjectProposalStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Rejected => 'Rejected',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    public function canSubmit(): bool
    {
        return $this->isEditable();
    }

    public function canReview(): bool
    {
        return $this === self::Pending;
    }

    public function canReopen(): bool
    {
        return in_array($this, [self::Confirmed, self::Rejected], true);
    }
}
