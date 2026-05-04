<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectTaskStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmProjectRequirementUnderstandingRequest;
use App\Http\Requests\Admin\MarkProjectRequirementReviewedRequest;
use App\Http\Requests\Admin\StoreProjectRequirementRequest;
use App\Http\Requests\Admin\UpdateProjectRequirementRequest;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectRequirementMessage;
use App\Models\ProjectTask;
use App\Models\User;
use App\Support\ProjectRequirementAssignableUsers;
use App\Support\ProjectTaskDisplayOrder;
use App\Support\TipTapDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ProjectRequirementController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $requirements = $project->requirements()
            ->with(['creator', 'responsibleUser', 'reviewer'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->through(fn (ProjectRequirement $r): array => $this->requirementRow($r, $actor));

        return Inertia::render('admin/projects/requirements/Index', [
            'project' => $this->projectSummary($project),
            'requirements' => $requirements,
            'canCreateRequirements' => $actor->can('create', [ProjectRequirement::class, $project]),
            'canManageProject' => $actor->can('update', $project),
        ]);
    }

    public function show(Request $request, Project $project, ProjectRequirement $requirement): Response
    {
        $this->ensureRequirementBelongsToProject($project, $requirement);
        $this->authorize('view', $requirement);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $requirement->loadMissing(['creator', 'responsibleUser', 'reviewer', 'project', 'understandingConfirmedBy']);

        return Inertia::render('admin/projects/requirements/Show', [
            'project' => $this->projectSummary($project),
            'requirement' => $this->requirementDetailPayload($requirement),
            'requirement_chat_messages' => $this->requirementChatMessagesPayload($request, $requirement),
            'requirement_tasks' => $this->requirementTaskSummaries($requirement, $actor),
            'task_status_options' => $this->taskStatusOptions(),
            'task_assignable_users' => $this->taskAssignableUserOptions($project),
            'can_post_requirement_chat' => $actor->can('create', [ProjectRequirementMessage::class, $requirement]),
            'can_update' => $actor->can('update', $requirement),
            'can_mark_reviewed' => $actor->can('markReviewed', $requirement),
            'can_confirm_understanding' => $actor->can('confirmUnderstanding', $requirement),
            'can_manage_project' => $actor->can('update', $project),
            'can_create_tasks' => $actor->can('create', [ProjectTask::class, $project]),
        ]);
    }

    public function markReviewed(
        MarkProjectRequirementReviewedRequest $request,
        Project $project,
        ProjectRequirement $requirement,
    ): RedirectResponse {
        $this->ensureRequirementBelongsToProject($project, $requirement);

        $validated = $request->validated();

        $requirement->update([
            'review_understanding' => $validated['review_understanding'],
            'reviewed_at' => now(),
            'understanding_confirmed_at' => null,
            'understanding_confirmed_by_user_id' => null,
        ]);

        return to_route('admin.projects.requirements.show', [$project, $requirement]);
    }

    public function confirmUnderstanding(
        ConfirmProjectRequirementUnderstandingRequest $request,
        Project $project,
        ProjectRequirement $requirement,
    ): RedirectResponse {
        $this->ensureRequirementBelongsToProject($project, $requirement);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $requirement->update([
            'understanding_confirmed_at' => now(),
            'understanding_confirmed_by_user_id' => $actor->id,
        ]);

        return to_route('admin.projects.requirements.show', [$project, $requirement]);
    }

    public function create(Request $request, Project $project): Response
    {
        $this->authorize('create', [ProjectRequirement::class, $project]);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $project->loadMissing('teams');

        return Inertia::render('admin/projects/requirements/Create', [
            'project' => $this->projectSummary($project),
            'canManageProject' => $actor->can('update', $project),
            'assignable_responsibles' => $this->assignableResponsibleUsers($project)->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->all(),
        ]);
    }

    public function store(StoreProjectRequirementRequest $request, Project $project): RedirectResponse
    {
        $project->loadMissing('teams');

        $responsibleId = $request->validated('responsible_user_id');
        if ($responsibleId === null) {
            $responsibleId = $project->defaultResponsibleUser()?->id;
        }

        ProjectRequirement::query()->create([
            'project_id' => $project->id,
            'created_by_user_id' => $request->user()->id,
            'responsible_user_id' => $responsibleId,
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
        ]);

        return to_route('admin.projects.requirements.index', $project)->with('toast', 'Requirement created.');
    }

    public function edit(Request $request, Project $project, ProjectRequirement $requirement): Response
    {
        $this->ensureRequirementBelongsToProject($project, $requirement);
        $this->authorize('update', $requirement);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $requirement->loadMissing(['creator', 'responsibleUser', 'reviewer']);

        return Inertia::render('admin/projects/requirements/Edit', [
            'project' => $this->projectSummary($project),
            'requirement' => $this->requirementFormPayload($requirement),
            'assignable_staff' => $this->assignableStaff($project)->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->all(),
            'assignable_responsibles' => $this->assignableResponsibleUsers($project)->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->all(),
            'can_update_content' => $actor->can('updateContent', $requirement),
            'can_update_assignments' => $actor->can('updateAssignments', $requirement),
            'can_manage_project' => $actor->can('update', $project),
        ]);
    }

    public function update(UpdateProjectRequirementRequest $request, Project $project, ProjectRequirement $requirement): RedirectResponse
    {
        $this->ensureRequirementBelongsToProject($project, $requirement);

        $validated = $request->validated();

        $requirement->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'reviewer_user_id' => $validated['reviewer_user_id'] ?? null,
            'responsible_user_id' => $validated['responsible_user_id'] ?? null,
        ]);

        return to_route('admin.projects.requirements.index', $project)->with('toast', 'Requirement updated.');
    }

    public function destroy(Request $request, Project $project, ProjectRequirement $requirement): RedirectResponse
    {
        $this->ensureRequirementBelongsToProject($project, $requirement);
        $this->authorize('delete', $requirement);

        $requirement->delete();

        return to_route('admin.projects.requirements.index', $project)->with('toast', 'Requirement deleted.');
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
     * @return list<array<string, mixed>>
     */
    private function requirementTaskSummaries(ProjectRequirement $requirement, User $actor): array
    {
        $tasks = $requirement->tasks()
            ->with(['assignee:id,name,email'])
            ->withCount('children')
            ->get();

        $rows = [];
        foreach (ProjectTaskDisplayOrder::depthFirstWithDepth($tasks) as ['task' => $task, 'depth' => $depth]) {
            $rows[] = [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status->value,
                'status_label' => $task->status->label(),
                'assignee_user_id' => $task->assignee_user_id,
                'assignee' => $this->userBrief($task->assignee),
                'project_requirement_id' => $task->project_requirement_id,
                'requirement_title' => $requirement->title,
                'parent_project_task_id' => $task->parent_project_task_id,
                'estimated_minutes' => $task->estimated_minutes,
                'children_count' => $task->children_count,
                'tree_depth' => $depth,
                'can_update' => $actor->can('update', $task),
                'can_delete' => $actor->can('delete', $task),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function taskStatusOptions(): array
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
    private function taskAssignableUserOptions(Project $project): array
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
    private function requirementDetailPayload(ProjectRequirement $r): array
    {
        return [
            'id' => $r->id,
            'title' => $r->title,
            'description' => $r->description,
            'review_understanding' => $r->review_understanding,
            'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            'understanding_confirmed_at' => $r->understanding_confirmed_at?->toIso8601String(),
            'understanding_confirmed_by' => $this->userBrief($r->understandingConfirmedBy),
            'created_at' => $r->created_at?->toIso8601String(),
            'updated_at' => $r->updated_at?->toIso8601String(),
            'creator' => $this->userBrief($r->creator),
            'responsible_user' => $this->userBrief($r->responsibleUser),
            'reviewer' => $this->userBrief($r->reviewer),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requirementFormPayload(ProjectRequirement $r): array
    {
        return [
            'id' => $r->id,
            'title' => $r->title,
            'description' => $r->description,
            'reviewer_user_id' => $r->reviewer_user_id,
            'responsible_user_id' => $r->responsible_user_id,
            'creator' => $this->userBrief($r->creator),
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
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function requirementChatMessagesPayload(Request $request, ProjectRequirement $requirement): LengthAwarePaginator
    {
        $perPage = 50;
        $total = $requirement->messages()->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = (int) $request->query('chat_page', (string) $lastPage);
        $page = min(max(1, $page), $lastPage);

        $paginator = $requirement->messages()
            ->with(['user:id,name,email'])
            ->orderBy('created_at')
            ->paginate($perPage, ['*'], 'chat_page', $page);

        $paginator->getCollection()->transform(function (ProjectRequirementMessage $m): array {
            return [
                'id' => $m->id,
                'body' => $m->body,
                'created_at' => $m->created_at?->toIso8601String(),
                'user' => $this->userBrief($m->user),
            ];
        });

        return $paginator;
    }

    private function ensureRequirementBelongsToProject(Project $project, ProjectRequirement $requirement): void
    {
        abort_if($requirement->project_id !== $project->id, 404);
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function assignableStaff(Project $project): EloquentCollection
    {
        $teamIds = $project->teams()->pluck('teams.id');

        return User::query()
            ->where('role', UserRole::Staff)
            ->whereHas('teams', static function ($query) use ($teamIds): void {
                $query->whereIn('teams.id', $teamIds);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
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
}
