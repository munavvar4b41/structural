<?php

namespace App\Models;

use App\Enums\TaskPriorityColor;
use App\Enums\TaskPriorityShade;
use Database\Factories\TaskPriorityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

#[Fillable(['name', 'color', 'shade', 'sort_order'])]
class TaskPriority extends Model
{
    /** @use HasFactory<TaskPriorityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => TaskPriorityColor::class,
            'shade' => TaskPriorityShade::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<ProjectTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    /**
     * @param  EloquentBuilder<TaskPriority>  $query
     * @return EloquentBuilder<TaskPriority>
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

    public const NONE_FILTER = 'none';

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function formOptions(): array
    {
        return self::query()
            ->ordered()
            ->get()
            ->map(static fn (self $priority): array => [
                'value' => (string) $priority->id,
                'label' => $priority->name,
            ])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function filterOptions(): array
    {
        return [
            ['value' => self::NONE_FILTER, 'label' => __('No priority')],
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
     * @param  EloquentBuilder<ProjectTask>|QueryBuilder  $query
     * @param  array{ids: list<int>, include_none: bool}  $filter
     */
    public static function applyFilter(EloquentBuilder|QueryBuilder $query, array $filter): void
    {
        if ($filter['ids'] === [] && ! $filter['include_none']) {
            return;
        }

        $query->where(function (EloquentBuilder|QueryBuilder $query) use ($filter): void {
            if ($filter['ids'] !== []) {
                $query->whereIn('task_priority_id', $filter['ids']);
            }

            if (! $filter['include_none']) {
                return;
            }

            if ($filter['ids'] !== []) {
                $query->orWhereNull('task_priority_id');
            } else {
                $query->whereNull('task_priority_id');
            }
        });
    }
}
