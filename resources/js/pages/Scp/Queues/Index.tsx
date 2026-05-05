import type { ReactElement, ReactNode } from 'react';

import { type QueueNavigation } from '@/components/scp/QueueTypes';
import { QueueSelector } from '@/components/scp/QueueSelector';
import { PageHeader } from '@/components/layout/PageHeader';
import { appShellLayout } from '@/layouts/AppShell';

interface QueuesIndexProps {
    navigation: QueueNavigation;
}

export default function QueuesIndex({ navigation }: QueuesIndexProps) {
    return (
        <>
            <PageHeader title="Tickets" subtitle="Browse the legacy ticket queues you have access to." eyebrow="Tickets" headerActions={null} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center gap-4 px-1">
                    <QueueSelector navigation={navigation} placeholder="Pick a queue" />
                    <span className="text-xs text-[#A1A1AA]">
                        Pick a queue from the list above to see its ticket table.
                    </span>
                </div>

                <section className="flex min-h-[320px] flex-col items-center justify-center rounded-[18px] border border-dashed border-[#E2E0D8] bg-white px-6 py-12 text-center">
                    <h2 className="font-display text-lg font-medium text-[#18181B]">No queue selected</h2>
                    <p className="mt-2 max-w-md text-sm text-[#71717A]">
                        Each queue carries its own filters, columns, and sort order. Personal queues and saved searches
                        sync from your legacy osTicket profile and stay private to you.
                    </p>
                </section>
            </div>
        </>
    );
}

type QueuesIndexComponent = typeof QueuesIndex & {
    layout?: (page: ReactElement) => ReactNode;
};

(QueuesIndex as QueuesIndexComponent).layout = appShellLayout;
