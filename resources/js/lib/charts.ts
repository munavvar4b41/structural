import type { ApexOptions } from 'apexcharts';

export function colorForProjectId(projectId: number): string {
    const hue = (projectId * 137.508) % 360;

    return `hsl(${hue} 62% 52%)`;
}

export function colorsForProjectIds(projectIds: number[]): string[] {
    return projectIds.map((id) => colorForProjectId(id));
}

function getCssVariable(name: string, fallback: string): string {
    if (typeof document === 'undefined') {
        return fallback;
    }

    const value = getComputedStyle(document.documentElement)
        .getPropertyValue(name)
        .trim();

    if (! value) {
        return fallback;
    }

    if (value.startsWith('hsl(')) {
        return value;
    }

    return `hsl(${value})`;
}

export function getChartColors(): string[] {
    const fallbacks = ['#e07a52', '#3d9e72', '#9b6fc4', '#e89a2e', '#d45a7a'];

    if (typeof document === 'undefined') {
        return fallbacks;
    }

    return [1, 2, 3, 4, 5].map((i, index) => {
        const value = getComputedStyle(document.documentElement)
            .getPropertyValue(`--chart-${i}`)
            .trim();

        return value ? `hsl(${value})` : fallbacks[index];
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
    const muted = getCssVariable(
        '--muted-foreground',
        dark ? 'hsl(28 10% 62%)' : 'hsl(20 10% 45%)',
    );
    const grid = getCssVariable(
        '--border',
        dark ? 'hsl(22 8% 22%)' : 'hsl(28 20% 88%)',
    );
    const fore = getCssVariable(
        '--foreground',
        dark ? 'hsl(30 20% 96%)' : 'hsl(20 18% 14%)',
    );

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
