<?php

namespace App\Models;

use Database\Factories\ProjectRequirementEstimationItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_requirement_estimation_id',
    'parent_estimation_item_id',
    'title',
    'description',
    'estimated_minutes',
    'sort_order',
    'transferred_project_task_id',
])]
class ProjectRequirementEstimationItem extends Model
{
    /** @use HasFactory<ProjectRequirementEstimationItemFactory> */
    use HasFactory;

    public function estimation(): BelongsTo
    {
        return $this->belongsTo(ProjectRequirementEstimation::class, 'project_requirement_estimation_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_estimation_item_id');
    }

    /**
     * @return HasMany<ProjectRequirementEstimationItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_estimation_item_id');
    }

    public function transferredTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'transferred_project_task_id');
    }

    public function projectTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'id', 'project_requirement_estimation_item_id');
    }
}
