<?php

namespace App\Support;

use App\Enums\ProjectTaskStatus;
use App\Enums\TimeEntrySource;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single point of entry for time-tracking writes. Enforces:
 * - one running entry per user (transactional, lockForUpdate)
 * - non-overlapping closed entries per user (manual create/update)
 * - duration_seconds is always populated when an entry is closed.
 */
class TaskTimeTracker
{
    /**
     * Start a fresh timer entry for $user on $task. If the user already has a
     * running entry it is stopped first inside the same transaction.
     */
    public function start(User $user, ProjectTask $task, ?string $notes = null): TaskTimeEntry
    {
        return DB::transaction(function () use ($user, $task, $notes): TaskTimeEntry {
            $running = TaskTimeEntry::query()
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            $now = now();

            if ($running !== null) {
                $this->closeEntry($running, $now);
            }

            $task->refresh();
            $previousStatus = $this->statusShouldAutoTransition($task->status) ? $task->status : null;

            $entry = TaskTimeEntry::query()->create([
                'project_task_id' => $task->id,
                'project_id' => $task->project_id,
                'user_id' => $user->id,
                'started_at' => $now,
                'ended_at' => null,
                'duration_seconds' => null,
                'source' => TimeEntrySource::Timer,
                'previous_task_status' => $previousStatus?->value,
                'notes' => $notes,
            ]);

            if ($previousStatus !== null) {
                $task->forceFill(['status' => ProjectTaskStatus::InProgress])->save();
            }

            return $entry;
        });
    }

    /**
     * Pause the user's currently running (non-paused) entry, if any.
     */
    public function pause(User $user): ?TaskTimeEntry
    {
        return DB::transaction(function () use ($user): ?TaskTimeEntry {
            $running = TaskTimeEntry::query()
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->whereNull('paused_at')
                ->lockForUpdate()
                ->first();

            if ($running === null) {
                return null;
            }

            $running->forceFill(['paused_at' => now()])->save();

            return $running->refresh();
        });
    }

    /**
     * Resume the user's paused entry, if any.
     */
    public function resume(User $user): ?TaskTimeEntry
    {
        return DB::transaction(function () use ($user): ?TaskTimeEntry {
            $paused = TaskTimeEntry::query()
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->whereNotNull('paused_at')
                ->lockForUpdate()
                ->first();

            if ($paused === null) {
                return null;
            }

            $pauseDuration = $paused->paused_at->diffInSeconds(now());

            $paused->forceFill([
                'accumulated_pause_seconds' => (int) ($paused->accumulated_pause_seconds ?? 0) + $pauseDuration,
                'paused_at' => null,
            ])->save();

            return $paused->refresh();
        });
    }

    /**
     * Stop the user's currently open entry (running or paused), if any.
     */
    public function stop(User $user): ?TaskTimeEntry
    {
        return DB::transaction(function () use ($user): ?TaskTimeEntry {
            $open = TaskTimeEntry::query()
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($open === null) {
                return null;
            }

            $this->closeEntry($open, now());

            return $open->refresh();
        });
    }

    /**
     * Add a closed manual time entry for $user on $task.
     */
    public function addManual(
        User $user,
        ProjectTask $task,
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $notes = null,
    ): TaskTimeEntry {
        $this->validateRange($start, $end);
        $this->ensureNoOverlap($user->id, $start, $end, null);

        return TaskTimeEntry::query()->create([
            'project_task_id' => $task->id,
            'project_id' => $task->project_id,
            'user_id' => $user->id,
            'started_at' => $start,
            'ended_at' => $end,
            'duration_seconds' => $start->diffInSeconds($end),
            'source' => TimeEntrySource::Manual,
            'notes' => $notes,
        ]);
    }

    /**
     * Update an existing entry (manual edits or note tweaks). The entry must be
     * closed; running entries should be stopped before editing.
     */
    public function updateManual(
        TaskTimeEntry $entry,
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $notes,
    ): TaskTimeEntry {
        $this->validateRange($start, $end);
        $this->ensureNoOverlap($entry->user_id, $start, $end, $entry->id);

        $entry->forceFill([
            'started_at' => $start,
            'ended_at' => $end,
            'duration_seconds' => $start->diffInSeconds($end),
            'notes' => $notes,
        ])->save();

        return $entry->refresh();
    }

    private function closeEntry(TaskTimeEntry $entry, CarbonInterface $endedAt): void
    {
        if ($endedAt->lessThan($entry->started_at)) {
            $endedAt = $entry->started_at->copy();
        }

        if ($entry->paused_at !== null) {
            $pauseDuration = $entry->paused_at->diffInSeconds($endedAt);
            $entry->accumulated_pause_seconds = (int) ($entry->accumulated_pause_seconds ?? 0) + $pauseDuration;
            $entry->paused_at = null;
        }

        $entry->forceFill([
            'ended_at' => $endedAt,
            'duration_seconds' => $entry->elapsedSeconds($endedAt),
        ])->save();

        $previousStatus = $entry->previous_task_status;
        if ($previousStatus === null) {
            return;
        }

        $task = $entry->task()->first();
        if ($task === null) {
            return;
        }

        // Only restore if the task is still parked at InProgress; if the user
        // moved it elsewhere mid-run we respect their explicit choice.
        if ($task->status === ProjectTaskStatus::InProgress) {
            $task->forceFill(['status' => $previousStatus])->save();
        }
    }

    private function statusShouldAutoTransition(ProjectTaskStatus $status): bool
    {
        return ! in_array($status, [
            ProjectTaskStatus::InProgress,
            ProjectTaskStatus::Done,
            ProjectTaskStatus::Cancelled,
        ], true);
    }

    private function validateRange(CarbonInterface $start, CarbonInterface $end): void
    {
        if (! $end->greaterThan($start)) {
            throw ValidationException::withMessages([
                'ended_at' => __('The end time must be after the start time.'),
            ]);
        }

        if ($end->greaterThan(now()->addMinute())) {
            throw ValidationException::withMessages([
                'ended_at' => __('The end time cannot be in the future.'),
            ]);
        }
    }

    private function ensureNoOverlap(
        int $userId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $excludeEntryId,
    ): void {
        $query = TaskTimeEntry::query()
            ->where('user_id', $userId)
            ->where('started_at', '<', $end)
            ->where(function ($q) use ($start): void {
                $q->whereNull('ended_at')
                    ->orWhere('ended_at', '>', $start);
            });

        if ($excludeEntryId !== null) {
            $query->where('id', '!=', $excludeEntryId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'started_at' => __('This entry overlaps another time entry for you.'),
            ]);
        }
    }
}
