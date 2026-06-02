<?php

namespace App\Enums;

enum TimerResumedBy: string
{
    case Manual = 'manual';
    case Inactivity = 'inactivity';
    case System = 'system';
}
