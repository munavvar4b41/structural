<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\User;
use App\Support\ProjectRequirementAssignableUsers;
use App\Support\TipTapDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        if (! $project instanceof Project) {
            return false;
        }

        return $this->user()?->can('create', [ProjectRequirement::class, $project]) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('responsible_user_id') && $this->input('responsible_user_id') === '') {
            $merge['responsible_user_id'] = null;
        }
        if ($this->has('reviewer_user_id') && $this->input('reviewer_user_id') === '') {
            $merge['reviewer_user_id'] = null;
        }
        $desc = $this->input('description');
        if (is_string($desc) && $desc !== '' && ! TipTapDocument::isValidDocumentJson($desc)) {
            $merge['description'] = (string) json_encode([
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => $desc],
                        ],
                    ],
                ],
            ]);
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string|\Closure>>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');
        $project->loadMissing('teams');

        $allowedResponsibleIds = ProjectRequirementAssignableUsers::responsibleUserIds($project);
        $allowedReviewerIds = ProjectRequirementAssignableUsers::reviewerUserIds($project);

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => [
                'nullable',
                'string',
                'max:'.ProjectRequirementAssignableUsers::DESCRIPTION_MAX_LENGTH,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! is_string($value) || ! TipTapDocument::isValidDocumentJson($value)) {
                        $fail(__('The description must be valid rich text.'));
                    }
                },
            ],
            'responsible_user_id' => ['nullable', 'integer', Rule::in($allowedResponsibleIds)],
            'reviewer_user_id' => ['nullable', 'integer', Rule::in($allowedReviewerIds)],
            'max_generated_phase' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var User|null $actor */
            $actor = $this->user();
            /** @var Project|null $project */
            $project = $this->route('project');

            if (! $actor instanceof User || ! $project instanceof Project) {
                return;
            }

            $reviewerInput = $this->input('reviewer_user_id');
            $reviewerNew = $reviewerInput === null || $reviewerInput === '' ? null : (int) $reviewerInput;

            if ($reviewerNew === null) {
                return;
            }

            if (! $actor->can('assignReviewerOnCreate', [ProjectRequirement::class, $project])) {
                $validator->errors()->add('reviewer_user_id', __('You may not assign a reviewer.'));
            }
        });
    }
}
