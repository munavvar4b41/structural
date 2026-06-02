<?php

namespace App\Support;

use App\Settings\CompanySettings;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Derives a closed time-entry window from a duration and company working hours.
 */
class WorkTimeEntryWindowResolver
{
    public function __construct(private readonly CompanySettings $companySettings) {}

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function resolve(int $durationMinutes, ?string $workDate = null): array
    {
        if ($durationMinutes < 1) {
            throw ValidationException::withMessages([
                'duration_minutes' => [__('Duration must be at least one minute.')],
            ]);
        }

        $timezone = config('app.timezone', 'UTC');
        $date = $workDate !== null && $workDate !== ''
            ? CarbonImmutable::parse($workDate, $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->startOfDay();

        $workStart = $this->timeOnDate($date, $this->companySettings->work_day_start_time);
        $workEnd = $this->timeOnDate($date, $this->companySettings->work_day_end_time);

        $windowMinutes = (int) round($workStart->diffInMinutes($workEnd));

        if ($durationMinutes > $windowMinutes) {
            throw ValidationException::withMessages([
                'duration_minutes' => [
                    __('Duration cannot exceed :minutes minutes within the configured working hours.', [
                        'minutes' => $windowMinutes,
                    ]),
                ],
            ]);
        }

        $now = CarbonImmutable::now($timezone);
        $isToday = $date->isSameDay($now);

        $endedAt = $isToday ? $now->min($workEnd) : $workEnd;
        $startedAt = $endedAt->subMinutes($durationMinutes);

        if ($startedAt->lessThan($workStart)) {
            throw ValidationException::withMessages([
                'duration_minutes' => [
                    __('Duration does not fit within today\'s remaining working hours. Use start and end times instead.'),
                ],
            ]);
        }

        return [
            'start' => $startedAt,
            'end' => $endedAt,
        ];
    }

    private function timeOnDate(CarbonImmutable $date, string $time): CarbonImmutable
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches) !== 1) {
            throw ValidationException::withMessages([
                'duration_minutes' => [__('Company working hours are not configured correctly.')],
            ]);
        }

        return $date->setTime((int) $matches[1], (int) $matches[2], 0);
    }
}
