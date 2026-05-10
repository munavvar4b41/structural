<?php

namespace App\Enums;

enum LeaveType: string
{
    case FullDay = 'full_day';
    case HalfDay = 'half_day';
    case Break = 'break';

    public function label(): string
    {
        return match ($this) {
            self::FullDay => 'Full day',
            self::HalfDay => 'Half day',
            self::Break => 'Break (1 hour)',
        };
    }
}
