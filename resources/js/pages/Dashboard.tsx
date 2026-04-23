import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    ArrowDown01Icon,
    ArrowUp01Icon,
} from '@hugeicons/core-free-icons';
import {
    Bar,
    BarChart,
    Label,
    CartesianGrid,
    Cell,
    Pie,
    PieChart,
    Sector,
    XAxis,
    YAxis,
} from 'recharts';
import type { PieSectorDataItem, PieSectorShapeProps } from 'recharts/types/polar/Pie';

import { MigrationBanner } from '@/components/auth/MigrationBanner';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    type ChartConfig,
    ChartLegend,
    ChartLegendContent,
    ChartStyle,
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
import { Separator } from '@/components/ui/separator';
import DashboardLayout from '@/layouts/DashboardLayout';
import { cn } from '@/lib/utils';

type StatCardItem = {
    label: string;
    value: string;
    suffix?: string;
    trend: string;
    positive: boolean;
};

type BarChartDatum = {
    day: string;
    created: number;
    solved: number;
    highlighted?: boolean;
};

const STAT_CARDS: StatCardItem[] = [
    { label: 'Created Tickets', value: '24,208', trend: '-5%', positive: false },
    { label: 'Unsolved Tickets', value: '4,564', trend: '+2%', positive: true },
    { label: 'Solved Tickets', value: '18,208', trend: '+8%', positive: true },
    { label: 'Average First Time Reply', value: '12:01', suffix: 'min', trend: '+8', positive: true },
];

const BAR_CHART_DATA: BarChartDatum[] = [
    { day: 'Dec 1', created: 4120, solved: 3100 },
    { day: 'Dec 2', created: 3450, solved: 2890 },
    { day: 'Dec 3', created: 3890, solved: 2950 },
    { day: 'Dec 4', created: 4300, solved: 3800, highlighted: true },
    { day: 'Dec 5', created: 3650, solved: 2400 },
    { day: 'Dec 6', created: 3520, solved: 2860 },
    { day: 'Dec 7', created: 3780, solved: 3010 },
];

const REPLY_TIME_DATA = [
    { name: '0-1 Hours', value: 81, fill: '#22C55E' },
    { name: '1-8 Hours', value: 9, fill: '#C4A5F3' },
    { name: '8-24 Hours', value: 4, fill: '#5B619D' },
    { name: '> 24 Hours', value: 4, fill: '#94A3B8' },
    { name: 'No Replies', value: 2, fill: '#F43F5E' },
] as const;

type ReplyTimeName = (typeof REPLY_TIME_DATA)[number]['name'];

const CHANNEL_DATA = [
    { name: 'Email', value: 940, fill: '#5B619D' },
    { name: 'Live Chat', value: 720, fill: '#C4A5F3' },
    { name: 'Contact Form', value: 610, fill: '#22C55E' },
    { name: 'Messenger', value: 420, fill: '#94A3B8' },
    { name: 'WhatsApp', value: 312, fill: '#CBD5E1' },
] as const;

const SATISFACTION_ROWS = [
    { icon: '👍', label: 'Positive', percent: '80%', helper: '72%', fill: '80%', tone: 'bg-emerald-500', iconTone: 'bg-emerald-50' },
    { icon: '✋', label: 'Neutral', percent: '15%', helper: '24%', fill: '15%', tone: 'bg-[#C4A5F3]', iconTone: 'bg-[#F3ECFF]' },
    { icon: '👎', label: 'Negative', percent: '5%', helper: '4%', fill: '5%', tone: 'bg-rose-500', iconTone: 'bg-rose-50' },
] as const;

const ticketChartConfig = {
    created: { label: 'Avg. Ticket Create', color: '#C4A5F3' },
    solved: { label: 'Avg. Ticket Solved', color: '#5B619D' },
} satisfies ChartConfig;

const DATE_RANGE_OPTIONS = [
    { value: 'dec-1-7', label: 'Dec 1 - 7' },
    { value: 'dec-8-14', label: 'Dec 8 - 14' },
    { value: 'dec-15-21', label: 'Dec 15 - 21' },
] as const;

const channelChartConfig = Object.fromEntries(
    CHANNEL_DATA.map(({ name, fill }) => [name, { label: name, color: fill }]),
) satisfies ChartConfig;

const replyTimeInteractiveChartConfig = {
    visitors: {
        label: 'Tickets',
    },
    ...Object.fromEntries(
        REPLY_TIME_DATA.map(({ name, fill }) => [name, { label: name, color: fill }]),
    ),
} satisfies ChartConfig;

function StatCard({ label, value, suffix, trend, positive }: StatCardItem) {
    return (
        <Card className="rounded-none border-0 py-0 shadow-none ring-0">
            <CardContent className="flex min-h-[150px] flex-col p-6 xl:p-8">
                <div className="font-body text-[13px] font-medium text-[#64748B]">{label}</div>
                <div className="mt-3 flex items-baseline gap-3">
                    <div className="font-display text-[30px] font-medium tracking-tight text-[#0F172A] xl:text-[34px]">{value}</div>
                    {suffix && <span className="font-body text-base font-medium text-[#0F172A]">{suffix}</span>}
                    <span className={cn(
                        'inline-flex items-center gap-1 rounded-sm px-1.5 py-0.5 text-[10px] font-semibold',
                        positive ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600',
                    )}>
                        {trend}
                        <HugeiconsIcon icon={positive ? ArrowUp01Icon : ArrowDown01Icon} size={12} />
                    </span>
                </div>
                <p className="mt-auto pt-3 text-xs text-[#94A3B8]">Compare to last month</p>
            </CardContent>
        </Card>
    );
}

function SectionFrame({ children, className }: { children: ReactNode; className?: string }) {
    return <Card className={cn('overflow-hidden rounded-[18px] border-[#E2E8F0] py-0 shadow-none ring-0', className)}>{children}</Card>;
}

function TicketChartFooter() {
    return (
        <CardFooter className="flex-col items-start gap-2 border-t border-[#E2E8F0] px-6 py-4 text-sm xl:px-8">
            <div className="flex items-center gap-2 leading-none font-medium text-[#0F172A]">
                Ticket creation remains ahead of resolutions this week
                <HugeiconsIcon icon={ArrowUp01Icon} size={14} />
            </div>
            <div className="leading-none text-[#94A3B8]">
                Showing created versus solved tickets for Dec 1 - 7
            </div>
        </CardFooter>
    );
}

function ReplyTimeInteractiveChart() {
    const chartId = 'reply-time-interactive';
    const [activeSegment, setActiveSegment] = React.useState<ReplyTimeName>(REPLY_TIME_DATA[0].name);

    const activeIndex = React.useMemo(
        () => REPLY_TIME_DATA.findIndex((item) => item.name === activeSegment),
        [activeSegment],
    );

    const activeItem = REPLY_TIME_DATA[activeIndex] ?? REPLY_TIME_DATA[0];

    const renderPieShape = React.useCallback(
        ({ index, outerRadius = 0, ...props }: PieSectorDataItem & PieSectorShapeProps) => {
            if (index === activeIndex) {
                return (
                    <g>
                        <Sector {...props} outerRadius={outerRadius + 10} />
                        <Sector {...props} outerRadius={outerRadius + 25} innerRadius={outerRadius + 12} />
                    </g>
                );
            }

            return <Sector {...props} outerRadius={outerRadius} />;
        },
        [activeIndex],
    );

    return (
        <Card data-chart={chartId} className="flex min-h-[430px] flex-col rounded-none border-0 py-0 shadow-none ring-0">
            <ChartStyle id={chartId} config={replyTimeInteractiveChartConfig} />
            <CardHeader className="px-6 pt-6 pb-0 xl:px-8 xl:pt-8">
                <div className="flex items-start">
                    <div className="grid gap-1">
                        <CardTitle className="font-body text-base font-medium text-[#0F172A]">Ticket By First Reply Time</CardTitle>
                        <CardDescription className="text-sm text-[#94A3B8]">Interactive reply-time breakdown</CardDescription>
                    </div>
                    <Select value={activeSegment} onValueChange={(value) => value && setActiveSegment(value as ReplyTimeName)}>
                        <SelectTrigger
                            size="sm"
                            className="ml-auto h-7 w-[130px] rounded-lg border-[#E2E8F0] bg-white pl-2.5 text-xs text-[#64748B]"
                            aria-label="Select reply time segment"
                        >
                            <SelectValue placeholder="Select segment" />
                        </SelectTrigger>
                        <SelectContent align="end" className="rounded-xl">
                            {REPLY_TIME_DATA.map(({ name }) => (
                                <SelectItem key={name} value={name} className="rounded-lg [&_span]:flex">
                                    <div className="flex items-center gap-2 text-xs">
                                        <span
                                            className="flex h-3 w-3 shrink-0 rounded-[2px]"
                                            style={{ backgroundColor: `var(--color-${name})` }}
                                        />
                                        {name}
                                    </div>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col items-center justify-center gap-8 p-6 pt-4 xl:p-8 xl:pt-4 2xl:flex-row 2xl:justify-between">
                <ChartContainer
                    id={chartId}
                    config={replyTimeInteractiveChartConfig}
                    className="mx-auto aspect-square w-full max-w-[300px]"
                >
                    <PieChart>
                        <ChartTooltip cursor={false} content={<ChartTooltipContent hideLabel formatter={(value) => `${value}%`} />} />
                        <Pie
                            data={REPLY_TIME_DATA}
                            dataKey="value"
                            nameKey="name"
                            innerRadius={58}
                            strokeWidth={5}
                            shape={renderPieShape}
                        >
                            {REPLY_TIME_DATA.map((entry) => (
                                <Cell key={entry.name} fill={entry.fill} />
                            ))}
                            <Label
                                content={({ viewBox }) => {
                                    if (viewBox && 'cx' in viewBox && 'cy' in viewBox) {
                                        return (
                                            <text
                                                x={viewBox.cx}
                                                y={viewBox.cy}
                                                textAnchor="middle"
                                                dominantBaseline="middle"
                                            >
                                                <tspan
                                                    x={viewBox.cx}
                                                    y={viewBox.cy}
                                                    className="fill-foreground text-3xl font-bold"
                                                >
                                                    {activeItem.value}%
                                                </tspan>
                                                <tspan
                                                    x={viewBox.cx}
                                                    y={(viewBox.cy || 0) + 24}
                                                    className="fill-muted-foreground"
                                                >
                                                    {activeItem.name}
                                                </tspan>
                                            </text>
                                        );
                                    }

                                    return null;
                                }}
                            />
                        </Pie>
                    </PieChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}

export default function Dashboard() {
    const [dateRange, setDateRange] = React.useState<(typeof DATE_RANGE_OPTIONS)[number]['value']>('dec-1-7');

    return (
        <>
            <MigrationBanner />

            <div className="space-y-6 xl:space-y-8">
                <SectionFrame>
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4">
                        {STAT_CARDS.map((card, index) => (
                            <div key={card.label} className={cn(index > 0 && 'border-t border-[#E2E8F0] md:border-t-0', index % 2 === 1 && 'md:border-l md:border-[#E2E8F0] xl:border-l', index >= 2 && 'xl:border-l xl:border-t-0')}>
                                <StatCard {...card} />
                            </div>
                        ))}
                    </div>
                </SectionFrame>

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-3 xl:gap-0">
                    <SectionFrame className="xl:col-span-2 xl:rounded-r-none xl:border-r-0">
                        <CardHeader className="px-6 pt-6 xl:px-8 xl:pt-8">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <CardTitle className="font-body text-base font-medium text-[#0F172A]">Average Tickets Created</CardTitle>
                                    <CardDescription className="mt-1 text-sm text-[#94A3B8]">Dec 1 - 7</CardDescription>
                                </div>
                                <Select value={dateRange} onValueChange={(value) => value && setDateRange(value as (typeof DATE_RANGE_OPTIONS)[number]['value'])}>
                                    <SelectTrigger
                                        size="sm"
                                        className="rounded-md border-[#E2E8F0] bg-white text-xs text-[#64748B]"
                                        aria-label="Select ticket date range"
                                    >
                                        <SelectValue placeholder="Select date range" />
                                    </SelectTrigger>
                                    <SelectContent align="end" className="rounded-xl">
                                        {DATE_RANGE_OPTIONS.map(({ value, label }) => (
                                            <SelectItem key={value} value={value} className="rounded-lg text-xs">
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </CardHeader>
                        <CardContent className="px-6 pb-6 xl:px-8 xl:pb-8">
                            <ChartContainer config={ticketChartConfig} className="h-[360px] min-h-0 w-full !aspect-auto">
                                <BarChart accessibilityLayer data={BAR_CHART_DATA} margin={{ top: 16, right: 8, left: 0, bottom: 8 }}>
                                    <CartesianGrid vertical={false} strokeDasharray="3 5" />
                                    <XAxis dataKey="day" tickLine={false} axisLine={false} tickMargin={10} />
                                    <YAxis tickLine={false} axisLine={false} tickMargin={12} domain={[0, 5000]} ticks={[0, 1000, 2000, 3000, 4000, 5000]} />
                                    <ChartTooltip content={<ChartTooltipContent hideLabel />} />
                                    <ChartLegend content={<ChartLegendContent />} />
                                    <Bar dataKey="solved" stackId="a" radius={[0, 0, 4, 4]} maxBarSize={32}>
                                        {BAR_CHART_DATA.map((item) => (
                                            <Cell key={`solved-${item.day}`} fill="var(--color-solved)" fillOpacity={item.highlighted ? 1 : 0.92} />
                                        ))}
                                    </Bar>
                                    <Bar dataKey="created" stackId="a" radius={[4, 4, 0, 0]} maxBarSize={32}>
                                        {BAR_CHART_DATA.map((item) => (
                                            <Cell key={`created-${item.day}`} fill={item.highlighted ? '#C4A5F3' : 'var(--color-created)'} fillOpacity={item.highlighted ? 1 : 0.9} />
                                        ))}
                                    </Bar>
                                </BarChart>
                            </ChartContainer>
                        </CardContent>
                        <TicketChartFooter />
                    </SectionFrame>

                    <SectionFrame className="xl:rounded-l-none">
                        <ReplyTimeInteractiveChart />
                    </SectionFrame>
                </div>

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2 xl:gap-0">
                    <SectionFrame className="xl:rounded-r-none xl:border-r-0">
                        <CardHeader className="px-6 pt-6 pb-0 xl:px-8 xl:pt-8">
                            <CardTitle className="font-body text-base font-medium text-[#0F172A]">Ticket by Channels</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col items-center p-6 xl:p-8">
                            <div className="relative mb-8 h-40 w-72 max-w-full">
                                <ChartContainer config={channelChartConfig} className="h-full w-full !aspect-auto">
                                    <PieChart>
                                        <ChartTooltip content={<ChartTooltipContent hideLabel formatter={(value) => value?.toLocaleString?.() ?? String(value)} />} />
                                        <Pie data={CHANNEL_DATA} dataKey="value" nameKey="name" startAngle={180} endAngle={0} innerRadius={72} outerRadius={96} cx="50%" cy="100%" stroke="transparent" paddingAngle={2}>
                                            {CHANNEL_DATA.map((entry) => (
                                                <Cell key={entry.name} fill={entry.fill} />
                                            ))}
                                        </Pie>
                                    </PieChart>
                                </ChartContainer>

                                <div className="absolute bottom-0 left-1/2 w-full -translate-x-1/2 text-center">
                                    <p className="text-[11px] text-[#94A3B8]">Total Active ticket</p>
                                    <p className="font-display text-[30px] font-medium tracking-tight text-[#0F172A]">3002</p>
                                </div>
                            </div>

                            <div className="flex flex-wrap justify-center gap-x-6 gap-y-3 text-xs text-[#64748B]">
                                {CHANNEL_DATA.map(({ fill, name }) => (
                                    <div key={name} className="flex items-center gap-2">
                                        <div className="h-2 w-2 rounded-sm" style={{ backgroundColor: fill }}></div>
                                        {name}
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </SectionFrame>

                    <SectionFrame className="xl:rounded-l-none">
                        <CardHeader className="px-6 pt-6 pb-0 xl:px-8 xl:pt-8">
                            <CardTitle className="font-body text-base font-medium text-[#0F172A]">Customer Satisfaction</CardTitle>
                        </CardHeader>
                        <CardContent className="p-6 xl:p-8">
                            <div className="grid gap-8 lg:grid-cols-2 lg:gap-6">
                                <div className="flex items-center lg:pr-8">
                                    <div className="w-full">
                                        <p className="mb-2 text-sm text-[#64748B]">Responses Received</p>
                                        <p className="font-display text-[30px] font-medium tracking-tight text-[#0F172A]">156 Customers</p>
                                    </div>
                                    <Separator orientation="vertical" className="ml-8 hidden bg-[#E2E8F0] lg:block" />
                                </div>

                                <div className="space-y-6">
                                    {SATISFACTION_ROWS.map(({ icon, label, percent, helper, fill, tone, iconTone }) => (
                                        <div key={label}>
                                            <div className="mb-2 flex items-center gap-3">
                                                <div className={cn('flex h-11 w-11 items-center justify-center rounded-full text-xl', iconTone)}>
                                                    <span>{icon}</span>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-[#64748B]">{label}</p>
                                                    <p className="mt-0.5 text-xl font-semibold leading-none text-[#0F172A]">{percent}</p>
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-3">
                                                <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-[#E2E8F0]">
                                                    <div className={cn('h-full rounded-full', tone)} style={{ width: fill }}></div>
                                                </div>
                                                <span className="text-[10px] font-medium text-[#94A3B8]">{helper}</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </CardContent>
                    </SectionFrame>
                </div>
            </div>
        </>
    );
}

type DashboardPageComponent = typeof Dashboard & {
    layout?: (page: ReactElement) => ReactNode;
};

(Dashboard as DashboardPageComponent).layout = (page: ReactElement) => (
    <DashboardLayout title="Dashboard" activeNav="dashboard" contentClassName="w-full">
        {page}
    </DashboardLayout>
);
