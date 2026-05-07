<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;

final class ProjectRequirementAssignableUsers
{
    public const DESCRIPTION_MAX_LENGTH = 500_000;

    /**
     * @return list<int>
     */
    public static function responsibleUserIds(Project $project): array
    {
        $teamIds = $project->teams()->pluck('teams.id');

        $heads = User::query()
            ->where('role', UserRole::TeamHead)
            ->whereHas('teams', static function ($query) use ($teamIds): void {
                $query->whereIn('teams.id', $teamIds);
            })
            ->pluck('id');

        $staff = User::query()
            ->where('role', UserRole::Staff)
            ->whereHas('teams', static function ($query) use ($teamIds): void {
                $query->whereIn('teams.id', $teamIds);
            })
            ->pluck('id');

        $admins = User::query()
            ->whereIn('role', [UserRole::Admin, UserRole::SuperAdmin])
            ->pluck('id');

        return $heads->merge($staff)->merge($admins)->unique()->values()->all();
    }

    /**
     * @return list<int>
     */
    public static function reviewerUserIds(Project $project): array
    {
        $teamIds = $project->teams()->pluck('teams.id');

        return User::query()
            ->where('role', UserRole::Staff)
            ->whereHas('teams', static function ($query) use ($teamIds): void {
                $query->whereIn('teams.id', $teamIds);
            })
            ->pluck('id')
            ->all();
    }
}
