<?php

namespace App\Support;

use App\Enums\ProjectTaskStatus;
use App\Models\CaseStudy;
use App\Models\Project;
use App\Models\ProjectMetadata;
use App\Models\ProjectProposal;
use App\Models\ProjectRequirement;
use App\Models\ProjectTag;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\User;
use App\Settings\CompanySettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ProjectShowPayloadBuilder
{
    private const int LIST_LIMIT = 15;

    /**
     * @return array<string, mixed>
     */
    public function build(Project $project, User $actor): array
    {
        $project->loadMissing(['clientUser', 'leadUser', 'teams']);

        $reportAt = CarbonImmutable::now();

        $requirements = $project->requirements()
            ->with(['creator', 'responsibleUser', 'reviewer'])
            ->orderByDesc('created_at')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (ProjectRequirement $r): array => $this->requirementRow($r, $actor))
            ->all();

        $proposals = $project->proposals()
            ->with(['creator'])
            ->orderByDesc('created_at')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (ProjectProposal $proposal): array => $this->proposalRow($proposal))
            ->all();

        $canViewCaseStudies = $actor->can('viewAny', CaseStudy::class);

        $caseStudies = $canViewCaseStudies
            ? $project->caseStudies()
                ->with(['creator', 'task:id,title'])
                ->orderByDesc('created_at')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(fn (CaseStudy $caseStudy): array => $this->caseStudyRow($caseStudy))
                ->all()
            : [];

        $tasksCollection = $project->tasks()
            ->with(['assignee:id,name,email', 'requirement:id,title'])
            ->withCount('children')
            ->orderBy('title')
            ->limit(self::LIST_LIMIT)
            ->get();

        $tasks = collect(ProjectTaskDisplayOrder::depthFirstWithDepth($tasksCollection))
            ->map(fn (array $row): array => $this->taskRow($row['task'], $actor, $row['depth']))
            ->all();

        $timeEntries = $project->timeEntries()
            ->with(['task:id,title,project_id', 'user:id,name,email'])
            ->orderByDesc('started_at')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (TaskTimeEntry $entry): array => $this->timeEntryRow($entry, $actor, $reportAt))
            ->all();

        $assignableUsers = $this->assignableUserOptions($project);
        $requirementOptions = $project->requirements()
            ->orderBy('title')
            ->get(['id', 'title', 'max_generated_phase'])
            ->map(static fn (ProjectRequirement $r): array => [
                'value' => $r->id,
                'label' => $r->title,
                'max_generated_phase' => max(1, (int) ($r->max_generated_phase ?? RequirementPhaseRegistry::INITIAL_MAX_PHASE)),
            ])
            ->all();

        $taskOptions = $project->tasks()
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(static fn (ProjectTask $t): array => [
                'value' => $t->id,
                'label' => $t->title,
            ])
            ->all();

        $companySettings = app(CompanySettings::class);

        return [
            'project' => $this->projectDetail($project),
            'tags' => $project->tags()->orderBy('name')->get()->map(
                static fn (ProjectTag $tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ],
            )->all(),
            'metadata' => $project->metadata()->orderBy('key')->get()->map(
                static fn (ProjectMetadata $row): array => [
                    'id' => $row->id,
                    'key' => $row->key,
                    'value' => $row->value,
                ],
            )->all(),
            'requirements' => $requirements,
            'requirements_total' => $project->requirements()->count(),
            'proposals' => $proposals,
            'proposals_total' => $project->proposals()->count(),
            'can_create_proposals' => $actor->can('create', [ProjectProposal::class, $project]),
            'case_studies' => $caseStudies,
            'case_studies_total' => $canViewCaseStudies ? $project->caseStudies()->count() : 0,
            'can_create_case_studies' => $canViewCaseStudies
                && $actor->can('create', [CaseStudy::class, $project]),
            'can_view_case_studies' => $canViewCaseStudies,
            'tasks' => $tasks,
            'tasks_total' => $project->tasks()->count(),
            'time_entries' => $timeEntries,
            'time_entries_total' => $project->timeEntries()->count(),
            'can_create_requirements' => $actor->can('create', [ProjectRequirement::class, $project]),
            'can_create_tasks' => $actor->can('create', [ProjectTask::class, $project]),
            'can_manage_project' => $actor->can('update', $project),
            'can_manage_tags_metadata' => $actor->can('manageTags', $project),
            'can_create_time_entries' => $this->canCreateAnyTimeEntry($actor, $project),
            'assignable_responsibles' => $this->assignableResponsibleUsers($project)->map(
                static fn (User $u): array => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                ],
            )->all(),
            'assignable_users' => $assignableUsers,
            'requirement_options' => $requirementOptions,
            'task_options' => $taskOptions,
            'status_options' => $this->statusOptions(),
            'working_hours' => [
                'start' => $companySettings->work_day_start_time,
                'end' => $companySettings->work_day_end_time,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectDetail(Project $project): array
    {
        $client = $project->clientUser;
        $lead = $project->leadUser;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'code' => $project->code,
            'description' => $project->description,
            'estimation_required' => $project->estimation_required,
            'client_user' => $client === null ? null : [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
            ],
            'lead_user' => $lead === null ? null : [
                'id' => $lead->id,
                'name' => $lead->name,
                'email' => $lead->email,
            ],
            'teams' => $project->teams->map(static fn ($team): array => [
                'id' => $team->id,
                'name' => $team->name,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requirementRow(ProjectRequirement $r, User $actor): array
    {
        return [
            'id' => $r->id,
            'title' => $r->title,
            'description_preview' => TipTapDocument::previewFromStored($r->description),
            'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            'understanding_confirmed_at' => $r->understanding_confirmed_at?->toIso8601String(),
            'created_at' => $r->created_at?->toIso8601String(),
            'creator' => $this->userBrief($r->creator),
            'responsible_user' => $this->userBrief($r->responsibleUser),
            'reviewer' => $this->userBrief($r->reviewer),
            'can_update' => $actor->can('update', $r),
            'can_delete' => $actor->can('delete', $r),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalRow(ProjectProposal $proposal): array
    {
        return [
            'id' => $proposal->id,
            'title' => $proposal->title,
            'description_preview' => TipTapDocument::previewFromStored($proposal->description),
            'status' => $proposal->status->value,
            'status_label' => $proposal->status->label(),
            'created_at' => $proposal->created_at?->toIso8601String(),
            'creator' => $this->userBrief($proposal->creator),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function caseStudyRow(CaseStudy $caseStudy): array
    {
        return [
            'id' => $caseStudy->id,
            'title' => $caseStudy->title,
            'summary_preview' => ($overviewPreview = TipTapDocument::previewFromStored($caseStudy->overview)) !== null
                && $overviewPreview !== ''
                ? $overviewPreview
                : TipTapDocument::previewFromStored($caseStudy->client_issue),
            'created_at' => $caseStudy->created_at?->toIso8601String(),
            'creator' => $this->userBrief($caseStudy->creator),
            'task' => $caseStudy->task ? [
                'id' => $caseStudy->task->id,
                'title' => $caseStudy->task->title,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskRow(ProjectTask $task, User $actor, int $treeDepth = 0): array
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
            'estimated_minutes' => $task->estimated_minutes,
            'phase' => $task->phase,
            'phase_label' => $task->phase !== null
                ? app(RequirementPhaseRegistry::class)->phaseLabel((int) $task->phase)
                : null,
            'display_after_at' => $task->display_after_at?->toIso8601String(),
            'notify_at' => $task->notify_at?->toIso8601String(),
            'children_count' => $task->children_count,
            'tree_depth' => $treeDepth,
            'can_update' => $actor->can('update', $task),
            'can_delete' => $actor->can('delete', $task),
            'is_assignee_only_limited' => ProjectTaskAssigneeCapabilities::isAssigneeOnlyLimited($actor, $task),
            'can_submit_task_completion' => $this->canSubmitTaskCompletion($actor, $task),
            'can_confirm_task_completion' => $actor->can('confirmCompletion', $task),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function timeEntryRow(TaskTimeEntry $entry, User $actor, CarbonImmutable $reportAt): array
    {
        return [
            'id' => $entry->id,
            'project_task_id' => $entry->project_task_id,
            'task_title' => $entry->task?->title,
            'user' => $this->userBrief($entry->user),
            'started_at' => $entry->started_at?->toIso8601String(),
            'ended_at' => $entry->ended_at?->toIso8601String(),
            'duration_seconds' => $this->effectiveDurationSeconds($entry, $reportAt),
            'is_running' => $entry->isRunning(),
            'is_paused' => $entry->isPaused(),
            'source' => $entry->source->value,
            'notes' => $entry->notes,
            'can_update' => $actor->can('update', $entry),
            'can_delete' => $actor->can('delete', $entry),
        ];
    }

    private function effectiveDurationSeconds(TaskTimeEntry $entry, CarbonImmutable $at): int
    {
        if ($entry->ended_at !== null) {
            return max(0, (int) $entry->duration_seconds);
        }

        return $entry->elapsedSeconds($at);
    }

    private function canCreateAnyTimeEntry(User $actor, Project $project): bool
    {
        if ($actor->isClient()) {
            return false;
        }

        return $actor->can('view', $project) && $project->tasks()->exists();
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

    /**
     * @return Collection<int, User>
     */
    private function assignableResponsibleUsers(Project $project): Collection
    {
        $ids = ProjectRequirementAssignableUsers::responsibleUserIds($project);

        if ($ids === []) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->values();
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
