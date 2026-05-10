<?php

namespace App\Models;

use Database\Factories\ProjectTaskReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_task_id',
    'reviewer_user_id',
    'review_notes',
    'task_rating',
    'assignee_rating',
    'creator_rating',
])]
class ProjectTaskReview extends Model
{
    /** @use HasFactory<ProjectTaskReviewFactory> */
    use HasFactory;

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
