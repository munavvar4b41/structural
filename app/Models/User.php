<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'role', 'primary_team_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * @var list<string>
     */
    protected $appends = [
        'can_manage_company_settings',
        'can_manage_users',
        'can_manage_projects',
        'can_view_projects',
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

    public function canManageProjects(): bool
    {
        return $this->role->canManageProjects();
    }

    public function getCanManageCompanySettingsAttribute(): bool
    {
        return $this->canManageCompanySettings();
    }

    public function getCanManageUsersAttribute(): bool
    {
        return $this->canManageUsers();
    }

    public function getCanManageProjectsAttribute(): bool
    {
        return $this->canManageProjects();
    }

    public function getCanViewProjectsAttribute(): bool
    {
        return $this->can('viewAny', Project::class);
    }

    public function primaryTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'primary_team_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withTimestamps();
    }

    /**
     * Projects for which this user is the designated client contact.
     *
     * @return HasMany<Project, $this>
     */
    public function clientProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'client_user_id');
    }

    /**
     * Projects where this user is the internal lead.
     *
     * @return HasMany<Project, $this>
     */
    public function leadProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'lead_user_id');
    }

    /**
     * Requirements this user created.
     *
     * @return HasMany<ProjectRequirement, $this>
     */
    public function createdProjectRequirements(): HasMany
    {
        return $this->hasMany(ProjectRequirement::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<TaskTimeEntry, $this>
     */
    public function taskTimeEntries(): HasMany
    {
        return $this->hasMany(TaskTimeEntry::class);
    }
}
