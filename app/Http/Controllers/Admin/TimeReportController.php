<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TimeReportController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);
        abort_if($actor->isClient(), 403);

        $today = CarbonImmutable::today();
        $from = $this->parseDate($request->query('from'), $today);
        $to = $this->parseDate($request->query('to'), $today);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $rangeStart = $from->startOfDay();
        $rangeEnd = $to->endOfDay();

        $targetUserId = (int) $request->query('user_id', (string) $actor->id);
        $target = $targetUserId === $actor->id
            ? $actor
            : User::query()->find($targetUserId);

        if ($target === null) {
            $target = $actor;
        }

        $this->authorize('viewReportFor', [TaskTimeEntry::class, $target]);

        $allowedProjectIds = $this->allowedProjectIds($actor, $target);
        $projectFilterId = $this->parseProjectFilter($request->query('project_id'), $allowedProjectIds);

        $entriesQuery = TaskTimeEntry::query()
            ->where('user_id', $target->id)
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$rangeStart, $rangeEnd])
            ->when($allowedProjectIds !== null, fn ($q) => $q->whereIn('project_id', $allowedProjectIds ?: [0]))
            ->when($projectFilterId !== null, fn ($q) => $q->where('project_id', $projectFilterId));

        $entries = (clone $entriesQuery)
            ->with(['project:id,name,code', 'task:id,title,project_id'])
            ->orderByDesc('started_at')
            ->limit(500)
            ->get();

        $perDay = $this->aggregatePerDay($entries);
        $perProject = $this->aggregatePerProject($entries);
        $perTask = $this->aggregatePerTask($entries);

        return Inertia::render('admin/time-report/Index', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'user_id' => $target->id,
                'project_id' => $projectFilterId,
            ],
            'target_user' => [
                'id' => $target->id,
                'name' => $target->name,
                'email' => $target->email,
            ],
            'can_view_other_users' => $this->canViewOtherUsers($actor),
            'user_options' => $this->userOptions($actor),
            'project_options' => $this->projectOptions($actor, $target, $allowedProjectIds),
            'per_day' => $perDay,
            'per_project' => $perProject,
            'per_task' => $perTask,
            'totals' => [
                'seconds' => $entries->sum('duration_seconds'),
                'entries' => $entries->count(),
            ],
            'entries' => $entries->map(fn (TaskTimeEntry $e): array => [
                'id' => $e->id,
                'project_id' => $e->project_id,
                'project_name' => $e->project?->name,
                'project_code' => $e->project?->code,
                'task_id' => $e->project_task_id,
                'task_title' => $e->task?->title,
                'started_at' => $e->started_at?->toIso8601String(),
                'ended_at' => $e->ended_at?->toIso8601String(),
                'duration_seconds' => $e->duration_seconds,
                'source' => $e->source->value,
                'notes' => $e->notes,
            ])->all(),
        ]);
    }

    private function parseDate(mixed $value, CarbonImmutable $fallback): CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return $fallback;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * @param  list<int>|null  $allowedProjectIds
     */
    private function parseProjectFilter(mixed $value, ?array $allowedProjectIds): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $id = (int) $value;
        if ($id <= 0) {
            return null;
        }

        if ($allowedProjectIds !== null && ! in_array($id, $allowedProjectIds, true)) {
            return null;
        }

        return $id;
    }

    /**
     * Returns null when actor has no project-level scoping (admins/team-heads/super admins
     * see all data for the target). Otherwise returns the list of project ids the actor
     * may include in the report (intersection of actor leadership and target visibility).
     *
     * @return list<int>|null
     */
    private function allowedProjectIds(User $actor, User $target): ?array
    {
        if ($actor->id === $target->id) {
            return null;
        }

        if ($this->isElevated($actor)) {
            return null;
        }

        return Project::query()
            ->where('lead_user_id', $actor->id)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function isElevated(User $actor): bool
    {
        return in_array($actor->role, [
            UserRole::SuperAdmin,
            UserRole::Admin,
            UserRole::TeamHead,
        ], true);
    }

    private function canViewOtherUsers(User $actor): bool
    {
        if ($this->isElevated($actor)) {
            return true;
        }

        return Project::query()->where('lead_user_id', $actor->id)->exists();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function userOptions(User $actor): array
    {
        if (! $this->canViewOtherUsers($actor)) {
            return [[
                'value' => $actor->id,
                'label' => $actor->name.' ('.$actor->email.')',
            ]];
        }

        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(static fn (User $u): array => [
                'value' => $u->id,
                'label' => $u->name.' ('.$u->email.')',
            ])
            ->all();
    }

    /**
     * @param  list<int>|null  $allowedProjectIds
     * @return list<array{value: int, label: string}>
     */
    private function projectOptions(User $actor, User $target, ?array $allowedProjectIds): array
    {
        $query = Project::query()->orderBy('name');

        if ($allowedProjectIds !== null) {
            $query->whereIn('id', $allowedProjectIds ?: [0]);
        } elseif (! $actor->role->canViewAllProjects()) {
            $query->visibleToUser($actor);
        }

        return $query->get(['id', 'name', 'code'])
            ->map(static fn (Project $p): array => [
                'value' => $p->id,
                'label' => $p->code !== null ? $p->name.' ('.$p->code.')' : $p->name,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, TaskTimeEntry>  $entries
     * @return list<array{date: string, total_seconds: int, projects: list<array{project_id: int, project_name: string|null, total_seconds: int}>}>
     */
    private function aggregatePerDay($entries): array
    {
        $byDay = [];

        foreach ($entries as $entry) {
            $date = $entry->started_at->copy()->format('Y-m-d');
            $byDay[$date] ??= [
                'date' => $date,
                'total_seconds' => 0,
                'projects' => [],
            ];

            $byDay[$date]['total_seconds'] += (int) $entry->duration_seconds;

            $pid = $entry->project_id;
            $byDay[$date]['projects'][$pid] ??= [
                'project_id' => $pid,
                'project_name' => $entry->project?->name,
                'total_seconds' => 0,
            ];
            $byDay[$date]['projects'][$pid]['total_seconds'] += (int) $entry->duration_seconds;
        }

        $rows = [];
        foreach ($byDay as $row) {
            $row['projects'] = array_values($row['projects']);
            usort($row['projects'], static fn ($a, $b) => $b['total_seconds'] <=> $a['total_seconds']);
            $rows[] = $row;
        }

        usort($rows, static fn ($a, $b) => strcmp($b['date'], $a['date']));

        return $rows;
    }

    /**
     * @param  Collection<int, TaskTimeEntry>  $entries
     * @return list<array{project_id: int, project_name: string|null, project_code: string|null, total_seconds: int, task_count: int}>
     */
    private function aggregatePerProject($entries): array
    {
        $byProject = [];

        foreach ($entries as $entry) {
            $pid = $entry->project_id;
            $byProject[$pid] ??= [
                'project_id' => $pid,
                'project_name' => $entry->project?->name,
                'project_code' => $entry->project?->code,
                'total_seconds' => 0,
                'task_ids' => [],
            ];

            $byProject[$pid]['total_seconds'] += (int) $entry->duration_seconds;
            $byProject[$pid]['task_ids'][$entry->project_task_id] = true;
        }

        $rows = [];
        foreach ($byProject as $row) {
            $row['task_count'] = count($row['task_ids']);
            unset($row['task_ids']);
            $rows[] = $row;
        }

        usort($rows, static fn ($a, $b) => $b['total_seconds'] <=> $a['total_seconds']);

        return $rows;
    }

    /**
     * @param  Collection<int, TaskTimeEntry>  $entries
     * @return list<array{task_id: int, task_title: string|null, project_id: int, project_name: string|null, total_seconds: int}>
     */
    private function aggregatePerTask($entries): array
    {
        $byTask = [];

        foreach ($entries as $entry) {
            $tid = $entry->project_task_id;
            $byTask[$tid] ??= [
                'task_id' => $tid,
                'task_title' => $entry->task?->title,
                'project_id' => $entry->project_id,
                'project_name' => $entry->project?->name,
                'total_seconds' => 0,
            ];
            $byTask[$tid]['total_seconds'] += (int) $entry->duration_seconds;
        }

        $rows = array_values($byTask);
        usort($rows, static fn ($a, $b) => $b['total_seconds'] <=> $a['total_seconds']);

        return $rows;
    }
}
