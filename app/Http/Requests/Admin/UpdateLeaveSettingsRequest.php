<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateLeaveSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->canManageCompanySettings();
    }

    protected function prepareForValidation(): void
    {
        $text = $this->input('notification_emails_text', '');
        $emails = collect(preg_split('/[\r\n,]+/', (string) $text))
            ->map(static fn (mixed $line): string => Str::lower(trim((string) $line)))
            ->filter(static fn (string $email): bool => $email !== '')
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'notification_emails' => $emails,
        ]);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'notification_emails' => ['present', 'array'],
            'notification_emails.*' => ['email'],
        ];
    }
}
