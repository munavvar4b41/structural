<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'description', 'client_user_id', 'lead_user_id', 'estimation_required'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimation_required' => 'boolean',
        ];
    }

    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function leadUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_user_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withTimestamps();
    }

    /**
     * @return HasMany<ProjectRequirement, $this>
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(ProjectRequirement::class);
    }

    /**
     * @return HasMany<ProjectTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    /**
     * @return HasMany<TaskTimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TaskTimeEntry::class);
    }

    /**
     * @return HasMany<ProjectTag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(ProjectTag::class);
    }

    /**
     * @return HasMany<ProjectMetadata, $this>
     */
    public function metadata(): HasMany
    {
        return $this->hasMany(ProjectMetadata::class);
    }

    /**
     * @param  Builder<Project>  $query
     */
    public function scopeVisibleToUser(Builder $query, User $user): void
    {
        if ($user->isClient()) {
            $query->where('client_user_id', $user->id);

            return;
        }

        if (! $user->role->canViewAllProjects()) {
            $query->whereHas('teams.users', function (Builder $teamsUsers) use ($user): void {
                $teamsUsers->whereKey($user->id);
            });
        }
    }

    /**
     * First team head on this project's teams (lowest user id), for default project lead.
     */
    public function defaultTeamHeadUserId(): ?int
    {
        $user = $this->firstTeamHeadOnProjectTeams();

        return $user?->id;
    }

    /**
     * Default owner for new requirements: project lead (team head or staff on teams), else first team head.
     */
    public function defaultResponsibleUser(): ?User
    {
        if ($this->lead_user_id !== null) {
            $lead = $this->leadUser;
            if ($lead !== null && $lead->role !== UserRole::Client) {
                return $lead;
            }
        }

        return $this->firstTeamHeadOnProjectTeams();
    }

    private function firstTeamHeadOnProjectTeams(): ?User
    {
        $teamIds = $this->teams()->pluck('teams.id');
        if ($teamIds->isEmpty()) {
            return null;
        }

        return User::query()
            ->where('role', UserRole::TeamHead)
            ->whereHas('teams', static function ($query) use ($teamIds): void {
                $query->whereIn('teams.id', $teamIds);
            })
            ->orderBy('users.id')
            ->first();
    }
}
