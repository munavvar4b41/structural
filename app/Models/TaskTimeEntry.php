<?php

namespace App\Models;

use App\Enums\TimeEntrySource;
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
    'duration_seconds',
    'source',
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
            'duration_seconds' => 'integer',
            'source' => TimeEntrySource::class,
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

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * @param  Builder<TaskTimeEntry>  $query
     */
    public function scopeRunning(Builder $query): void
    {
        $query->whereNull('ended_at');
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
}
