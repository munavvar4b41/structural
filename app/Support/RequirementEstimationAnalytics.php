<?php

namespace App\Support;

use App\Models\ProjectRequirementEstimationItem;
use Illuminate\Database\Eloquent\Collection;

final class RequirementEstimationAnalytics
{
    private const int MINUTES_PER_WORK_DAY = 480;

    /**
     * @param  Collection<int, ProjectRequirementEstimationItem>  $items
     * @return array<string, int|float|string|null>
     */
    public static function fromItems(Collection $items): array
    {
        $totalMinutes = RequirementEstimationTotals::totalMinutesFromItems($items);
        $totalLines = $items->count();
        $rootModules = $items->whereNull('parent_estimation_item_id')->count();
        $linesWithEstimate = $items->filter(
            static fn (ProjectRequirementEstimationItem $item): bool => $item->estimated_minutes !== null && $item->estimated_minutes >= 1,
        )->count();

        $totalHours = $totalMinutes > 0 ? round($totalMinutes / 60, 1) : 0.0;
        $totalDays = $totalMinutes > 0 ? round($totalMinutes / self::MINUTES_PER_WORK_DAY, 2) : 0.0;

        return [
            'total_lines' => $totalLines,
            'root_modules_count' => $rootModules,
            'subtask_count' => max(0, $totalLines - $rootModules),
            'lines_with_estimate' => $linesWithEstimate,
            'total_minutes' => $totalMinutes,
            'total_hours' => $totalHours,
            'total_days' => $totalDays,
            'formatted_minutes' => self::formatMinutes($totalMinutes),
            'formatted_hours' => self::formatHours($totalHours),
            'formatted_days' => self::formatDays($totalDays),
        ];
    }

    private static function formatMinutes(int $minutes): string
    {
        if ($minutes < 1) {
            return '—';
        }

        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        if ($hours <= 0) {
            return $remainder.'m';
        }

        if ($remainder === 0) {
            return $hours.'h';
        }

        return $hours.'h '.$remainder.'m';
    }

    private static function formatHours(float $hours): string
    {
        if ($hours <= 0) {
            return '—';
        }

        return rtrim(rtrim(number_format($hours, 1), '0'), '.').'h';
    }

    private static function formatDays(float $days): string
    {
        if ($days <= 0) {
            return '—';
        }

        $value = rtrim(rtrim(number_format($days, 2), '0'), '.');

        return $value.' '.($value === '1' ? 'day' : 'days');
    }
}
