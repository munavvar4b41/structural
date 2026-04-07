<?php

namespace App\Enums;

use App\Models\User;
use Illuminate\Support\Str;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case TeamHead = 'team_head';
    case Staff = 'staff';
    case Client = 'client';

    public function isInternal(): bool
    {
        return $this !== self::Client;
    }

    public function isClient(): bool
    {
        return $this === self::Client;
    }

    /**
     * Whether this role may edit org-wide company settings (Spatie).
     */
    public function canManageCompanySettings(): bool
    {
        return $this === self::SuperAdmin || $this === self::Admin;
    }

    /**
     * Whether this role may use admin user management (list/create/edit/delete users).
     */
    public function canManageUsers(): bool
    {
        return $this->canManageCompanySettings();
    }

    public function canManageProjects(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin, self::TeamHead], true);
    }

    public function label(): string
    {
        return Str::title(str_replace('_', ' ', $this->value));
    }

    /**
     * Roles the actor may assign when creating or updating users.
     *
     * @return list<self>
     */
    public static function assignableRolesForActor(User $actor): array
    {
        if ($actor->role === self::SuperAdmin) {
            return self::cases();
        }

        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => $role !== self::SuperAdmin,
        ));
    }

    /**
     * @return list<string>
     */
    public static function assignableRoleValuesForActor(User $actor): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::assignableRolesForActor($actor),
        );
    }

    /**
     * @return array<int, self>
     */
    public static function internalCases(): array
    {
        return [
            self::SuperAdmin,
            self::Admin,
            self::TeamHead,
            self::Staff,
        ];
    }
}
