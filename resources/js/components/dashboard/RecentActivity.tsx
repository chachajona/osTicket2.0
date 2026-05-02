import { router } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    Activity01Icon,
    Clock01Icon,
    InboxIcon,
} from '@hugeicons/core-free-icons';
import { useTranslation } from 'react-i18next';

import {
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

import { formatDashboardDateTime, formatDashboardRelative } from './helpers';
import { SectionFrame } from './SectionFrame';
import type { ActivityItem } from './types';

function getActivityDotClass(event: string | null): string {
    const value = event?.toLowerCase() ?? '';

    if (value.includes('closed') || value.includes('resolved')) {
        return 'bg-[#16A34A] ring-[#BBF7D0]';
    }

    if (value.includes('overdue') || value.includes('reopened')) {
        return 'bg-[#DC2626] ring-[#FEE2E2]';
    }

    if (value.includes('assigned') || value.includes('transferred')) {
        return 'bg-[#F97316] ring-[#FEE9DA]';
    }

    return 'bg-[#6366F1] ring-[#E6E7FD]';
}

export function RecentActivity({ items, locale, t }: { items: ActivityItem[]; locale: string; t: ReturnType<typeof useTranslation>['t'] }) {
    return (
        <SectionFrame className="border-t border-[#E2E0D8] xl:rounded-l-none xl:border-l xl:border-t-0 xl:border-[#E2E0D8]">
            <CardHeader className="flex flex-row items-start justify-between gap-4 px-7 pt-7 pb-0">
                <div>
                    <CardTitle className="font-body text-sm font-medium text-[#18181B]">{t('dashboard.recent_activity.title')}</CardTitle>
                    <CardDescription className="mt-1 text-xs text-[#A1A1AA]">{t('dashboard.recent_activity.description')}</CardDescription>
                </div>
                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-[5px] bg-[#F4F2EB] text-[#6366F1]">
                    <HugeiconsIcon icon={Activity01Icon} size={17} />
                </div>
            </CardHeader>
            <CardContent className="flex flex-col px-7 pb-7 pt-5">
                {items.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-[6px] border border-dashed border-[#E2E0D8] bg-[#FAFAF8] px-5 py-12 text-center">
                        <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-[#F4F2EB] text-[#A1A1AA]">
                            <HugeiconsIcon icon={InboxIcon} size={20} />
                        </div>
                        <p className="text-sm font-medium text-[#71717A]">{t('dashboard.recent_activity.empty')}</p>
                    </div>
                ) : (
                    <div className="relative">
                        <div className="absolute bottom-6 left-[1px] top-6 w-[2px] bg-[#F4F2EB]" aria-hidden />
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
                                        <div className={cn('relative z-10 w-full min-w-0 rounded-[6px] border border-[#F4F2EB] bg-white p-4 shadow-[0_1px_2px_0_rgba(15,23,42,0.02)] transition group-hover:border-[#E2E0D8] group-hover:bg-[#FAFAF8]', 'border-l-[4px]', borderColor)}>
                                            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                                                <div className="flex min-w-0 items-center gap-2">
                                                    <span className="rounded-[4px] bg-[#F4F2EB] px-2 py-0.5 font-mono text-[11px] font-medium text-[#71717A] transition group-hover:bg-white">{title}</span>
                                                    <span className={cn('rounded-[4px] px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.05em]', lightBgColor, textColor)}>
                                                        {event}
                                                    </span>
                                                </div>
                                            </div>
                                            <p className="line-clamp-1 font-medium text-sm text-[#18181B]">
                                                {item.ticket_subject ?? t('dashboard.recent_activity.untitled_ticket')}
                                            </p>
                                            <div className="mt-3 flex items-center gap-2 text-[11px] font-medium text-[#A1A1AA]">
                                                {item.username && (
                                                    <span className="text-[#71717A]">
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
                                className="inline-flex items-center gap-1.5 border-b border-[#18181B] pb-0.5 font-body text-[12px] font-medium uppercase leading-4 tracking-[1.2px] text-[#18181B] transition-colors hover:border-[#EC4899] hover:text-[#EC4899]"
                            >
                                {t('dashboard.recent_activity.view_all', { defaultValue: 'View all activity' })}
                                <span aria-hidden>&rarr;</span>
                            </button>
                        </div>
                    </div>
                )}
            </CardContent>
        </SectionFrame>
    );
}
