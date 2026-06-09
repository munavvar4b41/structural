<?php

namespace App\Models;

use App\Enums\JobApplicationStatus;
use Database\Factories\JobApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'job_posting_id',
    'status',
    'candidate_name',
    'candidate_email',
    'candidate_phone',
    'linkedin_url',
    'portfolio_url',
    'cover_letter',
    'skills',
    'years_of_experience',
    'salary_expectation',
    'preferred_location',
    'resume_path',
    'resume_original_name',
    'resume_mime',
    'reviewed_by_user_id',
    'reviewed_at',
    'rejection_reason',
    'applied_at',
])]
class JobApplication extends Model
{
    /** @use HasFactory<JobApplicationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobApplicationStatus::class,
            'years_of_experience' => 'integer',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
