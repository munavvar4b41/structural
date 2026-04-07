<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * @var list<string>
     */
    protected $appends = [
        'can_manage_company_settings',
        'can_manage_users',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
        ];
    }

    public function canManageCompanySettings(): bool
    {
        return $this->role->canManageCompanySettings();
    }

    public function canManageUsers(): bool
    {
        return $this->role->canManageUsers();
    }

    public function isClient(): bool
    {
        return $this->role->isClient();
    }

    public function isInternal(): bool
    {
        return $this->role->isInternal();
    }

    public function getCanManageCompanySettingsAttribute(): bool
    {
        return $this->canManageCompanySettings();
    }

    public function getCanManageUsersAttribute(): bool
    {
        return $this->canManageUsers();
    }
}
