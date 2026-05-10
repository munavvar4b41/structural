<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class LeaveSettings extends Settings
{
    /**
     * Email addresses that receive a copy of every new leave request (in addition to team heads).
     *
     * @var list<string>
     */
    public array $notification_emails;

    public static function group(): string
    {
        return 'leave';
    }
}
