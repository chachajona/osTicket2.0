import type { ReactNode } from 'react';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    Add01Icon,
    Download01Icon,
    MaximizeScreenIcon,
    Search01Icon,
} from '@hugeicons/core-free-icons';

import { QueueSelector } from '@/components/scp/QueueSelector';
import {
    QueueTicketTable,
    type PaginationState,
    type TicketRow,
} from '@/components/scp/QueueTicketTable';
import { TicketFilterChips } from '@/components/scp/TicketFilterChips';
import { type QueueNavigation } from '@/components/scp/queue-types';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Kbd } from '@/components/ui/kbd';
import DashboardLayout from '@/layouts/DashboardLayout';

interface Queue {
    id: number;
    title: string;
}

interface QueueShowProps {
    navigation: QueueNavigation;
    queue: Queue;
    tickets: TicketRow[];
    pagination: PaginationState;
    unsupported: boolean;
    unsupportedReasons?: string[];
}

interface QueueShowLayoutProps extends QueueShowProps {
    children: ReactNode;
}

// Named function (not arrow) so Inertia treats it as a layout component, not a layout resolver.
// Arrow functions have prototype===undefined, triggering a speculative call with raw props that
// causes "TypeError: e.props is undefined". Named functions have a prototype, avoiding that path.
function QueueShowLayout({ queue, navigation, children }: QueueShowLayoutProps) {
    return (
        <DashboardLayout
            headerLeft={
                <div>
                    <QueueSelector navigation={navigation} activeQueueId={queue.id} variant="heading" label={queue.title} />
                    <p className="mt-1 font-body text-sm text-[#94A3B8]">Tickets</p>
                </div>
            }
            headerActions={
                <>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled
                        aria-disabled="true"
                        title="Coming soon"
                        className="rounded-md border-zinc-200 text-zinc-700"
                    >
                        <HugeiconsIcon icon={MaximizeScreenIcon} size={14} />
                        Focus Mode
                    </Button>
                    <a
                        href={`/scp/queues/${queue.id}/export`}
                        className={buttonVariants({ variant: 'outline', size: 'sm' })}
                    >
                        <HugeiconsIcon icon={Download01Icon} size={14} />
                        Export CSV
                    </a>
                    <Button
                        size="sm"
                        disabled
                        aria-disabled="true"
                        title="Ticket creation lands in a later phase"
                        className="rounded-md bg-emerald-500 text-white hover:bg-emerald-600 disabled:opacity-100"
                    >
                        <HugeiconsIcon icon={Add01Icon} size={14} />
                        Add Ticket
                    </Button>
                </>
            }
            activeNav="queues"
            contentClassName="w-full"
        >
            {children}
        </DashboardLayout>
    );
}

export default function QueueShow({
    queue,
    tickets,
    pagination,
    unsupported,
    unsupportedReasons = [],
}: QueueShowProps) {
    return (
        <div className="space-y-4">
            {unsupported && (
                <div className="rounded-[14px] border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                    <p className="font-medium">This queue uses legacy criteria that are not fully supported yet.</p>
                    {unsupportedReasons.length > 0 && (
                        <ul className="mt-2 list-disc space-y-1 pl-5 text-xs">
                            {unsupportedReasons.map((reason) => (
                                <li key={reason}>{reason}</li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            <div className="flex flex-wrap items-center gap-6 border-y border-zinc-100 px-1 py-3">
                <form
                    role="search"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const value = new FormData(event.currentTarget).get('q')?.toString().trim() ?? '';
                        if (value !== '') {
                            window.location.href = `/scp/search?queue=${queue.id}&q=${encodeURIComponent(value)}`;
                        }
                    }}
                    className="w-64"
                >
                    <InputGroup>
                        <InputGroupAddon align="inline-start">
                            <HugeiconsIcon icon={Search01Icon} size={14} />
                        </InputGroupAddon>
                        <InputGroupInput name="q" placeholder="Search" aria-label="Search this queue" />
                        <InputGroupAddon align="inline-end">
                            <Kbd>⌘</Kbd>
                            <Kbd>K</Kbd>
                        </InputGroupAddon>
                    </InputGroup>
                </form>

                <span className="text-xs text-zinc-400">
                    {pagination.total} {pagination.total === 1 ? 'ticket' : 'tickets'}
                </span>

                <TicketFilterChips />
            </div>

            <QueueTicketTable
                queueId={queue.id}
                tickets={tickets}
                pagination={pagination}
            />
        </div>
    );
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
(QueueShow as any).layout = QueueShowLayout;
