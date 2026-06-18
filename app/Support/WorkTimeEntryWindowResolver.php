<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Derives a closed time-entry window from a duration (ending at the current time).
 */
class WorkTimeEntryWindowResolver
{
    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function resolve(int $durationMinutes): array
    {
        if ($durationMinutes < 1) {
            throw ValidationException::withMessages([
                'duration_minutes' => [__('Duration must be at least one minute.')],
            ]);
        }

        $timezone = config('app.timezone', 'UTC');
        $endedAt = CarbonImmutable::now($timezone);
        $startedAt = $endedAt->subMinutes($durationMinutes);

        return [
            'start' => $startedAt,
            'end' => $endedAt,
        ];
    }
}
