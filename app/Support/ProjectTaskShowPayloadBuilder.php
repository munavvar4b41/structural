<?php

namespace App\Support;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\User;
use App\Settings\CompanySettings;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ProjectTaskShowPayloadBuilder
{
    /**
     * @return array{
     *     project: array<string, mixed>,
     *     task: array<string, mixed>,
     *     can_manage_project: bool,
     *     checklist: array<string, mixed>,
     *     time_tracking: array<string, mixed>
     * }
     */
    public function build(Project $project, ProjectTask $task, User $actor): array
    {
        $this->loadTaskRelations($task);

        $directChildren = ProjectTask::query()
            ->where('project_id', $project->id)
            ->where('parent_project_task_id', $task->id)
            ->orderBy('title')
            ->with(['assignee:id,name,email', 'requirement:id,title'])
            ->withCount('children')
            ->get();

        return [
            'project' => $this->projectSummary($project),
            'task' => $this->taskDetail($task, $actor, $directChildren),
            'can_manage_project' => $actor->can('update', $project),
            'checklist' => ProjectTaskChecklistProps::forTask($task, $actor),
            'time_tracking' => $this->timeTrackingProps($task, $actor),
        ];
    }

    public function loadTaskRelations(ProjectTask $task): void
    {
        $task->load([
            'assignee:id,name,email',
            'requirement:id,title',
            'parent:id,title',
            'completionSubmittedBy:id,name,email',
            'checklistItems' => fn ($q) => $q->orderBy('created_at'),
        ]);
        $task->loadCount('children');
    }

    /**
     * @return array<string, mixed>
     */
    private function timeTrackingProps(ProjectTask $task, User $actor): array
    {
        $entries = TaskTimeEntry::query()
            ->where('project_task_id', $task->id)
            ->with('user:id,name,email')
            ->orderByDesc('started_at')
            ->limit(50)
            ->get();

        $myTodayTotal = TaskTimeEntry::todayElapsedSecondsForUserOnTask(
            $actor->id,
            $task->id,
        );

        $myAllTimeTotal = TaskTimeEntry::elapsedSecondsForUserOnTask(
            $actor->id,
            $task->id,
        );

        $taskAllTimeTotal = (int) $entries
            ->whereNotNull('duration_seconds')
            ->sum('duration_seconds');

        $remainingSeconds = $task->estimated_minutes !== null
            ? max(0, $task->estimated_minutes * 60 - $myAllTimeTotal)
            : null;

        $companySettings = app(CompanySettings::class);

        return [
            'can_track' => $actor->can('start', [TaskTimeEntry::class, $task]),
            'working_hours' => [
                'start' => $companySettings->work_day_start_time,
                'end' => $companySettings->work_day_end_time,
            ],
            'totals' => [
                'my_today_seconds' => $myTodayTotal,
                'my_all_time_seconds' => $myAllTimeTotal,
                'task_all_time_seconds' => $taskAllTimeTotal,
                'remaining_seconds' => $remainingSeconds,
            ],
            'entries' => $entries->map(fn (TaskTimeEntry $e): array => [
                'id' => $e->id,
                'user_id' => $e->user_id,
                'user_name' => $e->user?->name,
                'started_at' => $e->started_at?->toIso8601String(),
                'ended_at' => $e->ended_at?->toIso8601String(),
                'duration_seconds' => $e->duration_seconds,
                'is_running' => $e->isOpen(),
                'is_paused' => $e->isPaused(),
                'elapsed_seconds' => $e->isOpen() ? $e->elapsedSeconds() : null,
                'source' => $e->source->value,
                'source_label' => $e->source->label(),
                'notes' => $e->notes,
                'can_update' => $actor->can('update', $e),
                'can_delete' => $actor->can('delete', $e),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectSummary(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'code' => $project->code,
            'estimation_required' => $project->estimation_required,
        ];
    }

    /**
     * @return array{id: int, name: string, email: string}|null
     */
    private function userBrief(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskDetail(ProjectTask $task, User $actor, EloquentCollection $directChildren): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status->value,
            'status_label' => $task->status->label(),
            'assignee_user_id' => $task->assignee_user_id,
            'assignee' => $this->userBrief($task->assignee),
            'project_requirement_id' => $task->project_requirement_id,
            'requirement_title' => $task->requirement?->title,
            'parent_project_task_id' => $task->parent_project_task_id,
            'parent' => $task->parent === null ? null : [
                'id' => $task->parent->id,
                'title' => $task->parent->title,
            ],
            'estimated_minutes' => $task->estimated_minutes,
            'phase' => $task->phase,
            'phase_label' => $task->phase !== null
                ? app(RequirementPhaseRegistry::class)->phaseLabel((int) $task->phase)
                : null,
            'children_count' => $task->children_count,
            'subtasks' => $directChildren
                ->map(fn (ProjectTask $child): array => [
                    'id' => $child->id,
                    'title' => $child->title,
                    'status' => $child->status->value,
                    'status_label' => $child->status->label(),
                    'assignee_user_id' => $child->assignee_user_id,
                    'assignee' => $this->userBrief($child->assignee),
                    'project_requirement_id' => $child->project_requirement_id,
                    'requirement_title' => $child->requirement?->title,
                    'estimated_minutes' => $child->estimated_minutes,
                    'children_count' => $child->children_count,
                    'tree_depth' => 1,
                    'can_update' => $actor->can('update', $child),
                    'can_delete' => $actor->can('delete', $child),
                    'is_assignee_only_limited' => ProjectTaskAssigneeCapabilities::isAssigneeOnlyLimited($actor, $child),
                    'can_submit_task_completion' => $this->canSubmitTaskCompletion($actor, $child),
                    'can_confirm_task_completion' => $actor->can('confirmCompletion', $child),
                ])
                ->values()
                ->all(),
            'can_update' => $actor->can('update', $task),
            'can_delete' => $actor->can('delete', $task),
            'completion_submitted_at' => $task->completion_submitted_at?->toIso8601String(),
            'completion_submitted_by' => $this->userBrief($task->completionSubmittedBy),
            'is_assignee_only_limited' => ProjectTaskAssigneeCapabilities::isAssigneeOnlyLimited($actor, $task),
            'can_submit_task_completion' => $this->canSubmitTaskCompletion($actor, $task),
            'can_confirm_task_completion' => $actor->can('confirmCompletion', $task),
        ];
    }

    private function canSubmitTaskCompletion(User $actor, ProjectTask $task): bool
    {
        if (! $actor->can('submitCompletion', $task)) {
            return false;
        }

        return ! in_array($task->status, [
            ProjectTaskStatus::Review,
            ProjectTaskStatus::Done,
            ProjectTaskStatus::Cancelled,
        ], true);
    }
}
