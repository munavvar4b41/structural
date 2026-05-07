<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Support\ProjectRequirementAssignableUsers;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectTaskRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['assignee_user_id', 'project_requirement_id', 'parent_project_task_id', 'estimated_minutes'] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        if (! $project instanceof Project) {
            return false;
        }

        return $this->user()?->can('create', [ProjectTask::class, $project]) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');
        $assignableIds = ProjectRequirementAssignableUsers::responsibleUserIds($project);

        $estimationRules = $project->estimation_required
            ? ['required', 'integer', 'min:1']
            : ['nullable', 'integer', 'min:1'];

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:50000'],
            'status' => ['required', Rule::enum(ProjectTaskStatus::class)],
            'assignee_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereIn('id', $assignableIds ?: [0]),
            ],
            'project_requirement_id' => [
                'nullable',
                'integer',
                Rule::exists('project_requirements', 'id')->where('project_id', $project->id),
            ],
            'parent_project_task_id' => [
                'nullable',
                'integer',
                Rule::exists('project_tasks', 'id')->where('project_id', $project->id),
            ],
            'estimated_minutes' => $estimationRules,
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Project $project */
                $project = $this->route('project');

                $parentId = $this->input('parent_project_task_id');
                if ($parentId === null || $parentId === '') {
                    return;
                }

                $parent = ProjectTask::query()->find((int) $parentId);
                if ($parent === null) {
                    return;
                }

                if ($this->parentChainHasCycle($parent)) {
                    $validator->errors()->add(
                        'parent_project_task_id',
                        __('Selected parent task is invalid due to a circular hierarchy.'),
                    );

                    return;
                }

                $reqId = $this->input('project_requirement_id');
                $parentReqId = $parent->project_requirement_id;
                $normalizedReq = $reqId === null || $reqId === '' ? null : (int) $reqId;
                if ($parentReqId !== $normalizedReq) {
                    $validator->errors()->add(
                        'project_requirement_id',
                        __('Subtasks must use the same requirement link as their parent task.'),
                    );
                }
            },
        ];
    }

    private function parentChainHasCycle(ProjectTask $start): bool
    {
        $visited = [];
        $cursor = $start;

        while ($cursor !== null) {
            $cursorId = (int) $cursor->id;
            if (isset($visited[$cursorId])) {
                return true;
            }

            $visited[$cursorId] = true;
            $cursor = $cursor->parent;
        }

        return false;
    }
}
