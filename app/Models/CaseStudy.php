<?php

namespace App\Models;

use App\Enums\WorkloadPeriod;
use Database\Factories\CaseStudyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'project_task_id',
    'created_by_user_id',
    'title',
    'summary',
    'client_issue',
    'proposed_solution',
    'resolution',
    'workload_reduction_details',
    'workload_hours_saved',
    'workload_percentage_reduction',
    'workload_period',
])]
class CaseStudy extends Model
{
    /** @use HasFactory<CaseStudyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'workload_hours_saved' => 'decimal:2',
            'workload_percentage_reduction' => 'decimal:2',
            'workload_period' => WorkloadPeriod::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<CaseStudyAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(CaseStudyAttachment::class)->orderBy('sort_order');
    }
}
