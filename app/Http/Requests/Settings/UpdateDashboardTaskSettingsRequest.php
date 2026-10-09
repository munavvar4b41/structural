<?php

namespace App\Http\Requests\Settings;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\TaskPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDashboardTaskSettingsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'statuses' => ['sometimes', 'array'],
            'statuses.*' => ['string', Rule::enum(ProjectTaskStatus::class)],
            'priorities' => ['sometimes', 'array'],
            'priorities.*' => ['string'],
            'project_ids' => ['sometimes', 'array'],
            'project_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array{enabled: bool, statuses: list<string>, priorities: list<string>, project_ids: list<int>}
     */
    public function preferences(): array
    {
        $validated = $this->validated();
        $user = $this->user();

        $requestedProjectIds = array_map(
            static fn (mixed $id): int => (int) $id,
            $validated['project_ids'] ?? [],
        );

        $visibleProjectIds = $requestedProjectIds === []
            ? []
            : Project::query()
                ->visibleToUser($user)
                ->whereIn('id', $requestedProjectIds)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

        $statuses = array_values(array_unique(array_map(
            static fn (mixed $status): string => (string) $status,
            $validated['statuses'] ?? [],
        )));

        return [
            'enabled' => $this->boolean('enabled'),
            'statuses' => $statuses,
            'priorities' => TaskPriority::filterValues(TaskPriority::parseFilter($validated['priorities'] ?? [])),
            'project_ids' => array_values(array_unique($visibleProjectIds)),
        ];
    }
}
