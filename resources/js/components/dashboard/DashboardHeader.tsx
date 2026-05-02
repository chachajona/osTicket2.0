import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/select';

export function DashboardHeaderTitle() {
    const { t } = useTranslation();

    return (
        <div className="flex items-baseline gap-2.5">
            <h1 className="font-display text-xl font-medium tracking-[-0.02em] text-[#18181B]">{t('nav.dashboard')}</h1>
            <span className="text-[#A1A1AA]">·</span>
            <span className="font-body text-[11px] font-medium uppercase tracking-[0.12em] text-[#A1A1AA]">{t('dashboard.overview')}</span>
        </div>
    );
}

export function DashboardHeaderActions() {
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
                <SelectTrigger className="h-7 w-[160px] rounded-[4px] border-[#E2E0D8] bg-white text-xs text-[#71717A]">
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
                onClick={() => router.reload({ only: ['metrics'] })}
            >
                {t('actions.refresh')}
            </Button>
        </>
    );
}
