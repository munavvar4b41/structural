<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaskTimeEntryRequest;
use App\Http\Requests\Admin\UpdateTaskTimeEntryRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Support\TaskTimeTracker;
use App\Support\WorkTimeEntryWindowResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskTimeEntryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TaskTimeTracker $tracker,
        private readonly WorkTimeEntryWindowResolver $workTimeWindowResolver,
    ) {}

    public function store(StoreTaskTimeEntryRequest $request, Project $project, ProjectTask $task): RedirectResponse
    {
        abort_if($task->project_id !== $project->id, 404);

        [$start, $end, $notes] = $this->resolveManualRange($request->validated());

        $this->tracker->addManual($request->user(), $task, $start, $end, $notes);

        return back()->with('toast', __('Time entry added.'));
    }

    public function update(
        UpdateTaskTimeEntryRequest $request,
        Project $project,
        ProjectTask $task,
        TaskTimeEntry $timeEntry,
    ): RedirectResponse {
        $this->ensureEntryBelongs($project, $task, $timeEntry);

        [$start, $end, $notes] = $this->resolveManualRange($request->validated());

        $this->tracker->updateManual($timeEntry, $start, $end, $notes);

        return back()->with('toast', __('Time entry updated.'));
    }

    public function destroy(
        Request $request,
        Project $project,
        ProjectTask $task,
        TaskTimeEntry $timeEntry,
    ): RedirectResponse {
        $this->ensureEntryBelongs($project, $task, $timeEntry);
        $this->authorize('delete', $timeEntry);

        $timeEntry->delete();

        return back()->with('toast', __('Time entry deleted.'));
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
