<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class CareersSettings extends Settings
{
    /**
     * Email addresses that receive a copy of every new job application.
     *
     * @var list<string>
     */
    public array $notification_emails;

    public static function group(): string
    {
        return 'careers';
    }
}
