<?php

namespace App\Support;

use App\Enums\ProjectTaskStatus;
use App\Enums\TimeEntrySource;
use App\Enums\TimerPauseReason;
use App\Enums\TimerResumedBy;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single point of entry for time-tracking writes. Enforces:
 * - one running entry per user (transactional, lockForUpdate)
 * - switching tasks pauses the prior entry; same-day return resumes it
 * - non-overlapping closed entries per user (manual create/update)
 * - duration_seconds is always populated when an entry is closed.
 */
class TaskTimeTracker
{
    /**
     * Start or resume a timer on $task for today. Pauses any other running entry
     * instead of closing it. Reuses today's open paused entry when switching back.
     */
    public function start(User $user, ProjectTask $task, ?string $notes = null): TaskTimeEntry
    {
        return DB::transaction(function () use ($user, $task, $notes): TaskTimeEntry {
            $now = now();

            $this->closeStaleOpenEntriesForUser($user, $now);

            TaskTimeEntry::query()
                ->where('user_id', $user->id)
                ->open()
                ->lockForUpdate()
                ->get();

            $todayOpen = $this->findTodayOpenEntry($user, $task, $now);

            $running = TaskTimeEntry::query()
                ->where('user_id', $user->id)
                ->running()
                ->first();

            if ($todayOpen !== null && $todayOpen->isRunning()) {
                return $todayOpen;
            }

            if ($todayOpen !== null && $todayOpen->isPaused()) {
                if ($running !== null && $running->id !== $todayOpen->id) {
                    $this->pauseEntry($running, $now, TimerPauseReason::Switch);
                }

                $this->resumeEntry($todayOpen, $now);

                return $todayOpen->refresh();
            }

            if ($running !== null) {
                $this->pauseEntry($running, $now, TimerPauseReason::Switch);
            }

            return $this->createTimerEntry($user, $task, $notes, $now);
        });
    }

    /**
     * Pause the user's currently running (non-paused) entry, if any.
     */
    public function pause(
        User $user,
        ?TimerPauseReason $pauseReason = null,
        ?CarbonInterface $clientEventAt = null,
    ): ?TaskTimeEntry {
        return DB::transaction(function () use ($user, $pauseReason, $clientEventAt): ?TaskTimeEntry {
            $now = now();
            $this->closeStaleOpenEntriesForUser($user, $now);

            $running = TaskTimeEntry::query()
                ->where('user_id', $user->id)
                ->running()
                ->lockForUpdate()
                ->first();

            if ($running === null) {
                return null;
            }

            $this->pauseEntry($running, $now, $pauseReason, $clientEventAt);

            return $running->refresh();
        });
    }

    /**
     * Resume the user's most recently paused open entry, if any.
     */
    public function resume(
        User $user,
        ?TimerResumedBy $resumedBy = null,
        ?CarbonInterface $clientEventAt = null,
    ): ?TaskTimeEntry {
        return DB::transaction(function () use ($user, $resumedBy, $clientEventAt): ?TaskTimeEntry {
            $now = now();
            $this->closeStaleOpenEntriesForUser($user, $now);

            $running = TaskTimeEntry::query()
                ->where('user_id', $user->id)
                ->running()
                ->lockForUpdate()
                ->first();

            if ($running !== null) {
                $this->pauseEntry($running, $now, TimerPauseReason::Switch);
            }

            $paused = TaskTimeEntry::query()
                ->where('user_id', $user->id)
                ->open()
                ->whereNotNull('paused_at')
                ->orderByDesc('paused_at')
                ->lockForUpdate()
                ->first();

            if ($paused === null) {
                return null;
            }

            if ($resumedBy === TimerResumedBy::Inactivity
                && $paused->pause_reason !== TimerPauseReason::Inactivity) {
                return null;
            }

            $this->resumeEntry($paused, $now, $resumedBy, $clientEventAt);

            return $paused->refresh();
        });
    }

    /**
     * Stop the user's active open entry (running, or paused if none running).
     */
    public function stop(User $user): ?TaskTimeEntry
    {
        return DB::transaction(function () use ($user): ?TaskTimeEntry {
            $now = now();
            $this->closeStaleOpenEntriesForUser($user, $now);

            $open = TaskTimeEntry::query()
                ->where('user_id', $user->id)
                ->open()
                ->orderByRaw('CASE WHEN paused_at IS NULL THEN 0 ELSE 1 END')
                ->orderByDesc('started_at')
                ->lockForUpdate()
                ->first();

            if ($open === null) {
                return null;
            }

            $this->closeEntry($open, $now);

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
            'duration_seconds' => (int) round($start->diffInSeconds($end)),
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
            'duration_seconds' => (int) round($start->diffInSeconds($end)),
            'notes' => $notes,
        ])->save();

        return $entry->refresh();
    }

    private function createTimerEntry(
        User $user,
        ProjectTask $task,
        ?string $notes,
        CarbonInterface $now,
    ): TaskTimeEntry {
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
    }

    private function findTodayOpenEntry(User $user, ProjectTask $task, CarbonInterface $at): ?TaskTimeEntry
    {
        [$startOfDay, $endOfDay] = $this->dayBounds($at);

        return TaskTimeEntry::query()
            ->where('user_id', $user->id)
            ->where('project_task_id', $task->id)
            ->open()
            ->whereBetween('started_at', [$startOfDay, $endOfDay])
            ->first();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dayBounds(CarbonInterface $at): array
    {
        $day = CarbonImmutable::parse($at);

        return [$day->startOfDay(), $day->endOfDay()];
    }

    private function closeStaleOpenEntriesForUser(User $user, CarbonInterface $now): void
    {
        [$startOfDay] = $this->dayBounds($now);

        $stale = TaskTimeEntry::query()
            ->where('user_id', $user->id)
            ->open()
            ->where('started_at', '<', $startOfDay)
            ->lockForUpdate()
            ->get();

        foreach ($stale as $entry) {
            $this->closeEntry($entry, $now);
        }
    }

    private function pauseEntry(
        TaskTimeEntry $entry,
        CarbonInterface $at,
        ?TimerPauseReason $pauseReason = null,
        ?CarbonInterface $clientEventAt = null,
    ): void {
        if ($entry->paused_at !== null) {
            return;
        }

        $entry->forceFill([
            'paused_at' => $at,
            'pause_reason' => $pauseReason ?? TimerPauseReason::Manual,
            'last_client_event_at' => $clientEventAt,
        ])->save();

        $this->revertTaskStatusFromEntry($entry);
    }

    private function resumeEntry(
        TaskTimeEntry $entry,
        CarbonInterface $at,
        ?TimerResumedBy $resumedBy = null,
        ?CarbonInterface $clientEventAt = null,
    ): void {
        if ($entry->paused_at === null) {
            return;
        }

        $pauseDuration = (int) round($entry->paused_at->diffInSeconds($at));

        $entry->forceFill([
            'accumulated_pause_seconds' => (int) ($entry->accumulated_pause_seconds ?? 0) + $pauseDuration,
            'paused_at' => null,
            'pause_reason' => null,
            'resumed_by' => $resumedBy ?? TimerResumedBy::Manual,
            'last_client_event_at' => $clientEventAt,
        ])->save();

        $this->applyInProgressFromEntry($entry);
    }

    private function closeEntry(TaskTimeEntry $entry, CarbonInterface $endedAt): void
    {
        if ($endedAt->lessThan($entry->started_at)) {
            $endedAt = $entry->started_at->copy();
        }

        if ($entry->paused_at !== null) {
            $pauseDuration = (int) round($entry->paused_at->diffInSeconds($endedAt));
            $entry->accumulated_pause_seconds = (int) ($entry->accumulated_pause_seconds ?? 0) + $pauseDuration;
            $entry->paused_at = null;
        }

        $entry->forceFill([
            'ended_at' => $endedAt,
            'duration_seconds' => $entry->elapsedSeconds($endedAt),
        ])->save();

        $this->revertTaskStatusFromEntry($entry);
    }

    private function revertTaskStatusFromEntry(TaskTimeEntry $entry): void
    {
        $previousStatus = $entry->previous_task_status;
        if ($previousStatus === null) {
            return;
        }

        $task = $entry->task()->first();
        if ($task === null) {
            return;
        }

        if ($task->status === ProjectTaskStatus::InProgress) {
            $task->forceFill(['status' => $previousStatus])->save();
        }
    }

    private function applyInProgressFromEntry(TaskTimeEntry $entry): void
    {
        $previousStatus = $entry->previous_task_status;
        if ($previousStatus === null) {
            return;
        }

        $task = $entry->task()->first();
        if ($task === null) {
            return;
        }

        if ($task->status === $previousStatus) {
            $task->forceFill(['status' => ProjectTaskStatus::InProgress])->save();
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
                $q->where(function ($q2): void {
                    $q2->whereNull('ended_at')
                        ->whereNull('paused_at');
                })->orWhere(function ($q2) use ($start): void {
                    $q2->whereNotNull('ended_at')
                        ->where('ended_at', '>', $start);
                });
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
