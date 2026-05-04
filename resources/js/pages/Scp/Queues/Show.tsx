import type { ReactElement, ReactNode } from 'react';
import { useState } from 'react';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    Add01Icon,
    Download01Icon,
    Search01Icon,
    Settings02Icon,
} from '@hugeicons/core-free-icons';
import { router } from '@inertiajs/react';

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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { appShellLayout, SetPageHeader } from '@/layouts/AppShell';

interface Queue {
    id: number;
    title: string;
}

interface QueueColumn {
    column_id: number;
    sort?: number;
    width?: number;
    heading?: string;
    [key: string]: number | string | undefined;
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
    columns?: QueueColumn[];
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
    columns = [],
}: QueueShowProps) {
    const [showCustomizeDrawer, setShowCustomizeDrawer] = useState(false);
    const [columnEdits, setColumnEdits] = useState<QueueColumn[]>(columns);
    const [isSaving, setIsSaving] = useState(false);

    const handleSaveColumns = () => {
        setIsSaving(true);
        router.patch(
            `/scp/queues/${queue.id}/columns`,
            { columns: columnEdits },
            {
                onFinish: () => {
                    setIsSaving(false);
                    setShowCustomizeDrawer(false);
                },
            }
        );
    };

    const updateColumn = (index: number, field: string, value: unknown) => {
        const updated = [...columnEdits];
        updated[index] = { ...updated[index], [field]: value as number | string | undefined };
        setColumnEdits(updated);
    };
    return (
        <>
            <SetPageHeader>
                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0 flex-1">
                        <QueueSelector navigation={navigation} activeQueueId={queue.id} variant="heading" label={queue.title} />
                        <p className="mt-1 font-body text-sm text-[#A1A1AA]">Tickets</p>
                    </div>
                    <div className="flex shrink-0 items-center gap-2 sm:gap-3">
                        <button
                            onClick={() => setShowCustomizeDrawer(true)}
                            className={buttonVariants({ variant: 'outline', size: 'sm' })}
                        >
                            <HugeiconsIcon icon={Settings02Icon} size={14} />
                            Customize
                        </button>
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

            {/* Customize Drawer */}
            {showCustomizeDrawer && (
                <div className="fixed inset-0 z-50 flex">
                    {/* Backdrop */}
                    <div
                        className="flex-1 bg-black/50"
                        onClick={() => setShowCustomizeDrawer(false)}
                    />
                    {/* Drawer Panel */}
                    <div className="w-96 flex flex-col bg-white shadow-lg">
                        <div className="border-b border-[#E5E5E5] px-6 py-4">
                            <h2 className="text-lg font-semibold">Customize Columns</h2>
                        </div>
                        <div className="flex-1 overflow-y-auto px-6 py-4 space-y-6">
                            {columnEdits.map((column, index) => (
                                <div key={column.column_id} className="space-y-3 pb-4 border-b border-[#E5E5E5]">
                                    <div>
                                        <Label className="text-xs font-medium text-[#71717A]">Column ID</Label>
                                        <div className="mt-1 text-sm text-[#3F3F46]">{column.column_id}</div>
                                    </div>
                                    <div>
                                        <Label htmlFor={`sort-${index}`} className="text-xs font-medium text-[#71717A]">
                                            Sort Order
                                        </Label>
                                        <Input
                                            id={`sort-${index}`}
                                            type="number"
                                            value={column.sort ?? ''}
                                            onChange={(e) => updateColumn(index, 'sort', e.target.value ? parseInt(e.target.value) : undefined)}
                                            className="mt-1"
                                            placeholder="Sort order"
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor={`width-${index}`} className="text-xs font-medium text-[#71717A]">
                                            Width (50-1000)
                                        </Label>
                                        <Input
                                            id={`width-${index}`}
                                            type="number"
                                            min="50"
                                            max="1000"
                                            value={column.width ?? ''}
                                            onChange={(e) => updateColumn(index, 'width', e.target.value ? parseInt(e.target.value) : undefined)}
                                            className="mt-1"
                                            placeholder="Width"
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor={`heading-${index}`} className="text-xs font-medium text-[#71717A]">
                                            Heading
                                        </Label>
                                        <Input
                                            id={`heading-${index}`}
                                            type="text"
                                            maxLength={80}
                                            value={column.heading ?? ''}
                                            onChange={(e) => updateColumn(index, 'heading', e.target.value || undefined)}
                                            className="mt-1"
                                            placeholder="Column heading"
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="border-t border-[#E5E5E5] flex gap-2 px-6 py-4">
                            <Button
                                onClick={() => setShowCustomizeDrawer(false)}
                                variant="outline"
                                className="flex-1"
                                disabled={isSaving}
                            >
                                Cancel
                            </Button>
                            <Button
                                onClick={handleSaveColumns}
                                className="flex-1"
                                disabled={isSaving}
                            >
                                {isSaving ? 'Saving...' : 'Save'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}

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
