import { router, usePage } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    Activity01Icon,
    ArrowDownRight01Icon,
    ArrowUpRight01Icon,
    Clock01Icon,
    InboxIcon,
} from '@hugeicons/core-free-icons';
import { useCallback, useEffect, useMemo, useState, type ReactElement, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Label,
    Pie,
    PieChart,
    Sector,
    XAxis,
    YAxis,
} from 'recharts';
import type { PieSectorShapeProps } from 'recharts/types/polar/Pie';

import LanguageSwitcher from '@/components/LanguageSwitcher';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    type ChartConfig,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import DashboardLayout from '@/layouts/DashboardLayout';
import { cn } from '@/lib/utils';

type DashboardMetrics = {
    open: number;
    assignedToMe: number;
    unassigned: number;
    overdue: number;
    trend: Record<MetricKey, MetricTrend>;
    statusComparison: StatusComparison;
    channelDistribution: ChannelDistribution;
    recentActivity: ActivityItem[];
    generatedAt: string;
};

type MetricKey = 'open' | 'assignedToMe' | 'unassigned' | 'overdue';

type MetricTrend = {
    previous: number;
    change: number;
    percent: number | null;
    direction: 'up' | 'down' | 'flat' | 'new';
};

type StatusComparison = {
    rangeStart: string;
    rangeEnd: string;
    openTotal: number;
    solvedTotal: number;
    months: Array<{
        month: string;
        label: string;
        open: number;
        solved: number;
    }>;
};

type ChannelDistribution = {
    rangeStart: string;
    rangeEnd: string;
    total: number;
    channels: Array<{
        key: string;
        label: string;
        count: number;
        percent: number;
    }>;
};

type ActivityItem = {
    id: number;
    thread_id: number;
    event_id: number;
    event: string | null;
    ticket_id: number | null;
    ticket_number: string | null;
    ticket_subject: string | null;
    username: string | null;
    timestamp: string | null;
};

type MetricCard = {
    label: string;
    value: number;
    helper: string;
    trend: MetricTrend;
};

type Translation = ReturnType<typeof useTranslation>['t'];

interface DashboardProps {
    metrics: DashboardMetrics;
    range?: string;
}

function numberFormat(value: number, locale: string): string {
    return new Intl.NumberFormat(locale).format(value);
}

function percent(part: number, total: number, locale: string): string {
    if (total <= 0) {
        return new Intl.NumberFormat(locale, { style: 'percent', maximumFractionDigits: 0 }).format(0);
    }

    return new Intl.NumberFormat(locale, { style: 'percent', maximumFractionDigits: 0 }).format(part / total);
}

function trendPercent(value: number, locale: string): string {
    return new Intl.NumberFormat(locale, { style: 'percent', maximumFractionDigits: 1 }).format(Math.abs(value) / 100);
}

function formatMonth(value: string, locale: string): string {
    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(date);
}

function getTrendLabel(trend: MetricTrend, t: Translation, locale: string): string {
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

function getTrendClass(direction: MetricTrend['direction']): string {
    if (direction === 'up') {
        return 'bg-[#F0FDF4] text-[#16A34A]';
    }

    if (direction === 'down') {
        return 'bg-[#FEF2F2] text-[#DC2626]';
    }

    if (direction === 'new') {
        return 'bg-[#F0FDF4] text-[#16A34A]';
    }

    return 'bg-[#F8FAFC] text-[#64748B]';
}

function getTrendIcon(trend: MetricTrend) {
    if (trend.direction === 'up' || trend.direction === 'new') {
        return ArrowUpRight01Icon;
    }

    if (trend.direction === 'down') {
        return ArrowDownRight01Icon;
    }

    return null;
}

function formatDashboardDateTime(value: string | null | undefined, locale: string): string | null {
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

function formatDashboardRelative(value: string | null | undefined, locale: string): string | null {
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

function getMetricCards(metrics: DashboardMetrics, t: Translation, locale: string): MetricCard[] {
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

function getStatCardBorderClass(index: number): string {
    return cn(
        index > 0 && 'border-t border-[#E2E8F0] md:border-t-0',
        index % 2 === 1 && 'md:border-l md:border-[#E2E8F0] xl:border-l',
        index >= 2 && 'xl:border-l xl:border-t-0',
    );
}

function StatCard({ label, value, helper, trend, locale }: MetricCard & { locale: string }) {
    const { t } = useTranslation();
    const trendIcon = getTrendIcon(trend);

    return (
        <Card className="rounded-none border-0 py-0 shadow-none ring-0">
            <CardContent className="flex min-h-[132px] flex-col px-7 py-6">
                <div className="flex items-center justify-between gap-3">
                    <div className="font-body text-[10px] font-medium uppercase tracking-[0.1em] text-[#94A3B8]">{label}</div>
                </div>
                <div className="mt-3 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                    <div className="font-display text-[30px] font-medium leading-none tracking-tight text-[#0F172A]">
                        {numberFormat(value, locale)}
                    </div>
                    <div className={cn('inline-flex items-center gap-1 rounded-[4px] px-1.5 py-1 font-mono text-[10px] font-medium leading-none tracking-[0.05em]', getTrendClass(trend.direction))}>
                        {getTrendLabel(trend, t, locale)}
                        {trendIcon && <HugeiconsIcon icon={trendIcon} size={10} />}
                    </div>
                </div>
                <p className="mt-auto pt-2 text-xs text-[#94A3B8]" title={helper}>{t('dashboard.metrics.trend.compare_to_last_month')}</p>
            </CardContent>
        </Card>
    );
}

function SectionFrame({ children, className }: { children: ReactNode; className?: string }) {
    return <Card className={cn('overflow-hidden rounded-none border-0 py-0 shadow-none ring-0', className)}>{children}</Card>;
}

function WorkloadChart({ metrics, locale, t }: { metrics: DashboardMetrics; locale: string; t: Translation }) {
    const comparison = metrics.statusComparison;
    const rangeStart = formatMonth(comparison.rangeStart, locale);
    const rangeEnd = formatMonth(comparison.rangeEnd, locale);
    const data = comparison.months.map((item) => ({
        ...item,
        label: new Intl.DateTimeFormat(locale, { month: 'short' }).format(new Date(`${item.month}T00:00:00`)),
    }));
    const workloadChartConfig = {
        open: { label: t('dashboard.ticket_status_chart.open'), color: '#C4A5F3' },
        solved: { label: t('dashboard.ticket_status_chart.solved'), color: '#5B619D' },
    } satisfies ChartConfig;

    return (
        <SectionFrame className="xl:col-span-2 xl:rounded-r-none xl:border-r-0">
            <CardHeader className="px-7 pt-7">
                <CardTitle className="font-body text-sm font-medium text-[#0F172A]">{t('dashboard.ticket_status_chart.title')}</CardTitle>
                <CardDescription className="mt-1 text-xs text-[#94A3B8]">
                    {t('dashboard.ticket_status_chart.description', { rangeStart, rangeEnd })}
                </CardDescription>
            </CardHeader>
            <CardContent className="px-7 pb-7">
                <ChartContainer config={workloadChartConfig} className="h-[310px] min-h-0 w-full aspect-auto!">
                    <BarChart accessibilityLayer data={data} margin={{ top: 16, right: 8, left: 0, bottom: 8 }}>
                        <CartesianGrid vertical={false} strokeDasharray="3 5" stroke="#E2E8F0" />
                        <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={10} />
                        <YAxis tickLine={false} axisLine={false} tickMargin={12} allowDecimals={false} />
                        <ChartTooltip
                            content={(
                                <ChartTooltipContent
                                    labelFormatter={(_value, payload) => {
                                        const month = payload?.[0]?.payload?.month;

                                        return month ? formatMonth(month, locale) : null;
                                    }}
                                    formatter={(value) => numberFormat(Number(value), locale)}
                                />
                            )}
                        />
                        <ChartLegend content={<ChartLegendContent />} />
                        <Bar dataKey="open" stackId="tickets" fill="var(--color-open)" radius={[0, 0, 3, 3]} maxBarSize={34} />
                        <Bar dataKey="solved" stackId="tickets" fill="var(--color-solved)" radius={[3, 3, 0, 0]} maxBarSize={34} />
                    </BarChart>
                </ChartContainer>
                <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-[#94A3B8]">
                    <span>{t('dashboard.ticket_status_chart.open_total', { count: numberFormat(comparison.openTotal, locale) })}</span>
                    <span>{t('dashboard.ticket_status_chart.solved_total', { count: numberFormat(comparison.solvedTotal, locale) })}</span>
                </div>
            </CardContent>
        </SectionFrame>
    );
}

function AssignmentChart({ metrics, locale, t }: { metrics: DashboardMetrics; locale: string; t: Translation }) {
    const assignedElsewhere = Math.max(metrics.open - metrics.assignedToMe - metrics.unassigned, 0);
    const [activeSegment, setActiveSegment] = useState('assignedToMe');
    const assignmentChartConfig = {
        assignedToMe: { label: t('dashboard.ownership.assigned_to_me'), color: '#C4A5F3' },
        assignedElsewhere: { label: t('dashboard.ownership.assigned_elsewhere'), color: '#5B619D' },
        unassigned: { label: t('dashboard.ownership.unassigned'), color: '#22C55E' },
    } satisfies ChartConfig;
    const data = useMemo(() => [
        { key: 'assignedToMe', name: t('dashboard.ownership.assigned_to_me'), value: metrics.assignedToMe, fill: '#C4A5F3' },
        { key: 'assignedElsewhere', name: t('dashboard.ownership.assigned_elsewhere'), value: assignedElsewhere, fill: '#5B619D' },
        { key: 'unassigned', name: t('dashboard.ownership.unassigned'), value: metrics.unassigned, fill: '#22C55E' },
    ].filter((item) => item.value > 0), [assignedElsewhere, metrics.assignedToMe, metrics.unassigned, t]);
    const activeIndex = useMemo(() => data.findIndex((item) => item.key === activeSegment), [activeSegment, data]);
    const activeItem = activeIndex >= 0 ? data[activeIndex] : data[0];
    const resolvedActiveSegment = activeItem?.key ?? '';
    const renderActiveShape = useCallback(({ index, outerRadius = 0, ...props }: PieSectorShapeProps) => {
        if (index === activeIndex) {
            return (
                <g>
                    <Sector {...props} outerRadius={outerRadius + 8} />
                    <Sector {...props} innerRadius={outerRadius + 11} outerRadius={outerRadius + 20} />
                </g>
            );
        }

        return <Sector {...props} outerRadius={outerRadius} />;
    }, [activeIndex]);

    useEffect(() => {
        if (data.length > 0 && activeIndex < 0) {
            setActiveSegment(data[0].key);
        }
    }, [activeIndex, data]);

    return (
        <SectionFrame className="border-t border-[#E2E8F0] xl:rounded-l-none xl:border-l xl:border-t-0 xl:border-[#E2E8F0]">
            <CardHeader className="flex flex-row items-start justify-between gap-4 px-7 pt-7 pb-0">
                <div>
                    <CardTitle className="font-body text-sm font-medium text-[#0F172A]">{t('dashboard.ownership.title')}</CardTitle>
                    <CardDescription className="mt-1 text-xs text-[#94A3B8]">
                        {t('dashboard.ownership.description')}
                    </CardDescription>
                </div>
                {data.length > 0 && (
                    <Select value={resolvedActiveSegment} onValueChange={(value) => value && setActiveSegment(value)}>
                        <SelectTrigger
                            size="sm"
                            className="h-7 w-[150px] rounded-[4px] border-[#E2E8F0] bg-white px-2.5 text-xs text-[#64748B]"
                            aria-label={t('dashboard.ownership.select_segment')}
                        >
                            {activeItem ? (
                                <span className="flex min-w-0 items-center gap-2">
                                    <span className="h-2.5 w-2.5 shrink-0 rounded-[3px]" style={{ backgroundColor: activeItem.fill }} />
                                    <span className="truncate">{activeItem.name}</span>
                                </span>
                            ) : (
                                <span>{t('dashboard.ownership.select_segment')}</span>
                            )}
                        </SelectTrigger>
                        <SelectContent align="end" className="rounded-xl">
                            {data.map((item) => (
                                <SelectItem key={item.key} value={item.key} className="rounded-lg text-xs">
                                    <span className="flex items-center gap-2">
                                        <span className="h-2.5 w-2.5 shrink-0 rounded-[3px]" style={{ backgroundColor: item.fill }} />
                                        {item.name}
                                    </span>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
            </CardHeader>
            <CardContent className="flex min-h-[352px] flex-col items-center justify-center p-7">
                {data.length === 0 ? (
                    <p className="text-sm text-[#64748B]">{t('dashboard.ownership.empty')}</p>
                ) : (
                    <>
                        <ChartContainer id="dashboard-ownership" config={assignmentChartConfig} className="mx-auto aspect-square w-full max-w-[260px]">
                            <PieChart>
                                <ChartTooltip cursor={false} content={<ChartTooltipContent hideLabel formatter={(value) => numberFormat(Number(value), locale)} />} />
                                <Pie
                                    data={data}
                                    dataKey="value"
                                    nameKey="key"
                                    innerRadius={58}
                                    outerRadius={86}
                                    stroke="transparent"
                                    strokeWidth={5}
                                    paddingAngle={2}
                                    shape={renderActiveShape}
                                >
                                    <Label
                                        content={({ viewBox }) => {
                                            if (viewBox && 'cx' in viewBox && 'cy' in viewBox && activeItem) {
                                                return (
                                                    <text x={viewBox.cx} y={viewBox.cy} textAnchor="middle" dominantBaseline="middle">
                                                        <tspan x={viewBox.cx} y={viewBox.cy} className="fill-[#0F172A] font-display text-3xl font-medium">
                                                            {numberFormat(activeItem.value, locale)}
                                                        </tspan>
                                                        <tspan x={viewBox.cx} y={(viewBox.cy ?? 0) + 23} className="fill-[#94A3B8] text-xs">
                                                            {activeItem.name}
                                                        </tspan>
                                                    </text>
                                                );
                                            }

                                            return null;
                                        }}
                                    />
                                    {data.map((entry) => (
                                        <Cell key={entry.key} fill={entry.fill} />
                                    ))}
                                </Pie>
                            </PieChart>
                        </ChartContainer>
                        <div className="mt-6 grid w-full gap-3 text-[13px] text-[#64748B]">
                            {data.map((item) => (
                                <button
                                    key={item.key}
                                    type="button"
                                    onClick={() => setActiveSegment(item.key)}
                                    className={cn(
                                        'flex items-center justify-between gap-3 rounded-[4px] px-2 py-1.5 text-left transition',
                                        resolvedActiveSegment === item.key ? 'bg-[#F8FAFC] text-[#0F172A]' : 'hover:bg-[#F8FAFC]',
                                    )}
                                >
                                    <span className="flex items-center gap-2">
                                        <span className="h-2 w-2 rounded-sm" style={{ backgroundColor: item.fill }} />
                                        {item.name}
                                    </span>
                                    <span className="font-mono text-xs font-semibold text-[#0F172A]">{numberFormat(item.value, locale)}</span>
                                </button>
                            ))}
                        </div>
                    </>
                )}
            </CardContent>
        </SectionFrame>
    );
}

const CHANNEL_COLORS = ['#5B619D', '#C4A5F3', '#22C55E', '#E11D48', '#F59E0B', '#06B6D4', '#0F172A', '#84CC16', '#EC4899', '#64748B', '#8B5CF6', '#14B8A6'];

function ChannelRadialChart({ distribution, locale, t }: { distribution: ChannelDistribution; locale: string; t: Translation }) {
    const channels = distribution.channels.map((channel, index) => ({
        ...channel,
        color: CHANNEL_COLORS[index % CHANNEL_COLORS.length],
    }));
    const rangeStart = formatMonth(distribution.rangeStart, locale);
    const rangeEnd = formatMonth(distribution.rangeEnd, locale);
    const chartConfig = channels.reduce<ChartConfig>((config, channel) => {
        config[channel.key] = {
            label: channel.label,
            color: channel.color,
        };

        return config;
    }, {});

    return (
        <SectionFrame className="xl:rounded-r-none xl:border-r-0">
            <CardHeader className="px-7 pt-7 pb-0">
                <CardTitle className="font-body text-sm font-medium text-[#0F172A]">{t('dashboard.channels.title')}</CardTitle>
                <CardDescription className="mt-1 text-xs text-[#94A3B8]">
                    {t('dashboard.channels.description', { rangeStart, rangeEnd })}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex min-h-[360px] flex-col px-7 pb-7 pt-5">
                {distribution.total === 0 || channels.length === 0 ? (
                    <div className="flex flex-1 items-center justify-center rounded-[6px] border border-dashed border-[#E2E8F0] bg-[#F8FAFC] px-5 py-10 text-center text-sm text-[#64748B]">
                        {t('dashboard.channels.empty')}
                    </div>
                ) : (
                    <>
                        <div className="relative mx-auto h-[180px] w-full max-w-[300px]">
                            <ChartContainer config={chartConfig} className="h-full w-full aspect-auto!">
                                <PieChart>
                                    <ChartTooltip
                                        cursor={false}
                                        content={(
                                            <ChartTooltipContent
                                                hideLabel
                                                formatter={(value, name, item) => {
                                                    const channel = channels.find((entry) => entry.key === item.payload?.key || entry.key === name);

                                                    if (!channel) {
                                                        return numberFormat(Number(value), locale);
                                                    }

                                                    return (
                                                        <div className="flex min-w-32 flex-1 items-center justify-between gap-3 leading-none">
                                                            <span className="text-muted-foreground">{channel.label}</span>
                                                            <span className="font-mono font-medium text-foreground tabular-nums">
                                                                {numberFormat(channel.count, locale)}
                                                            </span>
                                                        </div>
                                                    );
                                                }}
                                            />
                                        )}
                                    />
                                    <Pie
                                        data={channels}
                                        dataKey="count"
                                        nameKey="key"
                                        startAngle={180}
                                        endAngle={0}
                                        innerRadius={72}
                                        outerRadius={108}
                                        cx="50%"
                                        cy="100%"
                                        paddingAngle={1}
                                        stroke="transparent"
                                    >
                                        {channels.map((channel) => (
                                            <Cell key={channel.key} fill={channel.color} />
                                        ))}
                                    </Pie>
                                </PieChart>
                            </ChartContainer>
                            <div className="pointer-events-none absolute inset-x-0 bottom-0 text-center">
                                <div className="font-display text-2xl font-medium text-[#0F172A]">{numberFormat(distribution.total, locale)}</div>
                                <div className="mt-0.5 text-xs text-[#94A3B8]">{t('dashboard.channels.total')}</div>
                            </div>
                        </div>
                        <div className="mt-3 flex flex-wrap justify-center gap-x-4 gap-y-2 text-xs">
                            {channels.map((channel) => (
                                <div key={channel.key} className="flex min-w-0 items-center gap-2 text-[#64748B]">
                                    <span className="h-2.5 w-2.5 shrink-0 rounded-[3px]" style={{ backgroundColor: channel.color }} />
                                    <span className="truncate">{channel.label}</span>
                                </div>
                            ))}
                        </div>
                    </>
                )}
            </CardContent>
        </SectionFrame>
    );
}

function getActivityDotClass(event: string | null): string {
    const value = event?.toLowerCase() ?? '';

    if (value.includes('closed') || value.includes('resolved')) {
        return 'bg-[#22C55E] ring-[#DCFCE7]';
    }

    if (value.includes('overdue') || value.includes('reopened')) {
        return 'bg-[#DC2626] ring-[#FEE2E2]';
    }

    if (value.includes('assigned') || value.includes('transferred')) {
        return 'bg-[#C4A5F3] ring-[#F3E8FF]';
    }

    return 'bg-[#5B619D] ring-[#E0E7FF]';
}

function RecentActivity({ items, locale, t }: { items: ActivityItem[]; locale: string; t: Translation }) {
    return (
        <SectionFrame className="border-t border-[#E2E8F0] xl:rounded-l-none xl:border-l xl:border-t-0 xl:border-[#E2E8F0]">
            <CardHeader className="flex flex-row items-start justify-between gap-4 px-7 pt-7 pb-0">
                <div>
                    <CardTitle className="font-body text-sm font-medium text-[#0F172A]">{t('dashboard.recent_activity.title')}</CardTitle>
                    <CardDescription className="mt-1 text-xs text-[#94A3B8]">{t('dashboard.recent_activity.description')}</CardDescription>
                </div>
                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-[5px] bg-[#F1F5F9] text-[#5B619D]">
                    <HugeiconsIcon icon={Activity01Icon} size={17} />
                </div>
            </CardHeader>
            <CardContent className="flex flex-col px-7 pb-7 pt-5">
                {items.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-[6px] border border-dashed border-[#E2E8F0] bg-[#F8FAFC] px-5 py-12 text-center">
                        <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-[#F1F5F9] text-[#94A3B8]">
                            <HugeiconsIcon icon={InboxIcon} size={20} />
                        </div>
                        <p className="text-sm font-medium text-[#64748B]">{t('dashboard.recent_activity.empty')}</p>
                    </div>
                ) : (
                    <div className="relative">
                        <div className="absolute bottom-6 left-[1px] top-6 w-[2px] bg-[#F1F5F9]" aria-hidden />
                        <div className="grid gap-3">
                            {items.map((item) => {
                                const title = item.ticket_number ? `#${item.ticket_number}` : t('dashboard.recent_activity.thread', { id: item.thread_id });
                                const time = formatDashboardRelative(item.timestamp, locale) ?? formatDashboardDateTime(item.timestamp, locale) ?? t('dashboard.recent_activity.unknown_time');
                                const event = item.event ?? t('dashboard.recent_activity.ticket_activity');

                                const dotClass = getActivityDotClass(item.event);
                                const borderColor = dotClass.split(' ')[0].replace('bg-', 'border-');
                                const textColor = dotClass.split(' ')[0].replace('bg-', 'text-');
                                const lightBgColor = dotClass.split(' ')[1].replace('ring-', 'bg-');

                                return (
                                    <button
                                        key={item.id}
                                        type="button"
                                        onClick={() => item.ticket_id && router.get(`/scp/tickets/${item.ticket_id}`)}
                                        disabled={!item.ticket_id}
                                        className="group relative flex w-full text-left transition disabled:cursor-default"
                                    >
                                        <div className={cn('relative z-10 w-full min-w-0 rounded-[6px] border border-[#F1F5F9] bg-white p-4 shadow-[0_1px_2px_0_rgba(15,23,42,0.02)] transition group-hover:border-[#E2E8F0] group-hover:bg-[#F8FAFC]', 'border-l-[4px]', borderColor)}>
                                            <div className="flex flex-wrap items-center justify-between gap-2 mb-2">
                                                <div className="flex min-w-0 items-center gap-2">
                                                    <span className="rounded-[4px] bg-[#F1F5F9] px-2 py-0.5 font-mono text-[11px] font-medium text-[#64748B] transition group-hover:bg-white">{title}</span>
                                                    <span className={cn('rounded-[4px] px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.05em]', lightBgColor, textColor)}>
                                                        {event}
                                                    </span>
                                                </div>
                                            </div>
                                            <p className="line-clamp-1 font-medium text-sm text-[#0F172A]">
                                                {item.ticket_subject ?? t('dashboard.recent_activity.untitled_ticket')}
                                            </p>
                                            <div className="mt-3 flex items-center gap-2 text-[11px] font-medium text-[#94A3B8]">
                                                {item.username && (
                                                    <span className="text-[#64748B]">
                                                        {t('dashboard.recent_activity.actor', { username: item.username })}
                                                    </span>
                                                )}
                                                {item.username && <span>&middot;</span>}
                                                <span className="flex shrink-0 items-center gap-1 font-mono">
                                                    <HugeiconsIcon icon={Clock01Icon} size={12} />
                                                    {time}
                                                </span>
                                            </div>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                        <div className="mt-4 text-center">
                            <button
                                type="button"
                                onClick={() => router.get('/scp/tickets')}
                                className="font-body text-xs font-medium text-[#94A3B8] transition hover:text-[#5B619D]"
                            >
                                View all activity &rarr;
                            </button>
                        </div>
                    </div>
                )}
            </CardContent>
        </SectionFrame>
    );
}



export function DashboardSkeleton() {
    return (
        <div className="overflow-hidden rounded-[8px] border border-[#E2E8F0] bg-white shadow-sm shadow-[#0F172A]/[0.03]">
            {/* Stat cards row */}
            <div className="border-b border-[#E2E8F0]">
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <div key={i} className={getStatCardBorderClass(i)}>
                            <div className="flex min-h-[132px] flex-col px-7 py-6">
                                <Skeleton className="h-3 w-24" />
                                <div className="mt-3 flex items-center gap-2">
                                    <Skeleton className="h-8 w-16" />
                                    <Skeleton className="h-5 w-12 rounded-[4px]" />
                                </div>
                                <Skeleton className="mt-auto pt-2 h-3 w-32" />
                            </div>
                        </div>
                    ))}
                </div>
            </div>
            {/* Charts row */}
            <div className="grid grid-cols-1 border-b border-[#E2E8F0] xl:grid-cols-3">
                <div className="px-7 py-7 xl:col-span-2">
                    <Skeleton className="h-4 w-40 mb-2" />
                    <Skeleton className="h-3 w-60 mb-6" />
                    <Skeleton className="h-[310px] w-full rounded-lg" />
                </div>
                <div className="border-t border-[#E2E8F0] px-7 py-7 xl:border-l xl:border-t-0">
                    <Skeleton className="h-4 w-32 mb-2" />
                    <Skeleton className="h-3 w-48 mb-6" />
                    <Skeleton className="mx-auto aspect-square w-[220px] rounded-full" />
                </div>
            </div>
            {/* Bottom row */}
            <div className="grid grid-cols-1 xl:grid-cols-2">
                <div className="px-7 py-7">
                    <Skeleton className="h-4 w-36 mb-2" />
                    <Skeleton className="h-3 w-52 mb-4" />
                    <Skeleton className="h-[180px] w-full rounded-lg" />
                </div>
                <div className="border-t border-[#E2E8F0] px-7 py-7 xl:border-l xl:border-t-0">
                    <Skeleton className="h-4 w-32 mb-2" />
                    <Skeleton className="h-3 w-48 mb-4" />
                    {Array.from({ length: 5 }).map((_, i) => (
                        <div key={i} className="mb-3 grid grid-cols-[22px_1fr] gap-3">
                            <Skeleton className="mt-3 h-[22px] w-[22px] rounded-full" />
                            <Skeleton className="h-[72px] rounded-[6px]" />
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

export default function Dashboard({ metrics, range }: DashboardProps) {
    const { t, i18n } = useTranslation();
    const locale = i18n.resolvedLanguage ?? i18n.language;
    const cards = getMetricCards(metrics, t, locale);

    return (
        <div className="overflow-hidden rounded-[8px] border border-[#E2E8F0] bg-white shadow-sm shadow-[#0F172A]/[0.03]">
            <div className="border-b border-[#E2E8F0]">
                <SectionFrame>
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4">
                        {cards.map((card, index) => (
                            <div key={card.label} className={getStatCardBorderClass(index)}>
                                <StatCard {...card} locale={locale} />
                            </div>
                        ))}
                    </div>
                </SectionFrame>
            </div>

            <div className="grid grid-cols-1 border-b border-[#E2E8F0] xl:grid-cols-3">
                <WorkloadChart metrics={metrics} locale={locale} t={t} />
                <AssignmentChart metrics={metrics} locale={locale} t={t} />
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-2">
                <ChannelRadialChart distribution={metrics.channelDistribution} locale={locale} t={t} />
                <RecentActivity items={metrics.recentActivity} locale={locale} t={t} />
            </div>
        </div>
    );
}

function DashboardHeaderTitle() {
    const { t } = useTranslation();

    return (
        <div className="flex items-baseline gap-2.5">
            <h1 className="font-display text-xl font-medium tracking-[-0.02em] text-[#0F172A]">{t('nav.dashboard')}</h1>
            <span className="text-[#94A3B8]">·</span>
            <span className="font-body text-[11px] font-medium uppercase tracking-[0.12em] text-[#94A3B8]">{t('dashboard.overview')}</span>
        </div>
    );
}

function DashboardHeaderActions() {
    const { t } = useTranslation();
    const { range } = usePage<{ range?: string }>().props;
    const [activeRange, setActiveRange] = useState(range ?? 'last_6_months');
    const rangeLabels: Record<string, string> = {
        last_7_days: t('dashboard.date_range.last_7_days'),
        last_30_days: t('dashboard.date_range.last_30_days'),
        last_3_months: t('dashboard.date_range.last_3_months'),
        last_6_months: t('dashboard.date_range.last_6_months'),
    };

    return (
        <>
            <Select
                defaultValue={activeRange}
                onValueChange={(value) => {
                    if (!value) return;
                    setActiveRange(value);
                    router.reload({ data: { range: value }, only: ['metrics'] });
                }}
            >
                <SelectTrigger className="h-7 w-[160px] rounded-[4px] border-[#E2E8F0] bg-white text-xs text-[#64748B]">
                    <span className="truncate">{rangeLabels[activeRange] ?? t('dashboard.date_range.label')}</span>
                </SelectTrigger>
                <SelectContent align="end" className="rounded-xl">
                    <SelectItem value="last_7_days" className="rounded-lg text-xs">{t('dashboard.date_range.last_7_days')}</SelectItem>
                    <SelectItem value="last_30_days" className="rounded-lg text-xs">{t('dashboard.date_range.last_30_days')}</SelectItem>
                    <SelectItem value="last_3_months" className="rounded-lg text-xs">{t('dashboard.date_range.last_3_months')}</SelectItem>
                    <SelectItem value="last_6_months" className="rounded-lg text-xs">{t('dashboard.date_range.last_6_months')}</SelectItem>
                </SelectContent>
            </Select>
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="h-7 rounded-[4px] border-[#E2E8F0] bg-white px-3 text-xs font-medium uppercase tracking-[0.12em] text-[#64748B] hover:border-[#C4A5F3] hover:bg-[#F8FAFC] hover:text-[#0F172A]"
                onClick={() => router.reload({ only: ['metrics'] })}
            >
                {t('actions.refresh')}
            </Button>
            <LanguageSwitcher />
        </>
    );
}

type DashboardPageComponent = typeof Dashboard & {
    layout?: (page: ReactElement) => ReactNode;
};

(Dashboard as DashboardPageComponent).layout = (page: ReactElement) => (
    <DashboardLayout
        activeNav="dashboard"
        contentClassName="w-full"
        headerLeft={<DashboardHeaderTitle />}
        headerActions={<DashboardHeaderActions />}
    >
        {page}
    </DashboardLayout>
);
