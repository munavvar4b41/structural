<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectMetadataRequest;
use App\Http\Requests\Admin\UpdateProjectMetadataRequest;
use App\Models\Project;
use App\Models\ProjectMetadata;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectMetadataController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreProjectMetadataRequest $request, Project $project): RedirectResponse
    {
        $project->metadata()->create($request->validated());

        return back()->with('toast', __('Metadata added.'));
    }

    public function update(
        UpdateProjectMetadataRequest $request,
        Project $project,
        ProjectMetadata $metadata,
    ): RedirectResponse {
        abort_if($metadata->project_id !== $project->id, 404);

        $metadata->update($request->validated());

        return back()->with('toast', __('Metadata updated.'));
    }

    public function destroy(Request $request, Project $project, ProjectMetadata $metadata): RedirectResponse
    {
        abort_if($metadata->project_id !== $project->id, 404);
        $this->authorize('manageMetadata', $project);

        $metadata->delete();

        return back()->with('toast', __('Metadata removed.'));
    }
}
