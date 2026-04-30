import type { ReactElement, ReactNode } from 'react';

import { type QueueNavigation } from '@/components/scp/queue-types';
import { QueueSelector } from '@/components/scp/QueueSelector';
import DashboardLayout from '@/layouts/DashboardLayout';

interface QueuesIndexProps {
    navigation: QueueNavigation;
}

export default function QueuesIndex({ navigation }: QueuesIndexProps) {
    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center gap-4 px-1">
                <QueueSelector navigation={navigation} placeholder="Pick a queue" />
                <span className="text-xs text-zinc-400">
                    Pick a queue from the list above to see its ticket table.
                </span>
            </div>

            <section className="flex min-h-[320px] flex-col items-center justify-center rounded-[18px] border border-dashed border-zinc-200 bg-white px-6 py-12 text-center">
                <h2 className="font-display text-lg font-medium text-zinc-900">No queue selected</h2>
                <p className="mt-2 max-w-md text-sm text-zinc-500">
                    Each queue carries its own filters, columns, and sort order. Personal queues and saved searches
                    sync from your legacy osTicket profile and stay private to you.
                </p>
            </section>
        </div>
    );
}

type QueuesIndexComponent = typeof QueuesIndex & {
    layout?: (page: ReactElement) => ReactNode;
};

(QueuesIndex as QueuesIndexComponent).layout = (page: ReactElement) => (
    <DashboardLayout
        title="Tickets"
        subtitle="Browse the legacy ticket queues you have access to."
        eyebrow="Tickets"
        activeNav="queues"
        contentClassName="w-full"
        headerActions={null}
    >
        {page}
    </DashboardLayout>
);
