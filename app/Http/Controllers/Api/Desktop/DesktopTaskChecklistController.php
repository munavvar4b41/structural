<?php

namespace App\Http\Controllers\Api\Desktop;

use App\Http\Controllers\Api\Desktop\Concerns\BuildsDesktopTaskShowResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectTaskChecklistItemRequest;
use App\Http\Requests\Admin\UpdateProjectTaskChecklistItemRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskChecklistItem;
use App\Models\User;
use App\Support\ProjectTaskShowPayloadBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesktopTaskChecklistController extends Controller
{
    use AuthorizesRequests;
    use BuildsDesktopTaskShowResponse;

    public function __construct(private readonly ProjectTaskShowPayloadBuilder $showPayloadBuilder)
    {
        //
    }

    public function store(
        StoreProjectTaskChecklistItemRequest $request,
        Project $project,
        ProjectTask $task,
    ): JsonResponse {
        $this->ensureTaskBelongsToProject($project, $task);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $task->checklistItems()->create($request->validated());

        return $this->taskShowResponse($this->showPayloadBuilder, $project, $task, $actor);
    }

    public function update(
        UpdateProjectTaskChecklistItemRequest $request,
        Project $project,
        ProjectTask $task,
        ProjectTaskChecklistItem $checklist_item,
    ): JsonResponse {
        $this->ensureItemBelongs($project, $task, $checklist_item);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $checklist_item->update($request->validated());

        return $this->taskShowResponse($this->showPayloadBuilder, $project, $task, $actor);
    }

    public function destroy(
        Request $request,
        Project $project,
        ProjectTask $task,
        ProjectTaskChecklistItem $checklist_item,
    ): JsonResponse {
        $this->ensureItemBelongs($project, $task, $checklist_item);
        $this->authorize('update', $task);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $checklist_item->delete();

        return $this->taskShowResponse($this->showPayloadBuilder, $project, $task, $actor);
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
