<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectTaskStatus;
use App\Enums\UserRole;
use App\Models\ProjectTask;
use App\Models\User;
use App\Support\ProjectRequirementAssignableUsers;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectTaskRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['assignee_user_id', 'project_requirement_id', 'parent_project_task_id', 'estimated_minutes'] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }

        /** @var ProjectTask|null $task */
        $task = $this->route('task');
        if (
            $task instanceof ProjectTask
            && $task->project->estimation_required
            && ! $this->has('estimated_minutes')
        ) {
            $this->merge(['estimated_minutes' => $task->estimated_minutes]);
        }
    }

    public function authorize(): bool
    {
        /** @var ProjectTask|null $task */
        $task = $this->route('task');

        if (! $task instanceof ProjectTask) {
            return false;
        }

        return $this->user()?->can('update', $task) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var ProjectTask $task */
        $task = $this->route('task');
        $project = $task->project;

        $estimationRules = $project->estimation_required
            ? ['required', 'integer', 'min:1']
            : ['nullable', 'integer', 'min:1'];

        $assignableIds = ProjectRequirementAssignableUsers::responsibleUserIds($project);

        $staffAssigneeOnly = $this->staffAssigneeLimitedUpdate($task);

        if ($staffAssigneeOnly) {
            return [
                'title' => ['prohibited'],
                'description' => ['prohibited'],
                'project_requirement_id' => ['prohibited'],
                'parent_project_task_id' => ['prohibited'],
                'assignee_user_id' => ['prohibited'],
                'status' => ['sometimes', Rule::enum(ProjectTaskStatus::class)],
                'estimated_minutes' => $estimationRules,
            ];
        }

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:50000'],
            'status' => ['sometimes', 'required', Rule::enum(ProjectTaskStatus::class)],
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
                /** @var ProjectTask $task */
                $task = $this->route('task');

                if ($this->staffAssigneeLimitedUpdate($task)) {
                    return;
                }

                $parentId = $this->input('parent_project_task_id');
                if ($parentId === null || $parentId === '') {
                    return;
                }

                if ((int) $task->id === (int) $parentId) {
                    $validator->errors()->add('parent_project_task_id', __('A task cannot be its own parent.'));

                    return;
                }

                $parent = ProjectTask::query()->find((int) $parentId);
                if ($parent === null) {
                    return;
                }

                if ($this->parentChainContainsTask($parent, (int) $task->id)) {
                    $validator->errors()->add(
                        'parent_project_task_id',
                        __('A task cannot be moved under one of its subtasks.'),
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

    private function parentChainContainsTask(ProjectTask $start, int $taskId): bool
    {
        $visited = [];
        $cursor = $start;

        while ($cursor !== null) {
            $cursorId = (int) $cursor->id;
            if (isset($visited[$cursorId])) {
                return false;
            }

            if ($cursorId === $taskId) {
                return true;
            }

            $visited[$cursorId] = true;
            $cursor = $cursor->parent;
        }

        return false;
    }

    private function staffAssigneeLimitedUpdate(ProjectTask $task): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        if ($user->role !== UserRole::Staff) {
            return false;
        }

        if ($task->project->lead_user_id === $user->id) {
            return false;
        }

        if ($task->created_by_user_id === $user->id) {
            return false;
        }

        return $task->assignee_user_id === $user->id;
    }
}
