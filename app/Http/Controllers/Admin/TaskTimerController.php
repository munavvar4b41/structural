<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\User;
use App\Support\TaskTimeTracker;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskTimerController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly TaskTimeTracker $tracker)
    {
        //
    }

    public function start(Request $request, Project $project, ProjectTask $task): RedirectResponse
    {
        abort_if($task->project_id !== $project->id, 404);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $this->authorize('start', [TaskTimeEntry::class, $task]);

        $this->tracker->start($actor, $task);

        return back()->with('toast', __('Timer started.'));
    }

    public function stop(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $this->authorize('stop', TaskTimeEntry::class);

        $this->tracker->stop($actor);

        return back()->with('toast', __('Timer stopped.'));
    }

    public function pause(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $this->authorize('stop', TaskTimeEntry::class);

        if ($this->tracker->pause($actor) === null) {
            return back()->with('toast', __('No running timer to pause.'));
        }

        return back()->with('toast', __('Timer paused.'));
    }

    public function resume(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $this->authorize('stop', TaskTimeEntry::class);

        if ($this->tracker->resume($actor) === null) {
            return back()->with('toast', __('No paused timer to resume.'));
        }

        return back()->with('toast', __('Timer resumed.'));
    }
}
