<?php

namespace App\Http\Controllers\Api\Desktop\Concerns;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Support\ProjectTaskShowPayloadBuilder;
use Illuminate\Http\JsonResponse;

trait BuildsDesktopTaskShowResponse
{
    protected function taskShowResponse(
        ProjectTaskShowPayloadBuilder $showPayloadBuilder,
        Project $project,
        ProjectTask $task,
        User $actor,
    ): JsonResponse {
        $task->refresh();

        return response()->json($showPayloadBuilder->build($project, $task, $actor));
    }

    protected function ensureTaskBelongsToProject(Project $project, ProjectTask $task): void
    {
        abort_if($task->project_id !== $project->id, 404);
    }
}
