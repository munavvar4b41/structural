<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectTaskStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmTaskCompletionRequest;
use App\Http\Requests\Admin\SubmitTaskCompletionRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskReview;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TaskCompletionReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);
        abort_unless($actor->can_review_task_completions, 403);

        $projectIds = $this->reviewableProjectIds($actor);
        if ($projectIds === []) {
            return Inertia::render('admin/task-reviews/Index', [
                'tasks' => [],
            ]);
        }

        $tasks = ProjectTask::query()
            ->where('status', ProjectTaskStatus::Review)
            ->whereIn('project_id', $projectIds)
            ->with([
                'project:id,name,code',
                'assignee:id,name,email',
                'creator:id,name,email',
                'completionSubmittedBy:id,name,email',
            ])
            ->orderByDesc('completion_submitted_at')
            ->orderBy('phase')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->map(fn (ProjectTask $task): array => $this->queueRow($task))
            ->all();

        return Inertia::render('admin/task-reviews/Index', [
            'tasks' => $tasks,
        ]);
    }

    public function submit(SubmitTaskCompletionRequest $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);

        $task->forceFill([
            'status' => ProjectTaskStatus::Review,
            'completion_submitted_at' => now(),
            'completion_submitted_by_user_id' => $request->user()->id,
        ])->save();

        return back()->with('toast', __('Task submitted for review.'));
    }

    public function confirm(ConfirmTaskCompletionRequest $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);

        $data = $request->validated();
        $reviewer = $request->user();
        abort_if(! $reviewer instanceof User, 403);

        DB::transaction(function () use ($task, $data, $reviewer): void {
            ProjectTaskReview::query()->create([
                'project_task_id' => $task->id,
                'reviewer_user_id' => $reviewer->id,
                'review_notes' => $data['review_notes'] ?? null,
                'task_rating' => $data['task_rating'],
                'assignee_rating' => $data['assignee_rating'] ?? null,
                'creator_rating' => $data['creator_rating'],
            ]);

            $task->forceFill([
                'status' => ProjectTaskStatus::Done,
            ])->save();
        });

        return back()->with('toast', __('Task confirmed and marked done.'));
    }

    /**
     * @return list<int>
     */
    private function reviewableProjectIds(User $actor): array
    {
        if ($actor->isClient()) {
            return [];
        }

        if (in_array($actor->role, [UserRole::SuperAdmin, UserRole::Admin, UserRole::TeamHead], true)) {
            return Project::query()
                ->visibleToUser($actor)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        }

        return Project::query()
            ->where('lead_user_id', $actor->id)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function queueRow(ProjectTask $task): array
    {
        $project = $task->project;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'project_id' => $task->project_id,
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
            ],
            'assignee' => $task->assignee === null ? null : [
                'id' => $task->assignee->id,
                'name' => $task->assignee->name,
                'email' => $task->assignee->email,
            ],
            'creator' => $task->creator === null ? null : [
                'id' => $task->creator->id,
                'name' => $task->creator->name,
                'email' => $task->creator->email,
            ],
            'completion_submitted_at' => $task->completion_submitted_at?->toIso8601String(),
            'completion_submitted_by' => $task->completionSubmittedBy === null ? null : [
                'id' => $task->completionSubmittedBy->id,
                'name' => $task->completionSubmittedBy->name,
                'email' => $task->completionSubmittedBy->email,
            ],
            'review_stage' => 'awaiting_confirmation',
            'task_show_url' => route('admin.projects.tasks.show', [$project, $task]),
        ];
    }

    private function ensureTaskBelongsToProject(Project $project, ProjectTask $task): void
    {
        abort_if($task->project_id !== $project->id, 404);
    }
}
