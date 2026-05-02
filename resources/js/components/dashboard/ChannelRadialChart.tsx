import { useTranslation } from 'react-i18next';
import {
    Cell,
    Pie,
    PieChart,
} from 'recharts';

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

import { formatMonth, numberFormat } from './helpers';
import { SectionFrame } from './SectionFrame';
import type { ChannelDistribution } from './types';

const CHANNEL_COLORS = ['#F97316', '#EC4899', '#6366F1', '#FB9A57', '#D66313', '#06B6D4', '#18181B', '#84CC16', '#14B8A6'];

export function ChannelRadialChart({ distribution, locale, t }: { distribution: ChannelDistribution; locale: string; t: ReturnType<typeof useTranslation>['t'] }) {
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
                <CardTitle className="font-body text-sm font-medium text-[#18181B]">{t('dashboard.channels.title')}</CardTitle>
                <CardDescription className="mt-1 text-xs text-[#A1A1AA]">
                    {t('dashboard.channels.description', { rangeStart, rangeEnd })}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex min-h-[360px] flex-col px-7 pb-7 pt-5">
                {distribution.total === 0 || channels.length === 0 ? (
                    <div className="flex flex-1 items-center justify-center rounded-[6px] border border-dashed border-[#E2E0D8] bg-[#FAFAF8] px-5 py-10 text-center text-sm text-[#71717A]">
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
                                <div className="font-display text-2xl font-medium text-[#18181B]">{numberFormat(distribution.total, locale)}</div>
                                <div className="mt-0.5 text-xs text-[#A1A1AA]">{t('dashboard.channels.total')}</div>
                            </div>
                        </div>
                        <div className="mt-3 flex flex-wrap justify-center gap-x-4 gap-y-2 text-xs">
                            {channels.map((channel) => (
                                <div key={channel.key} className="flex min-w-0 items-center gap-2 text-[#71717A]">
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
