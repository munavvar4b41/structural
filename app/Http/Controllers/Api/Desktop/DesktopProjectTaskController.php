<?php

namespace App\Http\Controllers\Api\Desktop;

use App\Http\Controllers\Api\Desktop\Concerns\BuildsDesktopTaskShowResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectTaskRequest;
use App\Http\Requests\Admin\UpdateProjectTaskRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Support\AssignmentNotificationDispatcher;
use App\Support\ProjectTaskFormOptionsBuilder;
use App\Support\ProjectTaskHierarchy;
use App\Support\ProjectTaskShowPayloadBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DesktopProjectTaskController extends Controller
{
    use AuthorizesRequests;
    use BuildsDesktopTaskShowResponse;

    public function __construct(
        private readonly ProjectTaskShowPayloadBuilder $showPayloadBuilder,
        private readonly ProjectTaskFormOptionsBuilder $formOptionsBuilder,
        private readonly AssignmentNotificationDispatcher $assignmentNotificationDispatcher,
        private readonly ProjectTaskHierarchy $taskHierarchy,
    ) {}

    public function show(Request $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('view', $task);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        return $this->taskShowResponse($this->showPayloadBuilder, $project, $task, $actor);
    }

    public function formOptions(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $excludeTaskId = (int) $request->query('exclude_task_id', 0);
        $excludeTask = $excludeTaskId > 0
            ? ProjectTask::query()->where('project_id', $project->id)->find($excludeTaskId)
            : null;

        return response()->json($this->formOptionsBuilder->build($project, $actor, $excludeTask));
    }

    public function store(StoreProjectTaskRequest $request, Project $project): JsonResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $data = $request->validated();
        $data['project_id'] = $project->id;
        $data['created_by_user_id'] = $actor->id;

        $task = ProjectTask::query()->create($data);

        if ($task->assignee_user_id !== null) {
            $this->assignmentNotificationDispatcher->sendTaskAssigned($task, $actor);
        }

        return $this->taskShowResponse($this->showPayloadBuilder, $project, $task, $actor);
    }

    public function update(UpdateProjectTaskRequest $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $originalAssigneeId = $task->assignee_user_id;
        $originalStatus = $task->status;
        $payload = $request->validated();

        if (array_key_exists('notify_at', $payload)) {
            $existingNotifyAt = $task->notify_at;
            $incomingNotifyAt = $payload['notify_at'] === null ? null : Carbon::parse($payload['notify_at']);

            if (($existingNotifyAt?->toIso8601String()) !== ($incomingNotifyAt?->toIso8601String())) {
                $payload['notified_at'] = null;
            }
        }

        $task->update($payload);

        if ($task->wasChanged('status')
            && $task->status !== $originalStatus
            && $this->taskHierarchy->hasDirectChildren($task)) {
            $this->taskHierarchy->cascadeStatus($task, $task->status);
        }

        $assigneeChanged = $task->wasChanged('assignee_user_id') && $originalAssigneeId !== $task->assignee_user_id;

        if ($assigneeChanged && $task->assignee_user_id !== null) {
            $this->assignmentNotificationDispatcher->sendTaskAssigned($task, $actor);
        } elseif ($task->wasChanged()) {
            $this->assignmentNotificationDispatcher->sendTaskUpdated($task, $actor);
        }

        return $this->taskShowResponse($this->showPayloadBuilder, $project, $task, $actor);
    }

    public function destroy(Request $request, Project $project, ProjectTask $task): JsonResponse
    {
        $this->ensureTaskBelongsToProject($project, $task);
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(['deleted' => true]);
    }
}
