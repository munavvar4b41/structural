<?php

namespace App\Models;

use App\Enums\TaskTagColor;
use App\Enums\TaskTagShade;
use Database\Factories\TaskTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

#[Fillable(['name', 'color', 'shade', 'sort_order'])]
class TaskTag extends Model
{
    /** @use HasFactory<TaskTagFactory> */
    use HasFactory;

    public const NONE_FILTER = 'none';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => TaskTagColor::class,
            'shade' => TaskTagShade::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<ProjectTask, $this>
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(ProjectTask::class, 'project_task_task_tag')->withTimestamps();
    }

    /**
     * @param  EloquentBuilder<TaskTag>  $query
     * @return EloquentBuilder<TaskTag>
     */
    public function scopeOrdered(EloquentBuilder $query): EloquentBuilder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return array{id: int, name: string, color: string, shade: string}
     */
    public function toBadgeArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color->value,
            'shade' => $this->shade->value,
        ];
    }

    /**
     * @param  iterable<TaskTag>  $tags
     * @return list<array{id: int, name: string, color: string, shade: string}>
     */
    public static function badgeList(iterable $tags): array
    {
        return Collection::make($tags)
            ->map(static fn (self $tag): array => $tag->toBadgeArray())
            ->values()
            ->all();
    }

    /**
     * Drop blank entries (forms send an empty marker so "no tags" can be submitted).
     */
    public static function normalizeIdsInput(mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (! is_array($raw)) {
            return $raw;
        }

        return array_values(array_filter(
            $raw,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        ));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function formOptions(): array
    {
        return self::query()
            ->ordered()
            ->get()
            ->map(static fn (self $tag): array => [
                'value' => (string) $tag->id,
                'label' => $tag->name,
            ])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function filterOptions(): array
    {
        return [
            ['value' => self::NONE_FILTER, 'label' => __('No tags')],
            ...self::formOptions(),
        ];
    }

    /**
     * @return array{ids: list<int>, include_none: bool}
     */
    public static function parseFilter(mixed $raw): array
    {
        if (! is_array($raw)) {
            return ['ids' => [], 'include_none' => false];
        }

        $allowedIds = self::query()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $ids = [];
        $includeNone = false;

        foreach ($raw as $value) {
            $string = (string) $value;

            if ($string === self::NONE_FILTER) {
                $includeNone = true;

                continue;
            }

            $id = (int) $string;

            if (in_array($id, $allowedIds, true)) {
                $ids[] = $id;
            }
        }

        return [
            'ids' => array_values(array_unique($ids)),
            'include_none' => $includeNone,
        ];
    }

    /**
     * @param  array{ids: list<int>, include_none: bool}  $filter
     * @return list<string>
     */
    public static function filterValues(array $filter): array
    {
        $values = array_map(static fn (int $id): string => (string) $id, $filter['ids']);

        if ($filter['include_none']) {
            $values[] = self::NONE_FILTER;
        }

        return $values;
    }

    /**
     * Restrict a project task query to tasks having any of the selected tags (or no tags at all).
     *
     * @param  EloquentBuilder<ProjectTask>|QueryBuilder  $query
     * @param  array{ids: list<int>, include_none: bool}  $filter
     */
    public static function applyFilter(EloquentBuilder|QueryBuilder $query, array $filter, string $taskIdColumn = 'project_tasks.id'): void
    {
        if ($filter['ids'] === [] && ! $filter['include_none']) {
            return;
        }

        $query->where(function (EloquentBuilder|QueryBuilder $query) use ($filter, $taskIdColumn): void {
            if ($filter['ids'] !== []) {
                $query->whereExists(function (QueryBuilder $sub) use ($filter, $taskIdColumn): void {
                    $sub->selectRaw('1')
                        ->from('project_task_task_tag')
                        ->whereColumn('project_task_task_tag.project_task_id', $taskIdColumn)
                        ->whereIn('project_task_task_tag.task_tag_id', $filter['ids']);
                });
            }

            if ($filter['include_none']) {
                $query->whereNotExists(function (QueryBuilder $sub) use ($taskIdColumn): void {
                    $sub->selectRaw('1')
                        ->from('project_task_task_tag')
                        ->whereColumn('project_task_task_tag.project_task_id', $taskIdColumn);
                }, $filter['ids'] !== [] ? 'or' : 'and');
            }
        });
    }
}
