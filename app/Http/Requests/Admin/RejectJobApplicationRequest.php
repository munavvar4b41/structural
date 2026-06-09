<?php

namespace App\Http\Requests\Admin;

use App\Models\JobApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectJobApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var JobApplication|null $jobApplication */
        $jobApplication = $this->route('jobApplication');

        if (! $jobApplication instanceof JobApplication) {
            return false;
        }

        return $this->user()?->can('reject', $jobApplication) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
