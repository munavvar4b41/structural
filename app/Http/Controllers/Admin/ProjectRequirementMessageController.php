<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectRequirementMessageRequest;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectRequirementMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ProjectRequirementMessageController extends Controller
{
    public function store(
        StoreProjectRequirementMessageRequest $request,
        Project $project,
        ProjectRequirement $requirement,
    ): RedirectResponse {
        $this->ensureRequirementBelongsToProject($project, $requirement);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        ProjectRequirementMessage::query()->create([
            'project_requirement_id' => $requirement->id,
            'user_id' => $actor->id,
            'body' => $request->validated('body'),
        ]);

        return to_route('admin.projects.requirements.show', [$project, $requirement])
            ->with('toast', 'Message posted.');
    }

    private function ensureRequirementBelongsToProject(Project $project, ProjectRequirement $requirement): void
    {
        abort_if($requirement->project_id !== $project->id, 404);
    }
}
