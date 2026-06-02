<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('company.work_day_start_time', '09:00');
        $this->migrator->add('company.work_day_end_time', '17:00');
    }
};
