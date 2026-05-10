<?php

namespace App\Enums;

enum LeaveHalfDayPeriod: string
{
    case FirstHalf = 'first_half';
    case SecondHalf = 'second_half';

    public function label(): string
    {
        return match ($this) {
            self::FirstHalf => 'Morning (first half)',
            self::SecondHalf => 'Afternoon (second half)',
        };
    }
}
