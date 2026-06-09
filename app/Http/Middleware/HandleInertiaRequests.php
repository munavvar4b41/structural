<?php

namespace App\Http\Middleware;

use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\User;
use App\Settings\CompanySettings;
use App\Support\NotificationFeedBuilder;
use App\Support\ProjectTaskHierarchy;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @return array{name: string, registration_email_domain: string}
     */
    protected function companyRegistrationProps(): array
    {
        $settings = app(CompanySettings::class);

        return [
            'name' => $settings->name,
            'registration_email_domain' => $settings->email_domain,
        ];
    }

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'companyRegistration' => $this->companyRegistrationProps(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'toast' => $request->session()->get('toast'),
            ],
            'active_time_entry' => fn () => $this->activeTimeEntryProps($request),
            'notifications' => fn () => $this->notificationProps($request),
        ];
    }

    /**
     * @return array{
     *     unread_count: int,
     *     read_count: int,
     *     unread_items: list<array{id: string, type: string, read_at: string|null, created_at: string|null, title: string|null, project_name: string|null, task_show_url: string|null}>,
     *     read_items: list<array{id: string, type: string, read_at: string|null, created_at: string|null, title: string|null, project_name: string|null, task_show_url: string|null}>
     * }
     */
    protected function notificationProps(Request $request): array
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return [
                'unread_count' => 0,
                'read_count' => 0,
                'unread_items' => [],
                'read_items' => [],
            ];
        }

        return app(NotificationFeedBuilder::class)->buildForUser($user);
    }

    /**
     * @return array{id: int, task_id: int, project_id: int, task_title: string, project_name: string, project_code: string|null, started_at: string, is_paused: bool, elapsed_seconds: int, task_today_seconds: int, my_all_time_seconds: int}|null
     */
    protected function activeTimeEntryProps(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $entry = TaskTimeEntry::activeSessionForUser($user->id);
        if ($entry === null) {
            return null;
        }

        $entry->loadMissing(['task:id,title', 'project:id,name,code']);

        $taskTodaySeconds = $this->todaySecondsForActiveEntry($user->id, $entry);

        return [
            'id' => $entry->id,
            'task_id' => $entry->project_task_id,
            'project_id' => $entry->project_id,
            'task_title' => $entry->task?->title ?? '',
            'project_name' => $entry->project?->name ?? '',
            'project_code' => $entry->project?->code,
            'started_at' => $entry->started_at->toIso8601String(),
            'is_paused' => $entry->isPaused(),
            'elapsed_seconds' => $entry->elapsedSeconds(),
            'task_today_seconds' => $taskTodaySeconds,
            'my_all_time_seconds' => TaskTimeEntry::elapsedSecondsForUserOnTask(
                $user->id,
                $entry->project_task_id,
            ),
        ];
    }

    private function todaySecondsForActiveEntry(int $userId, TaskTimeEntry $entry): int
    {
        $task = ProjectTask::query()->find($entry->project_task_id);

        if ($task === null) {
            return TaskTimeEntry::todayElapsedSecondsForUserOnTask($userId, $entry->project_task_id);
        }

        $hierarchy = app(ProjectTaskHierarchy::class);
        $familyIds = $hierarchy->familyIds($task);

        return TaskTimeEntry::todayElapsedSecondsForUserOnTaskFamily($userId, $familyIds);
    }
}
