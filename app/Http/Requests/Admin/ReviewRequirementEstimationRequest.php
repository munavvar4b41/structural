<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectRequirementEstimation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewRequirementEstimationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $estimation = $this->resolveEstimation();

        if ($estimation === null) {
            return false;
        }

        $ability = (string) $this->route()->getActionMethod();

        return match ($ability) {
            'approve' => $this->user()?->can('approve', $estimation) ?? false,
            'reject' => $this->user()?->can('reject', $estimation) ?? false,
            'requestChanges' => $this->user()?->can('requestChanges', $estimation) ?? false,
            default => false,
        };
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function resolveEstimation(): ?ProjectRequirementEstimation
    {
        $estimation = $this->route('estimation');

        return $estimation instanceof ProjectRequirementEstimation ? $estimation : null;
    }
}
