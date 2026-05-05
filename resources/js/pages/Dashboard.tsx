import type { ReactElement, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { AssignmentChart } from '@/components/dashboard/AssignmentChart';
import { ChannelRadialChart } from '@/components/dashboard/ChannelRadialChart';
import { DashboardHeaderActions, DashboardHeaderTitle } from '@/components/dashboard/DashboardHeader';
import { RecentActivity } from '@/components/dashboard/RecentActivity';
import { SectionFrame } from '@/components/dashboard/SectionFrame';
import { StatCard } from '@/components/dashboard/StatCard';
import type { DashboardProps } from '@/components/dashboard/types';
import { WorkloadChart } from '@/components/dashboard/WorkloadChart';
import { getMetricCards, getStatCardBorderClass } from '@/components/dashboard/helpers';
import { PageHeader } from '@/components/layout/PageHeader';
import { appShellLayout } from '@/layouts/AppShell';
export default function Dashboard({ metrics }: DashboardProps) {
    const { t, i18n } = useTranslation();
    const locale = i18n.resolvedLanguage ?? i18n.language;
    const cards = getMetricCards(metrics, t, locale);

    return (
        <>
            <PageHeader headerLeft={<DashboardHeaderTitle />} headerActions={<DashboardHeaderActions />} />
            <div className="overflow-hidden rounded-[8px] border border-[#E2E0D8] bg-white shadow-sm shadow-[#18181B]/[0.03]">
                <div className="border-b border-[#E2E0D8]">
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

                <div className="grid grid-cols-1 border-b border-[#E2E0D8] xl:grid-cols-3">
                    <WorkloadChart metrics={metrics} locale={locale} t={t} />
                    <AssignmentChart metrics={metrics} locale={locale} t={t} />
                </div>

                <div className="grid grid-cols-1 xl:grid-cols-2">
                    <ChannelRadialChart distribution={metrics.channelDistribution} locale={locale} t={t} />
                    <RecentActivity items={metrics.recentActivity} locale={locale} t={t} />
                </div>
            </div>
        </>
    );
}

type DashboardPageComponent = typeof Dashboard & {
    layout?: (page: ReactElement) => ReactNode;
};

(Dashboard as DashboardPageComponent).layout = appShellLayout;
