<?php

namespace App\Support;

use App\Enums\ProjectTaskStatus;
use App\Models\User;

class DashboardTaskPreferences
{
    /**
     * @return array{enabled: bool, statuses: list<string>, priorities: list<string>, project_ids: list<int>}
     */
    public function forUser(User $user): array
    {
        $stored = $user->dashboard_task_preferences;

        if (! is_array($stored)) {
            return $this->defaults();
        }

        $allowedStatuses = array_map(
            static fn (ProjectTaskStatus $status): string => $status->value,
            ProjectTaskStatus::cases(),
        );

        return [
            'enabled' => array_key_exists('enabled', $stored) ? (bool) $stored['enabled'] : true,
            'statuses' => array_values(array_intersect($this->stringList($stored['statuses'] ?? []), $allowedStatuses)),
            'priorities' => $this->stringList($stored['priorities'] ?? []),
            'project_ids' => $this->intList($stored['project_ids'] ?? []),
        ];
    }

    /**
     * @param  array{enabled: bool, statuses: list<string>, priorities: list<string>, project_ids: list<int>}  $preferences
     */
    public function store(User $user, array $preferences): void
    {
        $user->dashboard_task_preferences = [
            'enabled' => $preferences['enabled'],
            'statuses' => array_values($preferences['statuses']),
            'priorities' => array_values($preferences['priorities']),
            'project_ids' => array_values($preferences['project_ids']),
        ];

        $user->save();
    }

    /**
     * @return array{enabled: bool, statuses: list<string>, priorities: list<string>, project_ids: list<int>}
     */
    public function defaults(): array
    {
        return [
            'enabled' => true,
            'statuses' => [],
            'priorities' => [],
            'project_ids' => [],
        ];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $strings = [];

        foreach ($values as $value) {
            if (is_string($value) || is_int($value)) {
                $strings[] = (string) $value;
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * @return list<int>
     */
    private function intList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $ids = [];

        foreach ($values as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }
}
