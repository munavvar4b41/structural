<?php

namespace App\Http\Controllers\Api\Desktop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Desktop\DesktopTimerStartRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\User;
use App\Support\DesktopTraySnapshot;
use App\Support\TaskTimeTracker;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesktopTimerController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TaskTimeTracker $tracker,
        private readonly DesktopTraySnapshot $snapshot,
    ) {
        //
    }

    public function start(DesktopTimerStartRequest $request): JsonResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $project = Project::query()->findOrFail($request->integer('project_id'));
        $task = ProjectTask::query()->findOrFail($request->integer('task_id'));
        abort_if($task->project_id !== $project->id, 404);

        $this->authorize('start', [TaskTimeEntry::class, $task]);

        $this->tracker->start($actor, $task);

        return $this->trayResponse($actor);
    }

    public function stop(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $this->authorize('stop', TaskTimeEntry::class);

        $this->tracker->stop($actor);

        return $this->trayResponse($actor);
    }

    public function pause(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $this->authorize('stop', TaskTimeEntry::class);

        $this->tracker->pause($actor);

        return $this->trayResponse($actor);
    }

    public function resume(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $this->authorize('stop', TaskTimeEntry::class);

        $this->tracker->resume($actor);

        return $this->trayResponse($actor);
    }

    private function trayResponse(User $actor): JsonResponse
    {
        return response()->json($this->snapshot->build($actor));
    }
}
