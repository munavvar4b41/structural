<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('company.name', 'Structural');
        $this->migrator->add('company.legal_name', null);
        $this->migrator->add('company.phone', null);
        $this->migrator->add('company.website', null);
        $this->migrator->add('company.address_line1', null);
        $this->migrator->add('company.address_line2', null);
        $this->migrator->add('company.city', null);
        $this->migrator->add('company.region', null);
        $this->migrator->add('company.postal_code', null);
        $this->migrator->add('company.country', null);
        $this->migrator->add('company.email_domain', 'example.com');
    }
};
