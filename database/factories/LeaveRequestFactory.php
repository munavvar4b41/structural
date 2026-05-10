<?php

namespace Database\Factories;

use App\Enums\LeaveHalfDayPeriod;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveType;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => LeaveType::FullDay,
            'date' => now()->addDays(1)->toDateString(),
            'half_day_period' => null,
            'break_starts_at' => null,
            'break_ends_at' => null,
            'status' => LeaveRequestStatus::Pending,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'reason' => fake()->optional()->sentence(),
        ];
    }

    public function halfDay(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => LeaveType::HalfDay,
            'half_day_period' => LeaveHalfDayPeriod::FirstHalf,
        ]);
    }

    public function oneHourBreak(): static
    {
        $start = now()->addDay()->setTime(12, 0, 0);

        return $this->state(fn (array $attributes): array => [
            'type' => LeaveType::Break,
            'break_starts_at' => $start,
            'break_ends_at' => $start->copy()->addHour(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LeaveRequestStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }
}
