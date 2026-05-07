/**
 * Format a duration in seconds for human-readable display.
 *
 * - >= 1 hour: "1h 23m" (or "1h 23m 45s" with seconds)
 * - < 1 hour:  "23m" (or "23m 45s" with seconds)
 * - < 1 minute: "45s"
 * - 0 / null / undefined: "0s"
 */
export function formatSeconds(
    seconds: number | null | undefined,
    options: { withSeconds?: boolean } = {},
): string {
    const total = seconds ?? 0;

    if (total <= 0) {
        return '0s';
    }

    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;

    const withSeconds = options.withSeconds ?? false;

    if (h > 0) {
        if (withSeconds) {
            return `${h}h ${m}m ${s}s`;
        }
        return m > 0 ? `${h}h ${m}m` : `${h}h`;
    }

    if (m > 0) {
        return withSeconds ? `${m}m ${s}s` : `${m}m`;
    }

    return `${s}s`;
}
