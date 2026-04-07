<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class CompanySettings extends Settings
{
    public string $name;

    public ?string $legal_name;

    public ?string $phone;

    public ?string $website;

    public ?string $address_line1;

    public ?string $address_line2;

    public ?string $city;

    public ?string $region;

    public ?string $postal_code;

    public ?string $country;

    public string $email_domain;

    public static function group(): string
    {
        return 'company';
    }
}
