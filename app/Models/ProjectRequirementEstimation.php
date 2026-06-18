<?php

namespace App\Models;

use App\Enums\RequirementEstimationStatus;
use Database\Factories\ProjectRequirementEstimationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_requirement_id',
    'version',
    'status',
    'created_by_user_id',
    'submitted_at',
    'submitted_to_user_id',
    'submission_notes',
    'reviewed_by_user_id',
    'reviewed_at',
    'review_notes',
    'transferred_at',
    'transferred_by_user_id',
    'superseded_by_estimation_id',
])]
class ProjectRequirementEstimation extends Model
{
    /** @use HasFactory<ProjectRequirementEstimationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequirementEstimationStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'transferred_at' => 'datetime',
        ];
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ProjectRequirement::class, 'project_requirement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function submittedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_to_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by_user_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_estimation_id');
    }

    /**
     * @return HasMany<ProjectRequirementEstimationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProjectRequirementEstimationItem::class);
    }
}
