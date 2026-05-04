<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectRequirement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MarkProjectRequirementReviewedRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProjectRequirement|null $requirement */
        $requirement = $this->route('requirement');

        if (! $requirement instanceof ProjectRequirement) {
            return false;
        }

        return $this->user()?->can('markReviewed', $requirement) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reviewed_at') && $this->input('reviewed_at') === '') {
            $this->merge(['reviewed_at' => null]);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'reviewed_at' => ['nullable', 'date'],
        ];
    }
}
