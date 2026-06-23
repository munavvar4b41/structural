<?php

namespace App\Enums;

enum WorkloadPeriod: string
{
    case PerDay = 'per_day';
    case PerWeek = 'per_week';
    case PerMonth = 'per_month';

    public function label(): string
    {
        return match ($this) {
            self::PerDay => 'Per day',
            self::PerWeek => 'Per week',
            self::PerMonth => 'Per month',
        };
    }
}
