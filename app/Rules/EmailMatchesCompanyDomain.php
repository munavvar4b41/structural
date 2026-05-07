<?php

namespace App\Rules;

use App\Settings\CompanySettings;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class EmailMatchesCompanyDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_contains($value, '@')) {
            $fail(__('The :attribute must be a valid email address.'));

            return;
        }

        $settings = app(CompanySettings::class);
        $allowed = Str::lower(trim($settings->email_domain));

        if ($allowed === '') {
            $fail(__('Self-service registration is not configured. Please contact your administrator.'));

            return;
        }

        $emailDomain = Str::lower(Str::after($value, '@'));

        if ($emailDomain !== $allowed) {
            $fail(__('You must register using an email address at :domain.', ['domain' => '@'.$allowed]));
        }
    }
}
