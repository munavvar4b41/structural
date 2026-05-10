<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTaskReview;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TaskRatingReportController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);
        abort_unless($actor->can_view_task_rating_report, 403);

        $today = CarbonImmutable::today();
        $from = $this->parseDate($request->query('from'), $today->subDays(30));
        $to = $this->parseDate($request->query('to'), $today);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $rangeStart = $from->startOfDay();
        $rangeEnd = $to->endOfDay();

        $projectFilterId = $this->parseProjectFilter($request->query('project_id'), $actor);
        $userFilterId = $this->parseUserFilter($request->query('user_id'));

        $reviews = ProjectTaskReview::query()
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->whereHas('task', function ($taskQuery) use ($actor, $projectFilterId): void {
                $taskQuery->whereHas('project', function ($projectQuery) use ($actor, $projectFilterId): void {
                    if (in_array($actor->role, [UserRole::SuperAdmin, UserRole::Admin, UserRole::TeamHead], true)) {
                        $projectQuery->visibleToUser($actor);
                    } else {
                        $projectQuery->where('lead_user_id', $actor->id);
                    }

                    if ($projectFilterId !== null) {
                        $projectQuery->where('id', $projectFilterId);
                    }
                });
            })
            ->when($userFilterId !== null, function ($q) use ($userFilterId): void {
                $q->whereHas('task', function ($taskQuery) use ($userFilterId): void {
                    $taskQuery->where(function ($inner) use ($userFilterId): void {
                        $inner->where('assignee_user_id', $userFilterId)
                            ->orWhere('created_by_user_id', $userFilterId);
                    });
                });
            })
            ->with([
                'task:id,title,project_id,assignee_user_id,created_by_user_id',
                'task.project:id,name,code',
            ])
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get();

        $aggregates = $this->aggregateByUser($reviews);
        $userIds = array_keys($aggregates);
        $names = User::query()
            ->whereIn('id', $userIds ?: [0])
            ->pluck('name', 'id');

        $rows = [];
        foreach ($aggregates as $userId => $bucket) {
            $assigneeScores = $bucket['assignee_scores'];
            $creatorScores = $bucket['creator_scores'];
            $rows[] = [
                'user_id' => $userId,
                'name' => $names[(int) $userId] ?? __('User #:id', ['id' => $userId]),
                'assignee_avg' => $assigneeScores === [] ? null : round(array_sum($assigneeScores) / count($assigneeScores), 2),
                'assignee_count' => count($assigneeScores),
                'creator_avg' => $creatorScores === [] ? null : round(array_sum($creatorScores) / count($creatorScores), 2),
                'creator_count' => count($creatorScores),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        $recent = $reviews->take(100)->map(static function (ProjectTaskReview $review): array {
            $task = $review->task;

            return [
                'id' => $review->id,
                'created_at' => $review->created_at->toIso8601String(),
                'task_title' => $task?->title,
                'project_name' => $task?->project?->name,
                'project_code' => $task?->project?->code,
                'task_rating' => $review->task_rating,
                'assignee_rating' => $review->assignee_rating,
                'creator_rating' => $review->creator_rating,
            ];
        })->values()->all();

        return Inertia::render('admin/task-ratings-report/Index', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'project_id' => $projectFilterId,
                'user_id' => $userFilterId,
            ],
            'project_options' => $this->projectOptions($actor),
            'user_options' => $this->userOptions($actor),
            'rows' => $rows,
            'recent_reviews' => $recent,
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

    private function parseProjectFilter(mixed $value, User $actor): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $id = (int) $value;
        if ($id <= 0) {
            return null;
        }

        $allowed = Project::query()
            ->when(
                in_array($actor->role, [UserRole::SuperAdmin, UserRole::Admin, UserRole::TeamHead], true),
                fn ($q) => $q->visibleToUser($actor),
                fn ($q) => $q->where('lead_user_id', $actor->id),
            )
            ->whereKey($id)
            ->exists();

        return $allowed ? $id : null;
    }

    private function parseUserFilter(mixed $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /**
     * @param  Collection<int, ProjectTaskReview>  $reviews
     * @return array<int, array{assignee_scores: list<int>, creator_scores: list<int>}>
     */
    private function aggregateByUser($reviews): array
    {
        $buckets = [];

        foreach ($reviews as $review) {
            $task = $review->task;
            if ($task === null) {
                continue;
            }

            if ($task->assignee_user_id !== null && $review->assignee_rating !== null) {
                $aid = (int) $task->assignee_user_id;
                $buckets[$aid] ??= ['assignee_scores' => [], 'creator_scores' => []];
                $buckets[$aid]['assignee_scores'][] = $review->assignee_rating;
            }

            $cid = (int) $task->created_by_user_id;
            if ($review->creator_rating !== null) {
                $buckets[$cid] ??= ['assignee_scores' => [], 'creator_scores' => []];
                $buckets[$cid]['creator_scores'][] = $review->creator_rating;
            }
        }

        return $buckets;
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function projectOptions(User $actor): array
    {
        $query = Project::query()->orderBy('name');

        if (in_array($actor->role, [UserRole::SuperAdmin, UserRole::Admin, UserRole::TeamHead], true)) {
            $query->visibleToUser($actor);
        } else {
            $query->where('lead_user_id', $actor->id);
        }

        return $query->get(['id', 'name', 'code'])
            ->map(static fn (Project $p): array => [
                'value' => $p->id,
                'label' => $p->code !== null ? $p->name.' ('.$p->code.')' : $p->name,
            ])
            ->all();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function userOptions(User $actor): array
    {
        return User::query()
            ->where('role', '!=', UserRole::Client->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(static fn (User $u): array => [
                'value' => $u->id,
                'label' => $u->name.' ('.$u->email.')',
            ])
            ->all();
    }
}
