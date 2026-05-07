<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectTaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyWorkController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $tasks = ProjectTask::query()
            ->where('assignee_user_id', $actor->id)
            ->whereIn('project_id', Project::query()->visibleToUser($actor)->select('projects.id'))
            ->with(['project:id,name,code', 'requirement:id,title'])
            ->orderByDesc('updated_at')
            ->get();

        $grouped = [];
        foreach (ProjectTaskStatus::boardOrder() as $status) {
            $grouped[$status->value] = [];
        }

        foreach ($tasks as $task) {
            $grouped[$task->status->value][] = $this->taskCard($task);
        }

        $columns = [];
        foreach (ProjectTaskStatus::boardOrder() as $status) {
            $columns[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'tasks' => $grouped[$status->value] ?? [],
            ];
        }

        return Inertia::render('admin/my-work/Index', [
            'columns' => $columns,
            'status_options' => collect(ProjectTaskStatus::cases())
                ->map(static fn (ProjectTaskStatus $s): array => [
                    'value' => $s->value,
                    'label' => $s->label(),
                ])
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function taskCard(ProjectTask $task): array
    {
        $project = $task->project;

        return [
            'id' => $task->id,
            'project_id' => $project->id,
            'title' => $task->title,
            'status' => $task->status->value,
            'estimated_minutes' => $task->estimated_minutes,
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
            ],
            'requirement' => $task->requirement === null ? null : [
                'id' => $task->requirement->id,
                'title' => $task->requirement->title,
            ],
            'project_tasks_url' => route('admin.projects.tasks.index', $project),
            'task_show_url' => route('admin.projects.tasks.show', [$project, $task]),
        ];
    }
}
