<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use App\Models\ProjectRequirementEstimation;
use App\Support\RequirementEstimationAssignableApprovers;
use App\Support\RequirementEstimationMinutesRollup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubmitRequirementEstimationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $estimation = $this->resolveEstimation();

        return $estimation !== null
            && ($this->user()?->can('submit', $estimation) ?? false);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');
        $approverIds = RequirementEstimationAssignableApprovers::approverUserIds($project);

        return [
            'submitted_to_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereIn('id', $approverIds ?: [0]),
            ],
            'submission_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $estimation = $this->resolveEstimation();
                if ($estimation === null) {
                    return;
                }

                $items = $estimation->items()->get();
                $rollup = RequirementEstimationMinutesRollup::forItems($items);
                $rollup->persistRollups();

                if ($rollup->leafMissingMinutes()) {
                    $validator->errors()->add(
                        'lines',
                        __('Every estimation line without subtasks must have estimated minutes before submission.'),
                    );
                }
            },
        ];
    }

    private function resolveEstimation(): ?ProjectRequirementEstimation
    {
        $estimation = $this->route('estimation');

        return $estimation instanceof ProjectRequirementEstimation ? $estimation : null;
    }
}
