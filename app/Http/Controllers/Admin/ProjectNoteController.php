<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectNoteRequest;
use App\Http\Requests\Admin\UpdateProjectNoteRequest;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectNoteController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreProjectNoteRequest $request, Project $project): RedirectResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $project->notes()->create([
            ...$request->validated(),
            'created_by_user_id' => $actor->id,
        ]);

        return back()->with('toast', __('Note added.'));
    }

    public function update(UpdateProjectNoteRequest $request, Project $project, ProjectNote $note): RedirectResponse
    {
        abort_if($note->project_id !== $project->id, 404);

        $note->update($request->validated());

        return back()->with('toast', __('Note updated.'));
    }

    public function destroy(Request $request, Project $project, ProjectNote $note): RedirectResponse
    {
        abort_if($note->project_id !== $project->id, 404);
        $this->authorize('delete', $note);

        $note->delete();

        return back()->with('toast', __('Note removed.'));
    }
}
