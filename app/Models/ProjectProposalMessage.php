<?php

namespace App\Models;

use Database\Factories\ProjectProposalMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_proposal_id',
    'user_id',
    'body',
])]
class ProjectProposalMessage extends Model
{
    /** @use HasFactory<ProjectProposalMessageFactory> */
    use HasFactory;

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ProjectProposal::class, 'project_proposal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
