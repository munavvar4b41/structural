<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\ProjectRequirement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProjectRequirement|null $requirement */
        $requirement = $this->route('requirement');

        if (! $requirement instanceof ProjectRequirement) {
            return false;
        }

        return $this->user()?->can('update', $requirement) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('reviewer_user_id') && $this->input('reviewer_user_id') === '') {
            $merge['reviewer_user_id'] = null;
        }
        if ($this->has('responsible_user_id') && $this->input('responsible_user_id') === '') {
            $merge['responsible_user_id'] = null;
        }
        if ($this->has('reviewed_at') && $this->input('reviewed_at') === '') {
            $merge['reviewed_at'] = null;
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var ProjectRequirement $requirement */
        $requirement = $this->route('requirement');
        $project = $requirement->project;
        $teamIds = $project->teams()->pluck('teams.id');

        $allowedReviewerIds = User::query()
            ->where('role', UserRole::Staff)
            ->whereHas('teams', static function ($query) use ($teamIds): void {
                $query->whereIn('teams.id', $teamIds);
            })
            ->pluck('id')
            ->all();

        $allowedResponsibleIds = User::query()
            ->whereIn('role', [UserRole::SuperAdmin, UserRole::Admin])
            ->pluck('id')
            ->merge(
                User::query()
                    ->where('role', UserRole::TeamHead)
                    ->whereHas('teams', static function ($query) use ($teamIds): void {
                        $query->whereIn('teams.id', $teamIds);
                    })
                    ->pluck('id')
            )
            ->unique()
            ->values()
            ->all();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'reviewer_user_id' => ['nullable', 'integer', Rule::in($allowedReviewerIds)],
            'responsible_user_id' => ['nullable', 'integer', Rule::in($allowedResponsibleIds)],
            'reviewed_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var User|null $actor */
            $actor = $this->user();
            /** @var ProjectRequirement|null $requirement */
            $requirement = $this->route('requirement');

            if (! $actor instanceof User || ! $requirement instanceof ProjectRequirement) {
                return;
            }

            $title = (string) $this->input('title');
            $description = $this->input('description');
            $descriptionNorm = $description === null || $description === '' ? null : (string) $description;
            $existingDesc = $requirement->description;

            if ($title !== $requirement->title || $descriptionNorm !== $existingDesc) {
                if (! $actor->can('updateContent', $requirement)) {
                    $validator->errors()->add('title', __('You may not edit this requirement\'s content.'));
                }
            }

            $reviewerInput = $this->input('reviewer_user_id');
            $reviewerNew = $reviewerInput === null || $reviewerInput === '' ? null : (int) $reviewerInput;
            if ($reviewerNew !== $requirement->reviewer_user_id) {
                if (! $actor->can('updateAssignments', $requirement)) {
                    $validator->errors()->add('reviewer_user_id', __('You may not change the reviewer.'));
                }
            }

            $responsibleInput = $this->input('responsible_user_id');
            $responsibleNew = $responsibleInput === null || $responsibleInput === '' ? null : (int) $responsibleInput;
            if ($responsibleNew !== $requirement->responsible_user_id) {
                if (! $actor->can('updateAssignments', $requirement)) {
                    $validator->errors()->add('responsible_user_id', __('You may not change the responsible person.'));
                }
            }

            $reviewedInput = $this->input('reviewed_at');
            $existingReviewed = $requirement->reviewed_at;
            $newReviewed = null;
            if ($reviewedInput !== null && $reviewedInput !== '') {
                try {
                    $newReviewed = Carbon::parse((string) $reviewedInput);
                } catch (\Throwable) {
                    return;
                }
            }

            $reviewedChanged = ($newReviewed === null && $existingReviewed !== null)
                || ($newReviewed !== null && ($existingReviewed === null || ! $newReviewed->equalTo($existingReviewed)));

            if ($reviewedChanged && ! $actor->can('markReviewed', $requirement)) {
                $validator->errors()->add('reviewed_at', __('You may not change the review timestamp.'));
            }
        });
    }
}
