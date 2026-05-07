<?php

namespace App\Models;

use Database\Factories\ProjectRequirementMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_requirement_id',
    'user_id',
    'body',
])]
class ProjectRequirementMessage extends Model
{
    /** @use HasFactory<ProjectRequirementMessageFactory> */
    use HasFactory;

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ProjectRequirement::class, 'project_requirement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
