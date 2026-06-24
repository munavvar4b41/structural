<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnderstandingReviewController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);
        abort_if($actor->role === UserRole::Client, 403);

        $visibleProjectIds = Project::query()
            ->visibleToUser($actor)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($visibleProjectIds === []) {
            return Inertia::render('admin/understanding-reviews/Index', [
                'requirements' => [],
            ]);
        }

        $requirements = ProjectRequirement::query()
            ->whereIn('project_id', $visibleProjectIds)
            ->whereNull('understanding_confirmed_at')
            ->with([
                'project:id,name,code',
                'creator:id,name,email',
                'reviewer:id,name,email',
                'responsibleUser:id,name,email',
            ])
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->filter(fn (ProjectRequirement $requirement): bool => $this->isActionableForActor($actor, $requirement))
            ->values()
            ->map(fn (ProjectRequirement $requirement): array => $this->queueRow($requirement, $actor))
            ->all();

        return Inertia::render('admin/understanding-reviews/Index', [
            'requirements' => $requirements,
        ]);
    }

    private function isActionableForActor(User $actor, ProjectRequirement $requirement): bool
    {
        if ($requirement->reviewed_at === null && $actor->can('markReviewed', $requirement)) {
            return true;
        }

        return $actor->can('confirmUnderstanding', $requirement);
    }

    /**
     * @return array<string, mixed>
     */
    private function queueRow(ProjectRequirement $requirement, User $actor): array
    {
        $project = $requirement->project;
        $reviewStage = $requirement->reviewed_at === null && $actor->can('markReviewed', $requirement)
            ? 'pending_review'
            : 'awaiting_confirmation';

        return [
            'id' => $requirement->id,
            'title' => $requirement->title,
            'review_stage' => $reviewStage,
            'reviewed_at' => $requirement->reviewed_at?->toIso8601String(),
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
            ],
            'creator' => $this->userBrief($requirement->creator),
            'reviewer' => $this->userBrief($requirement->reviewer),
            'responsible_user' => $this->userBrief($requirement->responsibleUser),
            'requirement_show_url' => route('admin.projects.requirements.show', [$project, $requirement]),
        ];
    }

    /**
     * @return array{id: int, name: string, email: string}|null
     */
    private function userBrief(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
