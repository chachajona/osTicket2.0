import { ArrowDownRight01Icon, ArrowUpRight01Icon } from '@hugeicons/core-free-icons';
import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

import type { DashboardMetrics, MetricCard, MetricTrend } from './types';

export function numberFormat(value: number, locale: string): string {
    return new Intl.NumberFormat(locale).format(value);
}

export function percent(part: number, total: number, locale: string): string {
    if (total <= 0) {
        return new Intl.NumberFormat(locale, { style: 'percent', maximumFractionDigits: 0 }).format(0);
    }

    return new Intl.NumberFormat(locale, { style: 'percent', maximumFractionDigits: 0 }).format(part / total);
}

export function trendPercent(value: number, locale: string): string {
    return new Intl.NumberFormat(locale, { style: 'percent', maximumFractionDigits: 1 }).format(Math.abs(value) / 100);
}

export function formatMonth(value: string, locale: string): string {
    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(date);
}

export function getTrendLabel(trend: MetricTrend, t: ReturnType<typeof useTranslation>['t'], locale: string): string {
    if (trend.direction === 'new') {
        return t('dashboard.metrics.trend.new');
    }

    if (trend.direction === 'flat') {
        return t('dashboard.metrics.trend.flat');
    }

    return t(`dashboard.metrics.trend.${trend.direction}`, {
        percent: trendPercent(trend.percent ?? 0, locale),
    });
}

export function getTrendClass(direction: MetricTrend['direction']): string {
    if (direction === 'up') {
        return 'bg-[#F0FDF4] text-[#16A34A]';
    }

    if (direction === 'down') {
        return 'bg-[#FEF2F2] text-[#DC2626]';
    }

    if (direction === 'new') {
        return 'bg-[#F0FDF4] text-[#16A34A]';
    }

    return 'bg-[#FAFAF8] text-[#71717A]';
}

export function getTrendIcon(trend: MetricTrend) {
    if (trend.direction === 'up' || trend.direction === 'new') {
        return ArrowUpRight01Icon;
    }

    if (trend.direction === 'down') {
        return ArrowDownRight01Icon;
    }

    return null;
}

export function formatDashboardDateTime(value: string | null | undefined, locale: string): string | null {
    if (!value) return null;

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;

    return new Intl.DateTimeFormat(locale, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
}

export function formatDashboardRelative(value: string | null | undefined, locale: string): string | null {
    if (!value) return null;

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;

    const diff = date.getTime() - Date.now();
    const abs = Math.abs(diff);
    const units: Array<{ unit: Intl.RelativeTimeFormatUnit; ms: number }> = [
        { unit: 'year', ms: 365 * 24 * 60 * 60 * 1000 },
        { unit: 'month', ms: 30 * 24 * 60 * 60 * 1000 },
        { unit: 'week', ms: 7 * 24 * 60 * 60 * 1000 },
        { unit: 'day', ms: 24 * 60 * 60 * 1000 },
        { unit: 'hour', ms: 60 * 60 * 1000 },
        { unit: 'minute', ms: 60 * 1000 },
    ];
    const formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

    for (const { unit, ms } of units) {
        if (abs >= ms) {
            return formatter.format(Math.round(diff / ms), unit);
        }
    }

    return formatter.format(Math.round(diff / 1000), 'second');
}

export function getMetricCards(metrics: DashboardMetrics, t: ReturnType<typeof useTranslation>['t'], locale: string): MetricCard[] {
    return [
        {
            label: t('dashboard.metrics.open_tickets.label'),
            value: metrics.open,
            helper: t('dashboard.metrics.open_tickets.helper'),
            trend: metrics.trend.open,
        },
        {
            label: t('dashboard.metrics.assigned_to_me.label'),
            value: metrics.assignedToMe,
            helper: t('dashboard.metrics.assigned_to_me.helper', { percent: percent(metrics.assignedToMe, metrics.open, locale) }),
            trend: metrics.trend.assignedToMe,
        },
        {
            label: t('dashboard.metrics.unassigned.label'),
            value: metrics.unassigned,
            helper: t('dashboard.metrics.unassigned.helper'),
            trend: metrics.trend.unassigned,
        },
        {
            label: t('dashboard.metrics.overdue.label'),
            value: metrics.overdue,
            helper: t('dashboard.metrics.overdue.helper', { percent: percent(metrics.overdue, metrics.open, locale) }),
            trend: metrics.trend.overdue,
        },
    ];
}

export function getStatCardBorderClass(index: number): string {
    return cn(
        index > 0 && 'border-t border-[#E2E0D8] md:border-t-0',
        index % 2 === 1 && 'md:border-l md:border-[#E2E0D8] xl:border-l',
        index >= 2 && 'xl:border-l xl:border-t-0',
    );
}
