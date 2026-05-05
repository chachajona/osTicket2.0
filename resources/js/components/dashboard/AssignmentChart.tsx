import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    Cell,
    Label,
    Pie,
    PieChart,
    Sector,
} from 'recharts';
import type { PieSectorShapeProps } from 'recharts/types/polar/Pie';

import {
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    type ChartConfig,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

import { numberFormat } from './helpers';
import { SectionFrame } from './SectionFrame';
import type { DashboardMetrics } from './types';

export function AssignmentChart({ metrics, locale, t }: { metrics: DashboardMetrics; locale: string; t: ReturnType<typeof useTranslation>['t'] }) {
    const assignedElsewhere = Math.max(metrics.open - metrics.assignedToMe - metrics.unassigned, 0);
    const [activeSegment, setActiveSegment] = useState('assignedToMe');
    const assignmentChartConfig = {
        assignedToMe: { label: t('dashboard.ownership.assigned_to_me'), color: '#F97316' },
        assignedElsewhere: { label: t('dashboard.ownership.assigned_elsewhere'), color: '#6366F1' },
        unassigned: { label: t('dashboard.ownership.unassigned'), color: '#EC4899' },
    } satisfies ChartConfig;
    const data = useMemo(() => [
        { key: 'assignedToMe', name: t('dashboard.ownership.assigned_to_me'), value: metrics.assignedToMe, fill: '#F97316' },
        { key: 'assignedElsewhere', name: t('dashboard.ownership.assigned_elsewhere'), value: assignedElsewhere, fill: '#6366F1' },
        { key: 'unassigned', name: t('dashboard.ownership.unassigned'), value: metrics.unassigned, fill: '#EC4899' },
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
        <SectionFrame className="border-t border-[#E2E0D8] xl:rounded-l-none xl:border-l xl:border-t-0 xl:border-[#E2E0D8]">
            <CardHeader className="flex flex-row items-start justify-between gap-4 px-7 pt-7 pb-0">
                <div>
                    <CardTitle className="font-body text-sm font-medium text-[#18181B]">{t('dashboard.ownership.title')}</CardTitle>
                    <CardDescription className="mt-1 text-xs text-[#A1A1AA]">
                        {t('dashboard.ownership.description')}
                    </CardDescription>
                </div>
                {data.length > 0 && (
                    <Select value={resolvedActiveSegment} onValueChange={(value) => value && setActiveSegment(value)}>
                        <SelectTrigger
                            size="sm"
                            className="h-7 w-[150px] rounded-[4px] border-[#E2E0D8] bg-white px-2.5 text-xs text-[#71717A]"
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
                    <p className="text-sm text-[#71717A]">{t('dashboard.ownership.empty')}</p>
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
                                                        <tspan x={viewBox.cx} y={viewBox.cy} className="fill-[#18181B] font-display text-3xl font-medium">
                                                            {numberFormat(activeItem.value, locale)}
                                                        </tspan>
                                                        <tspan x={viewBox.cx} y={(viewBox.cy ?? 0) + 23} className="fill-[#A1A1AA] text-xs">
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
                        <div className="mt-6 grid w-full gap-3 text-[13px] text-[#71717A]">
                            {data.map((item) => (
                                <button
                                    key={item.key}
                                    type="button"
                                    onClick={() => setActiveSegment(item.key)}
                                    className={cn(
                                        'flex items-center justify-between gap-3 rounded-[4px] px-2 py-1.5 text-left transition',
                                        resolvedActiveSegment === item.key ? 'bg-[#FAFAF8] text-[#18181B]' : 'hover:bg-[#FAFAF8]',
                                    )}
                                >
                                    <span className="flex items-center gap-2">
                                        <span className="h-2 w-2 rounded-sm" style={{ backgroundColor: item.fill }} />
                                        {item.name}
                                    </span>
                                    <span className="font-mono text-xs font-semibold text-[#18181B]">{numberFormat(item.value, locale)}</span>
                                </button>
                            ))}
                        </div>
                    </>
                )}
            </CardContent>
        </SectionFrame>
    );
}
