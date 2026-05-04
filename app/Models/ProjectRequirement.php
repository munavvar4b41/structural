<?php

namespace App\Models;

use Database\Factories\ProjectRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
