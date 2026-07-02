<?php

namespace App\Support;

use App\Enums\ProjectTaskStatus;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectTask;
use App\Models\User;

class ProjectTaskFormOptionsBuilder
{
    public function __construct(private readonly RequirementPhaseRegistry $requirementPhaseRegistry)
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Project $project, User $actor, ?ProjectTask $excludeFromParentOptions = null): array
    {
        $tasksCollection = $project->tasks()
            ->get(['id', 'title', 'parent_project_task_id', 'phase', 'sort_order']);

        $parentTasks = collect(ProjectTaskDisplayOrder::depthFirstWithDepth($tasksCollection))
            ->reject(function (array $row) use ($excludeFromParentOptions): bool {
                if ($excludeFromParentOptions === null) {
                    return false;
                }

                return $row['task']->id === $excludeFromParentOptions->id;
            })
            ->map(static fn (array $row): array => [
                'value' => $row['task']->id,
                'label' => $row['task']->title,
                'tree_depth' => $row['depth'],
            ])
            ->values()
            ->all();

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'estimation_required' => $project->estimation_required,
            ],
            'status_options' => $this->statusOptions(),
            'assignable_users' => $this->assignableUserOptions($project),
            'requirements' => $project->requirements()
                ->orderBy('title')
                ->get(['id', 'title', 'max_generated_phase'])
                ->map(static fn (ProjectRequirement $r): array => [
                    'value' => $r->id,
                    'label' => $r->title,
                    'max_generated_phase' => max(1, (int) ($r->max_generated_phase ?? RequirementPhaseRegistry::INITIAL_MAX_PHASE)),
                ])
                ->all(),
            'parent_tasks' => $parentTasks,
            'can_create_tasks' => $actor->can('create', [ProjectTask::class, $project]),
            'can_manage_project' => $actor->can('update', $project),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return collect(ProjectTaskStatus::cases())
            ->map(static fn (ProjectTaskStatus $s): array => [
                'value' => $s->value,
                'label' => $s->label(),
            ])
            ->all();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function assignableUserOptions(Project $project): array
    {
        $ids = ProjectRequirementAssignableUsers::responsibleUserIds($project);
        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(static fn (User $u): array => [
                'value' => $u->id,
                'label' => $u->name.' ('.$u->email.')',
            ])
            ->all();
    }
}
