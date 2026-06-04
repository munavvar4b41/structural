<?php

namespace App\Support;

use App\Enums\ProjectTaskStatus;
use App\Enums\RequirementEstimationStatus;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectRequirementEstimation;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TransferEstimationToTasks
{
    public function transfer(
        ProjectRequirementEstimation $estimation,
        Project $project,
        ProjectRequirement $requirement,
        User $actor,
    ): void {
        DB::transaction(function () use ($estimation, $project, $requirement, $actor): void {
            $estimation->load('items');
            $ordered = RequirementEstimationDisplayOrder::depthFirstWithDepth($estimation->items);
            $taskIdByItemId = [];

            foreach ($ordered as ['item' => $item]) {
                $parentTaskId = $item->parent_estimation_item_id !== null
                    ? ($taskIdByItemId[$item->parent_estimation_item_id] ?? null)
                    : null;

                $task = ProjectTask::query()->create([
                    'project_id' => $project->id,
                    'project_requirement_id' => $requirement->id,
                    'project_requirement_estimation_item_id' => $item->id,
                    'parent_project_task_id' => $parentTaskId,
                    'title' => $item->title,
                    'description' => $item->description,
                    'status' => ProjectTaskStatus::Backlog,
                    'assignee_user_id' => null,
                    'created_by_user_id' => $actor->id,
                    'estimated_minutes' => $item->estimated_minutes,
                ]);

                $taskIdByItemId[$item->id] = $task->id;

                $item->forceFill(['transferred_project_task_id' => $task->id])->save();
            }

            $estimation->forceFill([
                'status' => RequirementEstimationStatus::Transferred,
                'transferred_at' => now(),
                'transferred_by_user_id' => $actor->id,
            ])->save();
        });
    }
}
