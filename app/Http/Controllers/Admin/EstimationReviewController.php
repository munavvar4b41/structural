<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RequirementEstimationStatus;
use App\Http\Controllers\Controller;
use App\Models\ProjectRequirementEstimation;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EstimationReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $estimations = ProjectRequirementEstimation::query()
            ->where('status', RequirementEstimationStatus::PendingApproval)
            ->where('submitted_to_user_id', $actor->id)
            ->with([
                'requirement:id,title,project_id',
                'requirement.project:id,name,code',
                'creator:id,name,email',
            ])
            ->orderByDesc('submitted_at')
            ->limit(200)
            ->get()
            ->map(fn (ProjectRequirementEstimation $estimation): array => [
                'id' => $estimation->id,
                'version' => $estimation->version,
                'submitted_at' => $estimation->submitted_at?->toIso8601String(),
                'submission_notes' => $estimation->submission_notes,
                'requirement' => [
                    'id' => $estimation->requirement->id,
                    'title' => $estimation->requirement->title,
                ],
                'project' => [
                    'id' => $estimation->requirement->project->id,
                    'name' => $estimation->requirement->project->name,
                    'code' => $estimation->requirement->project->code,
                ],
                'creator' => $estimation->creator ? [
                    'id' => $estimation->creator->id,
                    'name' => $estimation->creator->name,
                    'email' => $estimation->creator->email,
                ] : null,
                'requirement_show_url' => route('admin.projects.requirements.estimation.show', [
                    $estimation->requirement->project,
                    $estimation->requirement,
                ]),
            ])
            ->all();

        return Inertia::render('admin/estimation-reviews/Index', [
            'estimations' => $estimations,
        ]);
    }
}
