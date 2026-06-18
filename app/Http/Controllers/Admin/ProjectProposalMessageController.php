<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectProposalMessageRequest;
use App\Models\Project;
use App\Models\ProjectProposal;
use App\Models\ProjectProposalMessage;
use App\Models\User;
use App\Support\AssignmentNotificationDispatcher;
use Illuminate\Http\RedirectResponse;

class ProjectProposalMessageController extends Controller
{
    public function __construct(private readonly AssignmentNotificationDispatcher $assignmentNotificationDispatcher) {}

    public function store(
        StoreProjectProposalMessageRequest $request,
        Project $project,
        ProjectProposal $proposal,
    ): RedirectResponse {
        $this->ensureProposalBelongsToProject($project, $proposal);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        ProjectProposalMessage::query()->create([
            'project_proposal_id' => $proposal->id,
            'user_id' => $actor->id,
            'body' => $request->validated('body'),
        ]);

        $this->assignmentNotificationDispatcher->sendProjectProposalDiscussion($proposal, $actor);

        return to_route('admin.projects.proposals.show', [$project, $proposal])
            ->with('toast', __('Message posted.'));
    }

    private function ensureProposalBelongsToProject(Project $project, ProjectProposal $proposal): void
    {
        abort_if($proposal->project_id !== $project->id, 404);
    }
}
