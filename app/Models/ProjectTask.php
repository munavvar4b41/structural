<?php

namespace App\Models;

use App\Enums\ProjectTaskStatus;
use Database\Factories\ProjectTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'project_requirement_id',
    'parent_project_task_id',
    'title',
    'description',
    'status',
    'assignee_user_id',
    'created_by_user_id',
    'estimated_minutes',
    'completion_submitted_at',
    'completion_submitted_by_user_id',
])]
class ProjectTask extends Model
{
    /** @use HasFactory<ProjectTaskFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectTaskStatus::class,
            'completion_submitted_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ProjectRequirement::class, 'project_requirement_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'parent_project_task_id');
    }

    /**
     * @return HasMany<ProjectTask, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'parent_project_task_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<TaskTimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TaskTimeEntry::class, 'project_task_id');
    }

    public function completionSubmittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completion_submitted_by_user_id');
    }

    /**
     * @return HasMany<ProjectTaskReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProjectTaskReview::class, 'project_task_id');
    }
}
