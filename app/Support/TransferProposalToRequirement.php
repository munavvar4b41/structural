<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectProposal;
use App\Models\ProjectRequirement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TransferProposalToRequirement
{
    public function transfer(ProjectProposal $proposal, Project $project, User $actor): ProjectRequirement
    {
        return DB::transaction(function () use ($proposal, $project): ProjectRequirement {
            $requirement = ProjectRequirement::query()->create([
                'project_id' => $project->id,
                'created_by_user_id' => $proposal->created_by_user_id,
                'title' => $proposal->title,
                'description' => $this->normalizeDescription($proposal->description),
                'max_generated_phase' => 1,
            ]);

            $proposal->forceFill([
                'transferred_project_requirement_id' => $requirement->id,
            ])->save();

            return $requirement;
        });
    }

    private function normalizeDescription(?string $description): string
    {
        if ($description !== null && $description !== '' && TipTapDocument::isValidDocumentJson($description)) {
            return $description;
        }

        return $this->plainTextToTipTapJson($description ?? '');
    }

    private function plainTextToTipTapJson(string $text): string
    {
        if ($text === '') {
            return (string) json_encode([
                'type' => 'doc',
                'content' => [],
            ]);
        }

        return (string) json_encode([
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => $text],
                    ],
                ],
            ],
        ]);
    }
}
