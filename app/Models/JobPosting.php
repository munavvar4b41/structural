<?php

namespace App\Models;

use App\Enums\JobEmploymentType;
use App\Enums\JobPostingStatus;
use Database\Factories\JobPostingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'title',
    'team_id',
    'location',
    'employment_type',
    'description',
    'requirements',
    'status',
    'published_at',
    'closes_at',
    'created_by_user_id',
])]
class JobPosting extends Model
{
    /** @use HasFactory<JobPostingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employment_type' => JobEmploymentType::class,
            'status' => JobPostingStatus::class,
            'published_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<JobPosting>  $query
     * @return Builder<JobPosting>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', JobPostingStatus::Open)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('closes_at')
                    ->orWhere('closes_at', '>=', now());
            });
    }

    public function isPubliclyVisible(): bool
    {
        if ($this->status !== JobPostingStatus::Open) {
            return false;
        }

        if ($this->published_at !== null && $this->published_at->isFuture()) {
            return false;
        }

        if ($this->closes_at !== null && $this->closes_at->isPast()) {
            return false;
        }

        return true;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }
}
