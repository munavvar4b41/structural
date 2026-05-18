import type { ApexOptions } from 'apexcharts';

export function colorForProjectId(projectId: number): string {
    const hue = (projectId * 137.508) % 360;

    return `hsl(${hue} 62% 52%)`;
}

export function colorsForProjectIds(projectIds: number[]): string[] {
    return projectIds.map((id) => colorForProjectId(id));
}

export function getChartColors(): string[] {
    if (typeof document === 'undefined') {
        return ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ec4899'];
    }

    const styles = getComputedStyle(document.documentElement);

    return [1, 2, 3, 4, 5].map((i) => {
        const value = styles.getPropertyValue(`--chart-${i}`).trim();

        return value ? `hsl(${value})` : '#3b82f6';
    });
}

export function isDarkMode(): boolean {
    if (typeof document === 'undefined') {
        return false;
    }

    return document.documentElement.classList.contains('dark');
}

export function buildApexTheme(dark = isDarkMode()): ApexOptions {
    const colors = getChartColors();
    const muted = dark ? 'hsl(240 5% 64%)' : 'hsl(240 4% 46%)';
    const grid = dark ? 'hsl(240 4% 20%)' : 'hsl(240 6% 90%)';
    const fore = dark ? 'hsl(0 0% 98%)' : 'hsl(240 6% 10%)';

    return {
        chart: {
            fontFamily: 'inherit',
            foreColor: fore,
            toolbar: { show: false },
            animations: {
                enabled: true,
                speed: 600,
            },
            background: 'transparent',
        },
        colors,
        grid: {
            borderColor: grid,
            strokeDashArray: 4,
            xaxis: { lines: { show: false } },
        },
        dataLabels: { enabled: false },
        stroke: {
            curve: 'smooth',
            width: 2.5,
        },
        legend: {
            fontSize: '13px',
            fontWeight: 500,
            labels: { colors: muted },
            markers: { size: 6 },
        },
        tooltip: {
            theme: dark ? 'dark' : 'light',
            style: { fontSize: '13px' },
        },
        xaxis: {
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { style: { colors: muted, fontSize: '12px' } },
        },
        yaxis: {
            labels: { style: { colors: muted, fontSize: '12px' } },
        },
        plotOptions: {
            bar: {
                borderRadius: 8,
                columnWidth: '55%',
            },
        },
    };
}

export function mergeApexOptions(
    base: ApexOptions,
    override: ApexOptions,
): ApexOptions {
    return {
        ...base,
        ...override,
        chart: { ...base.chart, ...override.chart },
        plotOptions: { ...base.plotOptions, ...override.plotOptions },
    };
}
