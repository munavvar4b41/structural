<?php

namespace App\Support;

use App\Models\ProjectRequirement;

final class SeedProposalFromRequirement
{
    /**
     * @return array{title: string, description: string}
     */
    public function seed(ProjectRequirement $requirement): array
    {
        $description = $requirement->description ?? '';

        if ($description === '' || ! TipTapDocument::isValidDocumentJson($description)) {
            $description = $this->emptyDocumentJson();
        }

        return [
            'title' => $requirement->title,
            'description' => $description,
        ];
    }

    private function emptyDocumentJson(): string
    {
        return (string) json_encode([
            'type' => 'doc',
            'content' => [],
        ]);
    }
}
