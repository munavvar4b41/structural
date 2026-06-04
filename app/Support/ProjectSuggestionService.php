<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectMetadata;
use App\Models\ProjectRequirement;
use App\Models\ProjectTag;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProjectSuggestionService
{
    private const int LIMIT = 10;

    /** @var list<string> */
    private const array ALLOWED_TYPES = [
        'tag',
        'metadata_key',
        'metadata_value',
        'requirement_title',
        'task_title',
        'time_entry_notes',
    ];

    /**
     * @return list<string>
     */
    public function suggest(User $actor, string $type, string $query, ?string $metadataKey = null): array
    {
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            return [];
        }

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        return match ($type) {
            'tag' => $this->tagSuggestions($actor, $query),
            'metadata_key' => $this->metadataKeySuggestions($actor, $query),
            'metadata_value' => $this->metadataValueSuggestions($actor, $query, $metadataKey),
            'requirement_title' => $this->requirementTitleSuggestions($actor, $query),
            'task_title' => $this->taskTitleSuggestions($actor, $query),
            'time_entry_notes' => $this->timeEntryNotesSuggestions($actor, $query),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function tagSuggestions(User $actor, string $query): array
    {
        return $this->distinctColumnSuggestions(
            ProjectTag::query()
                ->select('project_tags.name')
                ->join('projects', 'projects.id', '=', 'project_tags.project_id'),
            'project_tags.name',
            $query,
            fn (Builder $builder) => $this->scopeVisibleProjects($builder, $actor, 'projects'),
        );
    }

    /**
     * @return list<string>
     */
    private function metadataKeySuggestions(User $actor, string $query): array
    {
        return $this->distinctColumnSuggestions(
            ProjectMetadata::query()
                ->select('project_metadata.key')
                ->join('projects', 'projects.id', '=', 'project_metadata.project_id'),
            'project_metadata.key',
            $query,
            fn (Builder $builder) => $this->scopeVisibleProjects($builder, $actor, 'projects'),
        );
    }

    /**
     * @return list<string>
     */
    private function metadataValueSuggestions(User $actor, string $query, ?string $metadataKey): array
    {
        $normalizedKey = $metadataKey !== null && $metadataKey !== ''
            ? ProjectMetadataNormalizer::normalizeKey($metadataKey)
            : null;

        return $this->distinctColumnSuggestions(
            ProjectMetadata::query()
                ->select('project_metadata.value')
                ->join('projects', 'projects.id', '=', 'project_metadata.project_id')
                ->when($normalizedKey !== null, static fn (Builder $builder) => $builder->where('project_metadata.key', $normalizedKey)),
            'project_metadata.value',
            $query,
            fn (Builder $builder) => $this->scopeVisibleProjects($builder, $actor, 'projects'),
        );
    }

    /**
     * @return list<string>
     */
    private function requirementTitleSuggestions(User $actor, string $query): array
    {
        return $this->distinctColumnSuggestions(
            ProjectRequirement::query()
                ->select('project_requirements.title')
                ->join('projects', 'projects.id', '=', 'project_requirements.project_id'),
            'project_requirements.title',
            $query,
            fn (Builder $builder) => $this->scopeVisibleProjects($builder, $actor, 'projects'),
        );
    }

    /**
     * @return list<string>
     */
    private function taskTitleSuggestions(User $actor, string $query): array
    {
        return $this->distinctColumnSuggestions(
            ProjectTask::query()
                ->select('project_tasks.title')
                ->join('projects', 'projects.id', '=', 'project_tasks.project_id'),
            'project_tasks.title',
            $query,
            fn (Builder $builder) => $this->scopeVisibleProjects($builder, $actor, 'projects'),
        );
    }

    /**
     * @return list<string>
     */
    private function timeEntryNotesSuggestions(User $actor, string $query): array
    {
        return $this->distinctColumnSuggestions(
            TaskTimeEntry::query()
                ->select('task_time_entries.notes')
                ->join('projects', 'projects.id', '=', 'task_time_entries.project_id')
                ->whereNotNull('task_time_entries.notes')
                ->where('task_time_entries.notes', '!=', ''),
            'task_time_entries.notes',
            $query,
            fn (Builder $builder) => $this->scopeVisibleProjects($builder, $actor, 'projects'),
        );
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  callable(Builder<Model>): void  $scope
     * @return list<string>
     */
    private function distinctColumnSuggestions(
        Builder $builder,
        string $column,
        string $query,
        callable $scope,
    ): array {
        $scope($builder);
        $this->applyPrefixMatch($builder, $column, $query);

        /** @var list<string> */
        return $builder
            ->distinct()
            ->orderBy($column)
            ->limit(self::LIMIT)
            ->pluck($column)
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private function scopeVisibleProjects(Builder $builder, User $actor, string $projectsTable): void
    {
        $builder->whereIn("{$projectsTable}.id", Project::query()->visibleToUser($actor)->select('projects.id'));
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private function applyPrefixMatch(Builder $builder, string $column, string $query): void
    {
        $term = strtolower(addcslashes($query, '%_\\')).'%';
        $builder->whereRaw('LOWER('.$column.') LIKE ?', [$term]);
    }
}
