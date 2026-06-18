<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectTaskStatus;
use App\Models\ProjectTask;
use App\Models\User;
use App\Support\ProjectRequirementAssignableUsers;
use App\Support\ProjectTaskAssigneeCapabilities;
use App\Support\ValidatesLinkedTaskPhase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectTaskRequest extends FormRequest
{
    use ValidatesLinkedTaskPhase;

    protected function prepareForValidation(): void
    {
        foreach (['assignee_user_id', 'project_requirement_id', 'parent_project_task_id', 'estimated_minutes', 'display_after_at', 'notify_at', 'phase'] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }

        /** @var ProjectTask|null $task */
        $task = $this->route('task');
        if ($task instanceof ProjectTask
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

        $user = $this->user();
        $staffAssigneeOnly = $user instanceof User && ProjectTaskAssigneeCapabilities::isAssigneeOnlyLimited($user, $task);

        if ($staffAssigneeOnly) {
            return [
                'title' => ['prohibited'],
                'description' => ['prohibited'],
                'project_requirement_id' => ['prohibited'],
                'parent_project_task_id' => ['prohibited'],
                'assignee_user_id' => ['prohibited'],
                'status' => ['sometimes', Rule::enum(ProjectTaskStatus::class)],
                'estimated_minutes' => $estimationRules,
                'display_after_at' => ['prohibited'],
                'notify_at' => ['prohibited'],
                'phase' => ['prohibited'],
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
            'display_after_at' => ['nullable', 'date'],
            'notify_at' => ['nullable', 'date'],
            'phase' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if ($key !== null) {
            return $validated;
        }

        /** @var ProjectTask $task */
        $task = $this->route('task');
        $project = $task->project;

        if (! array_key_exists('project_requirement_id', $validated) && ! array_key_exists('phase', $validated)) {
            return $validated;
        }

        $requirementId = array_key_exists('project_requirement_id', $validated)
            ? $validated['project_requirement_id']
            : $task->project_requirement_id;

        $phase = array_key_exists('phase', $validated)
            ? $validated['phase']
            : ($requirementId !== null ? $task->phase : null);

        $validated['phase'] = $this->resolveValidatedTaskPhase($project, $requirementId, $phase);

        return $validated;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                /** @var ProjectTask $task */
                $task = $this->route('task');

                if (! $user instanceof User) {
                    return;
                }

                if (ProjectTaskAssigneeCapabilities::isAssigneeOnlyLimited($user, $task)) {
                    $status = $this->input('status');
                    if ($status !== null && $status !== '') {
                        $asEnum = ProjectTaskStatus::tryFrom((string) $status);
                        if ($asEnum === ProjectTaskStatus::Done) {
                            $validator->errors()->add(
                                'status',
                                __('Use “Submit for completion” so a reviewer can confirm this task.'),
                            );
                        }
                    }
                }
            },
            function (Validator $validator): void {
                $user = $this->user();
                /** @var ProjectTask $task */
                $task = $this->route('task');

                if (! $user instanceof User || ProjectTaskAssigneeCapabilities::isAssigneeOnlyLimited($user, $task)) {
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

                if ($this->input('notify_at', $task->notify_at) === null) {
                    return;
                }

                $assigneeId = $this->has('assignee_user_id')
                    ? $this->input('assignee_user_id')
                    : $task->assignee_user_id;

                $hasAssignee = $assigneeId !== null && $assigneeId !== '';
                $hasProjectLead = $task->project->lead_user_id !== null;

                if (! $hasAssignee && ! $hasProjectLead) {
                    $validator->errors()->add(
                        'notify_at',
                        __('A reminder needs at least one recipient. Assign a task owner or set a project lead.'),
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
}
