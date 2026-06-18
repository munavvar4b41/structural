<?php

namespace App\Models;

use App\Enums\RequirementEstimationStatus;
use Database\Factories\ProjectRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'created_by_user_id',
    'responsible_user_id',
    'reviewer_user_id',
    'title',
    'description',
    'reviewed_at',
    'review_understanding',
    'understanding_confirmed_at',
    'understanding_confirmed_by_user_id',
    'max_generated_phase',
])]
class ProjectRequirement extends Model
{
    /** @use HasFactory<ProjectRequirementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'understanding_confirmed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    public function understandingConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'understanding_confirmed_by_user_id');
    }

    /**
     * @return HasMany<int, ProjectRequirementMessage>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ProjectRequirementMessage::class);
    }

    /**
     * @return HasMany<ProjectProposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(ProjectProposal::class, 'project_requirement_id');
    }

    /**
     * @return HasMany<ProjectTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'project_requirement_id');
    }

    /**
     * @return HasMany<ProjectRequirementEstimation, $this>
     */
    public function estimations(): HasMany
    {
        return $this->hasMany(ProjectRequirementEstimation::class);
    }

    public function activeEstimation(): ?ProjectRequirementEstimation
    {
        return $this->estimations()
            ->whereIn('status', [
                RequirementEstimationStatus::Draft,
                RequirementEstimationStatus::PendingApproval,
                RequirementEstimationStatus::ChangesRequested,
            ])
            ->orderByDesc('version')
            ->first();
    }

    public function latestApprovedOrTransferredEstimation(): ?ProjectRequirementEstimation
    {
        return $this->estimations()
            ->whereIn('status', [
                RequirementEstimationStatus::Approved,
                RequirementEstimationStatus::Transferred,
            ])
            ->orderByDesc('version')
            ->first();
    }
}
