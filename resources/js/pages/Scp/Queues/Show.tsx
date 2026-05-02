import type { ReactElement, ReactNode } from 'react';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    Add01Icon,
    Download01Icon,
    Search01Icon,
} from '@hugeicons/core-free-icons';

import { QueueSelector } from '@/components/scp/QueueSelector';
import {
    QueueTicketTable,
    type PaginationState,
    type TicketRow,
} from '@/components/scp/QueueTicketTable';
import {
    TicketFilterChips,
    type QueueFilterOptions,
    type QueueFilters,
    type QueueSortState,
} from '@/components/scp/TicketFilterChips';
import { type QueueNavigation } from '@/components/scp/QueueTypes';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Kbd } from '@/components/ui/kbd';
import { appShellLayout, SetPageHeader } from '@/layouts/AppShell';

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
    filters: QueueFilters;
    filterOptions: QueueFilterOptions;
    sort: QueueSortState;
}

interface QueueShowLayoutProps extends QueueShowProps {
    children: ReactNode;
}

export default function QueueShow({
    navigation,
    queue,
    tickets,
    pagination,
    unsupported,
    unsupportedReasons = [],
    filters,
    filterOptions,
    sort,
}: QueueShowProps) {
    return (
        <>
            <SetPageHeader>
                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0 flex-1">
                        <QueueSelector navigation={navigation} activeQueueId={queue.id} variant="heading" label={queue.title} />
                        <p className="mt-1 font-body text-sm text-[#A1A1AA]">Tickets</p>
                    </div>
                    <div className="flex shrink-0 items-center gap-2 sm:gap-3">
                        <a
                            href={`/scp/queues/${queue.id}/export`}
                            className={buttonVariants({ variant: 'outline', size: 'sm' })}
                        >
                            <HugeiconsIcon icon={Download01Icon} size={14} />
                            Export CSV
                        </a>
                        <Button size="sm" disabled>
                            <HugeiconsIcon icon={Add01Icon} size={14} />
                            New Ticket
                        </Button>
                    </div>
                </div>
            </SetPageHeader>

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

            <div className="flex flex-wrap items-center gap-x-6 gap-y-3 border-y border-[#F4F2EB] px-1 py-3">
                <form
                    role="search"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const value = new FormData(event.currentTarget).get('q')?.toString().trim() ?? '';
                        if (value !== '') {
                            window.location.href = `/scp/search?queue=${queue.id}&q=${encodeURIComponent(value)}`;
                        }
                    }}
                    className="w-full sm:w-64"
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

                <span className="text-xs text-[#A1A1AA]">
                    {pagination.total} {pagination.total === 1 ? 'ticket' : 'tickets'}
                </span>

                <TicketFilterChips
                    queueId={queue.id}
                    filters={filters}
                    filterOptions={filterOptions}
                    sort={sort}
                />
            </div>

            <QueueTicketTable
                queueId={queue.id}
                tickets={tickets}
                pagination={pagination}
                sort={sort}
                filters={filters}
            />
        </div>
    </>
    );
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
(QueueShow as any).layout = appShellLayout;
