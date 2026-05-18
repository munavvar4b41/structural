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
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'primary_team_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens;

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
        'can_approve_leave_requests',
        'can_review_task_completions',
        'can_view_task_rating_report',
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

    public function canApproveLeaveRequests(): bool
    {
        return $this->role->canApproveLeaveRequests();
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

    public function getCanApproveLeaveRequestsAttribute(): bool
    {
        return $this->canApproveLeaveRequests();
    }

    public function getCanReviewTaskCompletionsAttribute(): bool
    {
        if ($this->isClient()) {
            return false;
        }

        if (in_array($this->role, [UserRole::SuperAdmin, UserRole::Admin, UserRole::TeamHead], true)) {
            return true;
        }

        return $this->leadProjects()->exists();
    }

    public function getCanViewTaskRatingReportAttribute(): bool
    {
        return $this->getCanReviewTaskCompletionsAttribute();
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

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
