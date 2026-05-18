<?php

namespace App\Models;

use App\Enums\ProjectTaskStatus;
use App\Enums\TimeEntrySource;
use Carbon\CarbonImmutable;
use Database\Factories\TaskTimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_task_id',
    'project_id',
    'user_id',
    'started_at',
    'ended_at',
    'paused_at',
    'accumulated_pause_seconds',
    'duration_seconds',
    'source',
    'previous_task_status',
    'notes',
])]
class TaskTimeEntry extends Model
{
    /** @use HasFactory<TaskTimeEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'paused_at' => 'datetime',
            'accumulated_pause_seconds' => 'integer',
            'duration_seconds' => 'integer',
            'source' => TimeEntrySource::class,
            'previous_task_status' => ProjectTaskStatus::class,
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    public function isPaused(): bool
    {
        return $this->isOpen() && $this->paused_at !== null;
    }

    public function isRunning(): bool
    {
        return $this->isOpen() && $this->paused_at === null;
    }

    public function elapsedSeconds(?\DateTimeInterface $at = null): int
    {
        $at = $at ?? now();

        if ($this->ended_at !== null) {
            return max(0, (int) $this->duration_seconds);
        }

        $gross = $this->started_at->diffInSeconds($at);
        $paused = (int) ($this->accumulated_pause_seconds ?? 0);

        if ($this->paused_at !== null) {
            $paused += $this->paused_at->diffInSeconds($at);
        }

        return max(0, $gross - $paused);
    }

    /**
     * @param  Builder<TaskTimeEntry>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    /**
     * @param  Builder<TaskTimeEntry>  $query
     */
    public function scopeRunning(Builder $query): void
    {
        $query->whereNull('ended_at')->whereNull('paused_at');
    }

    /**
     * @param  Builder<TaskTimeEntry>  $query
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * Filter entries whose started_at falls within [from, to] (inclusive of both ends).
     *
     * @param  Builder<TaskTimeEntry>  $query
     */
    public function scopeBetweenDates(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): void
    {
        $query->whereBetween('started_at', [$from, $to]);
    }

    public static function activeSessionForUser(int $userId): ?self
    {
        return once(function () use ($userId): ?self {
            return self::query()
                ->where('user_id', $userId)
                ->open()
                ->orderByRaw('CASE WHEN paused_at IS NULL THEN 0 ELSE 1 END')
                ->orderByDesc('started_at')
                ->first();
        });
    }

    public static function todayElapsedSecondsForUserOnTask(
        int $userId,
        int $taskId,
        ?\DateTimeInterface $at = null,
    ): int {
        return self::todayElapsedSecondsForUserOnTasks($userId, [$taskId], $at)[$taskId] ?? 0;
    }

    /**
     * @param  list<int>  $taskIds
     * @return array<int, int>
     */
    public static function todayElapsedSecondsForUserOnTasks(
        int $userId,
        array $taskIds,
        ?\DateTimeInterface $at = null,
    ): array {
        $taskIds = array_values(array_unique($taskIds));

        if ($taskIds === []) {
            return [];
        }

        $at = $at ?? now();
        $day = CarbonImmutable::parse($at);
        $startOfDay = $day->startOfDay();
        $endOfDay = $day->endOfDay();

        /** @var array<int, int> $closedTotals */
        $closedTotals = self::query()
            ->where('user_id', $userId)
            ->whereIn('project_task_id', $taskIds)
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$startOfDay, $endOfDay])
            ->groupBy('project_task_id')
            ->selectRaw('project_task_id, sum(duration_seconds) as total')
            ->pluck('total', 'project_task_id')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        $openEntries = self::query()
            ->where('user_id', $userId)
            ->whereIn('project_task_id', $taskIds)
            ->open()
            ->whereBetween('started_at', [$startOfDay, $endOfDay])
            ->get()
            ->keyBy('project_task_id');

        $result = [];
        foreach ($taskIds as $taskId) {
            $closed = $closedTotals[$taskId] ?? 0;
            $open = $openEntries->get($taskId);

            $result[$taskId] = $open === null
                ? $closed
                : $closed + $open->elapsedSeconds($at);
        }

        return $result;
    }
}
