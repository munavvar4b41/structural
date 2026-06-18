<?php

namespace App\Http\Controllers\Api\Desktop;

use App\Enums\ProjectTaskStatus;
use App\Http\Controllers\Api\Desktop\Concerns\BuildsDesktopTaskShowResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmTaskCompletionRequest;
use App\Http\Requests\Admin\SubmitTaskCompletionRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskReview;
use App\Models\User;
use App\Support\ProjectTaskShowPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DesktopTaskCompletionController extends Controller
{
    use BuildsDesktopTaskShowResponse;

    public function __construct(private readonly ProjectTaskShowPayloadBuilder $showPayloadBuilder)
    {
        //
    }

    public function submit(
        SubmitTaskCompletionRequest $request,
        Project $project,
        ProjectTask $task,
    ): JsonResponse {
        $this->ensureTaskBelongsToProject($project, $task);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $task->forceFill([
            'status' => ProjectTaskStatus::Review,
            'completion_submitted_at' => now(),
            'completion_submitted_by_user_id' => $actor->id,
        ])->save();

        return $this->taskShowResponse($this->showPayloadBuilder, $project, $task, $actor);
    }

    public function confirm(
        ConfirmTaskCompletionRequest $request,
        Project $project,
        ProjectTask $task,
    ): JsonResponse {
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

        return $this->taskShowResponse($this->showPayloadBuilder, $project, $task, $reviewer);
    }
}
