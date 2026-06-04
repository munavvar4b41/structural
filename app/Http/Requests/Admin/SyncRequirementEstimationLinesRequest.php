<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectRequirementEstimation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncRequirementEstimationLinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $estimation = $this->resolveEstimation();

        return $estimation !== null
            && ($this->user()?->can('syncLines', $estimation) ?? false);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.client_key' => ['nullable', 'string', 'max:64'],
            'lines.*.parent_id' => ['nullable', 'integer'],
            'lines.*.parent_client_key' => ['nullable', 'string', 'max:64'],
            'lines.*.title' => ['required', 'string', 'max:255'],
            'lines.*.description' => ['nullable', 'string', 'max:50000'],
            'lines.*.estimated_minutes' => ['nullable', 'integer', 'min:1'],
            'lines.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function resolveEstimation(): ?ProjectRequirementEstimation
    {
        $estimation = $this->route('estimation');

        return $estimation instanceof ProjectRequirementEstimation ? $estimation : null;
    }
}
