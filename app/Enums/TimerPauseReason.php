<?php

namespace App\Enums;

enum TimerPauseReason: string
{
    case Manual = 'manual';
    case Inactivity = 'inactivity';
    case Switch = 'switch';
    case System = 'system';
}
