<?php

namespace App\Models;

use Database\Factories\ProjectPasswordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'created_by_user_id', 'label', 'username', 'url'])]
#[Hidden(['secret_nonce', 'secret_ciphertext', 'notes_nonce', 'notes_ciphertext'])]
class ProjectPassword extends Model
{
    /** @use HasFactory<ProjectPasswordFactory> */
    use HasFactory;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
