<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

final class RequirementEstimationAssignableApprovers
{
    /**
     * @return list<int>
     */
    public static function approverUserIds(Project $project): array
    {
        $ids = collect();

        if ($project->lead_user_id !== null) {
            $ids->push($project->lead_user_id);
        }

        $teamIds = $project->teams()->pluck('teams.id');

        $heads = User::query()
            ->where('role', UserRole::TeamHead)
            ->whereHas('teams', static function ($query) use ($teamIds): void {
                $query->whereIn('teams.id', $teamIds);
            })
            ->pluck('id');

        $admins = User::query()
            ->whereIn('role', [UserRole::Admin, UserRole::SuperAdmin])
            ->pluck('id');

        return $ids->merge($heads)->merge($admins)->unique()->values()->all();
    }

    /**
     * @return Collection<int, User>
     */
    public static function approverUsers(Project $project): Collection
    {
        $ids = self::approverUserIds($project);
        if ($ids === []) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
