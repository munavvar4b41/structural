<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectTaskChecklistItemRequest;
use App\Http\Requests\Admin\UpdateProjectTaskChecklistItemRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskChecklistItem;
use App\Models\User;
use App\Support\ProjectTaskChecklistProps;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectTaskChecklistItemController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Project $project, ProjectTask $task): Response|RedirectResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('view', $task);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        if (! $request->inertia()) {
            return redirect()->route('admin.projects.tasks.show', [
                'project' => $project,
                'task' => $task,
            ]);
        }

        $task->load(['checklistItems' => fn ($q) => $q->orderBy('created_at')]);

        return Inertia::render('admin/projects/tasks/Show', [
            'checklist' => ProjectTaskChecklistProps::forTask($task, $actor),
        ]);
    }

    public function store(
        StoreProjectTaskChecklistItemRequest $request,
        Project $project,
        ProjectTask $task,
    ): RedirectResponse {
        $this->ensureTaskBelongsToProject($project, $task);

        $task->checklistItems()->create($request->validated());

        return back()->with('toast', __('Checklist item added.'));
    }

    public function update(
        UpdateProjectTaskChecklistItemRequest $request,
        Project $project,
        ProjectTask $task,
        ProjectTaskChecklistItem $checklistItem,
    ): RedirectResponse {
        $this->ensureItemBelongs($project, $task, $checklistItem);

        $checklistItem->update($request->validated());

        return back()->with('toast', __('Checklist item updated.'));
    }

    public function destroy(
        Request $request,
        Project $project,
        ProjectTask $task,
        ProjectTaskChecklistItem $checklistItem,
    ): RedirectResponse {
        $this->ensureItemBelongs($project, $task, $checklistItem);
        $this->authorize('update', $task);

        $checklistItem->delete();

        return back()->with('toast', __('Checklist item deleted.'));
    }

    private function ensureTaskBelongsToProject(Project $project, ProjectTask $task): void
    {
        abort_if($task->project_id !== $project->id, 404);
    }

    private function ensureItemBelongs(
        Project $project,
        ProjectTask $task,
        ProjectTaskChecklistItem $item,
    ): void {
        $this->ensureTaskBelongsToProject($project, $task);
        abort_if($item->project_task_id !== $task->id, 404);
    }
}
