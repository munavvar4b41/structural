<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectRequirement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmProjectRequirementUnderstandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProjectRequirement|null $requirement */
        $requirement = $this->route('requirement');

        if (! $requirement instanceof ProjectRequirement) {
            return false;
        }

        return $this->user()?->can('confirmUnderstanding', $requirement) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [];
    }
}
