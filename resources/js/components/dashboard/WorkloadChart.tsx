import {
    Bar,
    BarChart,
    CartesianGrid,
    XAxis,
    YAxis,
} from 'recharts';
import { useTranslation } from 'react-i18next';

import {
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

import { formatMonth, numberFormat } from './helpers';
import { SectionFrame } from './SectionFrame';
import type { DashboardMetrics } from './types';

export function WorkloadChart({ metrics, locale, t }: { metrics: DashboardMetrics; locale: string; t: ReturnType<typeof useTranslation>['t'] }) {
    const comparison = metrics.statusComparison;
    const rangeStart = formatMonth(comparison.rangeStart, locale);
    const rangeEnd = formatMonth(comparison.rangeEnd, locale);
    const data = comparison.months.map((item) => ({
        ...item,
        label: new Intl.DateTimeFormat(locale, { month: 'short' }).format(new Date(`${item.month}T00:00:00`)),
    }));
    const workloadChartConfig = {
        open: { label: t('dashboard.ticket_status_chart.open'), color: '#FB9A57' },
        solved: { label: t('dashboard.ticket_status_chart.solved'), color: '#6366F1' },
    } satisfies ChartConfig;

    return (
        <SectionFrame className="xl:col-span-2 xl:rounded-r-none xl:border-r-0">
            <CardHeader className="px-7 pt-7">
                <CardTitle className="font-body text-sm font-medium text-[#18181B]">{t('dashboard.ticket_status_chart.title')}</CardTitle>
                <CardDescription className="mt-1 text-xs text-[#A1A1AA]">
                    {t('dashboard.ticket_status_chart.description', { rangeStart, rangeEnd })}
                </CardDescription>
            </CardHeader>
            <CardContent className="px-7 pb-7">
                <ChartContainer config={workloadChartConfig} className="h-[310px] min-h-0 w-full aspect-auto!">
                    <BarChart accessibilityLayer data={data} margin={{ top: 16, right: 8, left: 0, bottom: 8 }}>
                        <CartesianGrid vertical={false} strokeDasharray="3 5" stroke="#E2E0D8" />
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
                <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-[#A1A1AA]">
                    <span>{t('dashboard.ticket_status_chart.open_total', { count: numberFormat(comparison.openTotal, locale) })}</span>
                    <span>{t('dashboard.ticket_status_chart.solved_total', { count: numberFormat(comparison.solvedTotal, locale) })}</span>
                </div>
            </CardContent>
        </SectionFrame>
    );
}
