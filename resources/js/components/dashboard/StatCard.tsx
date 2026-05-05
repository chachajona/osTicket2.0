import { HugeiconsIcon } from '@hugeicons/react';
import { useTranslation } from 'react-i18next';

import {
    Card,
    CardContent,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

import { getTrendClass, getTrendIcon, getTrendLabel, numberFormat } from './helpers';
import type { MetricCard } from './types';

export function StatCard({ label, value, helper, trend, locale }: MetricCard & { locale: string }) {
    const { t } = useTranslation();
    const trendIcon = getTrendIcon(trend);

    return (
        <Card className="rounded-none border-0 py-0 shadow-none ring-0">
            <CardContent className="flex min-h-[132px] flex-col px-7 py-6">
                <div className="flex items-center justify-between gap-3">
                    <div className="font-body text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">{label}</div>
                </div>
                <div className="mt-3 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                    <div className="font-display text-[28px] font-medium leading-none tracking-[-0.025em] text-[#18181B]">
                        {numberFormat(value, locale)}
                    </div>
                    <div className={cn('inline-flex items-center gap-1 rounded-[4px] px-1.5 py-1 font-mono text-[10px] font-medium leading-none tracking-[0.05em]', getTrendClass(trend.direction))}>
                        {getTrendLabel(trend, t, locale)}
                        {trendIcon && <HugeiconsIcon icon={trendIcon} size={10} />}
                    </div>
                </div>
                <p className="mt-auto pt-2 text-xs text-[#A1A1AA]" title={helper}>{t('dashboard.metrics.trend.compare_to_last_month')}</p>
            </CardContent>
        </Card>
    );
}
