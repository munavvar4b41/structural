<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectTagRequest;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectTagController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreProjectTagRequest $request, Project $project): RedirectResponse
    {
        $project->tags()->create($request->validated());

        return back()->with('toast', __('Tag added.'));
    }

    public function destroy(Request $request, Project $project, ProjectTag $tag): RedirectResponse
    {
        abort_if($tag->project_id !== $project->id, 404);
        $this->authorize('manageTags', $project);

        $tag->delete();

        return back()->with('toast', __('Tag removed.'));
    }
}
