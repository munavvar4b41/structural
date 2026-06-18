<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class UpdateCompanySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->canManageCompanySettings();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email_domain')) {
            $domain = Str::lower(Str::ltrim(trim((string) $this->input('email_domain')), '@'));

            $this->merge([
                'email_domain' => $domain,
            ]);
        }

        foreach (['work_day_start_time', 'work_day_end_time'] as $key) {
            if ($this->has($key)) {
                $this->merge([
                    $key => $this->normalizeTimeInput((string) $this->input($key)),
                ]);
            }
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $start = $this->input('work_day_start_time');
            $end = $this->input('work_day_end_time');

            if (! is_string($start) || ! is_string($end)) {
                return;
            }

            if ($this->timeToMinutes($start) >= $this->timeToMinutes($end)) {
                $validator->errors()->add(
                    'work_day_end_time',
                    __('The work day end time must be after the start time.'),
                );
            }
        });
    }

    private function normalizeTimeInput(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $matches) === 1) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return $value;
    }

    private function timeToMinutes(string $time): int
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches) !== 1) {
            return 0;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $domainRegex = '/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$/i';

        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'max:120'],
            'email_domain' => ['required', 'string', 'max:255', 'regex:'.$domainRegex],
            'work_day_start_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'work_day_end_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
        ];
    }
}
