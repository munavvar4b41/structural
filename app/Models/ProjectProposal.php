<?php

namespace App\Models;

use App\Enums\ProjectProposalStatus;
use Database\Factories\ProjectProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'project_requirement_id',
    'transferred_project_requirement_id',
    'created_by_user_id',
    'title',
    'description',
    'status',
    'submitted_at',
    'reviewed_at',
    'reviewed_by_user_id',
    'review_notes',
    'rejection_reason',
    'reopened_at',
    'reopened_by_user_id',
])]
class ProjectProposal extends Model
{
    /** @use HasFactory<ProjectProposalFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectProposalStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function linkedRequirement(): BelongsTo
    {
        return $this->belongsTo(ProjectRequirement::class, 'project_requirement_id');
    }

    public function transferredRequirement(): BelongsTo
    {
        return $this->belongsTo(ProjectRequirement::class, 'transferred_project_requirement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }

    /**
     * @return HasMany<int, ProjectProposalMessage>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ProjectProposalMessage::class);
    }
}
