<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectRequirement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequirementPhaseSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requirement = $this->route('requirement');

        return $requirement instanceof ProjectRequirement
            && ($this->user()?->can('update', $requirement) ?? false);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'max_generated_phase' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
