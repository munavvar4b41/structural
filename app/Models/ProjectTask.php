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
    'project_requirement_estimation_item_id',
    'parent_project_task_id',
    'title',
    'description',
    'status',
    'assignee_user_id',
    'created_by_user_id',
    'estimated_minutes',
    'phase',
    'display_after_at',
    'notify_at',
    'notified_at',
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
            'display_after_at' => 'datetime',
            'notify_at' => 'datetime',
            'notified_at' => 'datetime',
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

    public function estimationItem(): BelongsTo
    {
        return $this->belongsTo(ProjectRequirementEstimationItem::class, 'project_requirement_estimation_item_id');
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

    /**
     * @return HasMany<ProjectTaskChecklistItem, $this>
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(ProjectTaskChecklistItem::class, 'project_task_id');
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

    /**
     * @return HasMany<CaseStudy, $this>
     */
    public function caseStudies(): HasMany
    {
        return $this->hasMany(CaseStudy::class, 'project_task_id');
    }
}
