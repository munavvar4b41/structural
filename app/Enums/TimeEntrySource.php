<?php

namespace App\Enums;

enum TimeEntrySource: string
{
    case Timer = 'timer';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Timer => __('Timer'),
            self::Manual => __('Manual'),
        };
    }
}
