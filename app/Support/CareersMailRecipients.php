<?php

namespace App\Support;

use App\Settings\CareersSettings;

final class CareersMailRecipients
{
    public function __construct(
        private CareersSettings $settings,
    ) {}

    /**
     * @return list<string>
     */
    public function forNewApplication(): array
    {
        return array_values(array_unique($this->settings->notification_emails ?? []));
    }
}
