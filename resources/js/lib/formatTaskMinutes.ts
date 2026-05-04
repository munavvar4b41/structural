/**
 * Format stored task estimate (minutes) for display.
 */
export function formatTaskMinutes(minutes: number | null | undefined): string {
    if (minutes === null || minutes === undefined) {
        return '—';
    }

    if (minutes < 1) {
        return '—';
    }

    const h = Math.floor(minutes / 60);
    const m = minutes % 60;

    if (h <= 0) {
        return `${m}m`;
    }

    if (m === 0) {
        return `${h}h`;
    }

    return `${h}h ${m}m`;
}
