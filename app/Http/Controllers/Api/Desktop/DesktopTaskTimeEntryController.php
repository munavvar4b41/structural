<?php

namespace App\Http\Controllers\Api\Desktop;

use App\Http\Controllers\Api\Desktop\Concerns\BuildsDesktopTaskShowResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaskTimeEntryRequest;
use App\Http\Requests\Admin\UpdateTaskTimeEntryRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\User;
use App\Support\ProjectTaskShowPayloadBuilder;
use App\Support\TaskTimeTracker;
use App\Support\WorkTimeEntryWindowResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesktopTaskTimeEntryController extends Controller
{
    use AuthorizesRequests;
    use BuildsDesktopTaskShowResponse;

    public function __construct(
        private readonly ProjectTaskShowPayloadBuilder $showPayloadBuilder,
        private readonly TaskTimeTracker $tracker,
        private readonly WorkTimeEntryWindowResolver $workTimeWindowResolver,
    ) {}

    public function store(
        StoreTaskTimeEntryRequest $request,
        Project $project,
        ProjectTask $task,
    ): JsonResponse {
        abort_if($task->project_id !== $project->id, 404);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        [$start, $end, $notes] = $this->resolveManualRange($request->validated());

        $this->tracker->addManual($actor, $task, $start, $end, $notes);

        return $this->taskShowResponse($this->showPayloadBuilder, $project, $task, $actor);
    }

    public function update(
        UpdateTaskTimeEntryRequest $request,
        Project $project,
        ProjectTask $task,
        TaskTimeEntry $time_entry,
    ): JsonResponse {
        $this->ensureEntryBelongs($project, $task, $time_entry);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        [$start, $end, $notes] = $this->resolveManualRange($request->validated());

        $this->tracker->updateManual($time_entry, $start, $end, $notes);

        return $this->taskShowResponse($this->showPayloadBuilder, $project, $task, $actor);
    }

    public function destroy(
        Request $request,
        Project $project,
        ProjectTask $task,
        TaskTimeEntry $time_entry,
    ): JsonResponse {
        $this->ensureEntryBelongs($project, $task, $time_entry);
        $this->authorize('delete', $time_entry);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $time_entry->delete();

        return $this->taskShowResponse($this->showPayloadBuilder, $project, $task, $actor);
    }

    private function ensureEntryBelongs(Project $project, ProjectTask $task, TaskTimeEntry $entry): void
    {
        abort_if($task->project_id !== $project->id, 404);
        abort_if($entry->project_task_id !== $task->id, 404);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string|null}
     */
    private function resolveManualRange(array $data): array
    {
        if (array_key_exists('duration_minutes', $data)) {
            $window = $this->workTimeWindowResolver->resolve((int) $data['duration_minutes']);

            return [$window['start'], $window['end'], null];
        }

        return [
            CarbonImmutable::parse($data['started_at']),
            CarbonImmutable::parse($data['ended_at']),
            isset($data['notes']) ? (string) $data['notes'] : null,
        ];
    }
}
