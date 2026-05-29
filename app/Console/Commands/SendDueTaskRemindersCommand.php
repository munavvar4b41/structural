<?php

namespace App\Console\Commands;

use App\Models\ProjectTask;
use App\Support\TaskReminderDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('tasks:send-due-reminders')]
#[Description('Send one-time reminders for due scheduled tasks')]
class SendDueTaskRemindersCommand extends Command
{
    public function __construct(private readonly TaskReminderDispatcher $dispatcher)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $processed = 0;
        $notified = 0;
        $skipped = 0;

        ProjectTask::query()
            ->whereNotNull('notify_at')
            ->whereNull('notified_at')
            ->where('notify_at', '<=', now())
            ->with([
                'assignee:id,name,email',
                'project:id,name,code,lead_user_id',
                'project.leadUser:id,name,email',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($tasks) use (&$processed, &$notified, &$skipped): void {
                foreach ($tasks as $task) {
                    DB::transaction(function () use ($task, &$processed, &$notified, &$skipped): void {
                        $lockedTask = ProjectTask::query()
                            ->whereKey($task->id)
                            ->lockForUpdate()
                            ->first();

                        if ($lockedTask === null
                            || $lockedTask->notify_at === null
                            || $lockedTask->notified_at !== null
                            || $lockedTask->notify_at->isFuture()) {
                            return;
                        }

                        $lockedTask->load([
                            'assignee:id,name,email',
                            'project:id,name,code,lead_user_id',
                            'project.leadUser:id,name,email',
                        ]);

                        $processed++;
                        $result = $this->dispatcher->dispatch($lockedTask);

                        if ($result['recipients'] === 0) {
                            $skipped++;

                            return;
                        }

                        $lockedTask->forceFill(['notified_at' => now()])->save();
                        $notified++;
                    });
                }
            });

        $this->info("Processed {$processed} due tasks; notified {$notified}; skipped {$skipped}.");

        return self::SUCCESS;
    }
}
