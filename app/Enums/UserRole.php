<?php

namespace App\Enums;

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
