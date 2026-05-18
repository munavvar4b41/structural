<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Support\MyWorkBoardBuilder;
use App\Support\ProjectTaskShowPayloadBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyWorkController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly MyWorkBoardBuilder $boardBuilder,
        private readonly ProjectTaskShowPayloadBuilder $showPayloadBuilder,
    ) {
        //
    }

    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        return Inertia::render('admin/my-work/Index', [
            ...$this->boardBuilder->build($actor, $request),
            'task_preview' => $this->resolveTaskPreview($request, $actor),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveTaskPreview(Request $request, User $actor): ?array
    {
        $taskId = (int) $request->query('task_id', 0);

        if ($taskId <= 0) {
            return null;
        }

        $task = ProjectTask::query()->findOrFail($taskId);
        $project = Project::query()->findOrFail($task->project_id);

        $this->authorize('view', $task);

        return $this->showPayloadBuilder->build($project, $task, $actor);
    }
}
