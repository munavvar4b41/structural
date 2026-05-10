<?php

namespace App\Models;

use App\Enums\LeaveHalfDayPeriod;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveType;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'type',
    'date',
    'half_day_period',
    'break_starts_at',
    'break_ends_at',
    'status',
    'reviewed_by_user_id',
    'reviewed_at',
    'reason',
])]
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LeaveType::class,
            'date' => 'date',
            'half_day_period' => LeaveHalfDayPeriod::class,
            'break_starts_at' => 'datetime',
            'break_ends_at' => 'datetime',
            'status' => LeaveRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
