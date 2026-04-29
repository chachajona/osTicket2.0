import { Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    ArrowDown01Icon,
    ArrowUp01Icon,
    MoreHorizontalIcon,
    Tick02Icon,
} from '@hugeicons/core-free-icons';

import { Avatar } from '@/components/scp/Avatar';
import { PriorityBadge } from '@/components/scp/PriorityBadge';
import type { QueueFilters, QueueSortState } from '@/components/scp/TicketFilterChips';
import { Button } from '@/components/ui/button';
import { formatTicketDate } from '@/lib/datetime';
import { cn } from '@/lib/utils';

export interface TicketRow {
    id: number;
    number: string;
    created: string | null;
    subject: string | null;
    from: string | null;
    priority: string | null;
    assignee: string | null;
    status?: string | null;
    status_state?: string | null;
    source?: string | null;
}

export interface PaginationState {
    page: number;
    perPage: number;
    total: number;
}

interface QueueTicketTableProps {
    queueId: number;
    tickets: TicketRow[];
    pagination: PaginationState;
    sort: QueueSortState;
    filters: QueueFilters;
}

type SortDirection = 'asc' | 'desc';

interface ColumnDef {
    id: string;
    label: string;
    sortable: boolean;
    width?: string;
}

const COLUMNS: ColumnDef[] = [
    { id: 'number', label: 'Ticket ID', sortable: true, width: 'w-28' },
    { id: 'subject', label: 'Subject', sortable: false },
    { id: 'priority', label: 'Priority', sortable: true, width: 'w-32' },
    { id: 'from', label: 'Client', sortable: true, width: 'w-56' },
    { id: 'assignee', label: 'Assignee', sortable: true, width: 'w-44' },
    { id: 'created', label: 'Request Date', sortable: true, width: 'w-48' },
];

const ROW_BORDER = 'border-y border-zinc-100';
const ROW_BORDER_FIRST = 'border-l border-zinc-100';
const ROW_BORDER_LAST = 'border-r border-zinc-100';
const ACTIVE_BORDER = 'border-y border-[#5B619D]';
const ACTIVE_BORDER_FIRST = 'border-l border-[#5B619D]';
const ACTIVE_BORDER_LAST = 'border-r border-[#5B619D]';
const ACTIVE_BG = 'bg-[#F3ECFF]';

function CheckboxButton({
    checked,
    onChange,
    label,
    accent = false,
}: {
    checked: boolean;
    onChange: () => void;
    label: string;
    accent?: boolean;
}) {
    return (
        <button
            type="button"
            role="checkbox"
            aria-checked={checked}
            aria-label={label}
            onClick={onChange}
            className={cn(
                'grid h-4 w-4 place-items-center rounded border transition-colors',
                checked
                    ? 'border-[#5B619D] bg-[#5B619D] text-white'
                    : accent
                      ? 'border-zinc-400 text-transparent hover:border-zinc-500'
                      : 'border-zinc-300 text-transparent hover:border-zinc-400',
            )}
        >
            <HugeiconsIcon icon={Tick02Icon} size={10} strokeWidth={3} />
        </button>
    );
}

function buildFilterParams(filters: QueueFilters): Record<string, string | number | string[] | number[]> {
    const params: Record<string, string | number | string[] | number[]> = {};
    if (filters.state.length > 0) params.state = filters.state;
    if (filters.source.length > 0) params.source = filters.source;
    if (filters.priority.length > 0) params.priority = filters.priority;
    if (filters.created_from) params.created_from = filters.created_from;
    if (filters.created_to) params.created_to = filters.created_to;
    return params;
}

export function QueueTicketTable({ queueId, tickets, pagination, sort, filters }: QueueTicketTableProps) {
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const visibleIds = useMemo(() => tickets.map((ticket) => ticket.id), [tickets]);
    const visibleIdSet = useMemo(() => new Set(visibleIds), [visibleIds]);
    const allSelected = tickets.length > 0 && tickets.every((ticket) => selected.has(ticket.id));

    const sortBy = sort.by;
    const sortDir: SortDirection = sort.dir === 'asc' ? 'asc' : 'desc';

    useEffect(() => {
        setSelected((current) => new Set([...current].filter((id) => visibleIdSet.has(id))));
    }, [visibleIdSet]);

    function toggleAll() {
        setSelected(allSelected ? new Set() : new Set(visibleIds));
    }

    function toggle(id: number) {
        setSelected((current) => {
            const next = new Set(current);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    }

    function handleSort(column: ColumnDef) {
        if (!column.sortable) return;
        const nextDir: SortDirection = column.id === sortBy && sortDir === 'asc' ? 'desc' : 'asc';
        router.get(
            `/scp/queues/${queueId}`,
            { ...buildFilterParams(filters), sort: column.id, dir: nextDir, page: 1 },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }

    function navigatePage(nextPage: number) {
        router.get(
            `/scp/queues/${queueId}`,
            { ...buildFilterParams(filters), page: nextPage, sort: sortBy, dir: sortDir },
            { preserveScroll: true, preserveState: true },
        );
    }

    const showingFrom = pagination.total === 0 ? 0 : (pagination.page - 1) * pagination.perPage + 1;
    const showingTo = Math.min(pagination.page * pagination.perPage, pagination.total);

    return (
        <div className="flex flex-col">
            <div className="overflow-x-auto px-1">
                <table className="w-full text-left" style={{ borderCollapse: 'separate', borderSpacing: '0 4px' }}>
                    <thead>
                        <tr>
                            <th scope="col" className="w-12 px-3 py-2 font-normal">
                                <CheckboxButton
                                    checked={allSelected}
                                    onChange={toggleAll}
                                    label="Select all tickets"
                                />
                            </th>
                            {COLUMNS.map((column) => (
                                <th
                                    key={column.id}
                                    scope="col"
                                    className={cn(
                                        'px-3 py-2 text-xs font-normal whitespace-nowrap',
                                        column.sortable ? 'text-zinc-500' : 'text-zinc-500',
                                        column.width,
                                    )}
                                    aria-sort={
                                        sortBy === column.id
                                            ? sortDir === 'asc' ? 'ascending' : 'descending'
                                            : 'none'
                                    }
                                >
                                    <button
                                        type="button"
                                        onClick={() => handleSort(column)}
                                        disabled={!column.sortable}
                                        className={cn(
                                            'inline-flex items-center gap-1 transition-colors',
                                            column.sortable ? 'cursor-pointer hover:text-zinc-800' : 'cursor-default',
                                            sortBy === column.id && 'text-zinc-900',
                                        )}
                                    >
                                        {column.label}
                                        {column.sortable && (
                                            <HugeiconsIcon
                                                icon={sortBy === column.id && sortDir === 'asc' ? ArrowUp01Icon : ArrowDown01Icon}
                                                size={11}
                                                className={sortBy === column.id ? 'opacity-100' : 'opacity-50'}
                                            />
                                        )}
                                    </button>
                                </th>
                            ))}
                            <th scope="col" className="w-12 px-3 py-2" aria-label="Actions" />
                        </tr>
                    </thead>
                    <tbody className="text-sm">
                        {tickets.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={COLUMNS.length + 2}
                                    className="rounded-lg border border-dashed border-zinc-200 bg-white px-6 py-12 text-center text-sm text-zinc-500"
                                >
                                    <p className="font-medium text-zinc-700">No tickets match this queue.</p>
                                    <p className="mt-1 text-xs">
                                        The queue&apos;s criteria returned 0 results for tickets you have access to. Try a different queue from the dropdown above.
                                    </p>
                                </td>
                            </tr>
                        ) : (
                            tickets.map((ticket) => {
                                const isSelected = selected.has(ticket.id);
                                const cellBase = 'px-3 py-3 whitespace-nowrap transition-colors';
                                const borderClass = isSelected ? ACTIVE_BORDER : ROW_BORDER;
                                const firstBorder = isSelected ? ACTIVE_BORDER_FIRST : ROW_BORDER_FIRST;
                                const lastBorder = isSelected ? ACTIVE_BORDER_LAST : ROW_BORDER_LAST;
                                const cellBg = isSelected
                                    ? ACTIVE_BG
                                    : 'bg-white group-hover:bg-zinc-50';

                                return (
                                    <tr key={ticket.id} className="group">
                                        <td className={cn(cellBase, borderClass, firstBorder, cellBg, 'rounded-l-lg')}>
                                            <CheckboxButton
                                                checked={isSelected}
                                                onChange={() => toggle(ticket.id)}
                                                label={`Select ticket ${ticket.number}`}
                                                accent={isSelected}
                                            />
                                        </td>
                                        <td className={cn(cellBase, borderClass, cellBg, 'font-medium text-zinc-700')}>
                                            <Link href={`/scp/tickets/${ticket.id}`} className="hover:underline">
                                                #{ticket.number}
                                            </Link>
                                        </td>
                                        <td className={cn(cellBase, borderClass, cellBg, 'whitespace-normal text-zinc-800')}>
                                            <Link
                                                href={`/scp/tickets/${ticket.id}`}
                                                className="line-clamp-1 hover:underline"
                                            >
                                                {ticket.subject ?? '—'}
                                            </Link>
                                        </td>
                                        <td className={cn(cellBase, borderClass, cellBg)}>
                                            <PriorityBadge priority={ticket.priority} />
                                        </td>
                                        <td className={cn(cellBase, borderClass, cellBg, 'text-zinc-700')}>
                                            {ticket.from ? (
                                                <span className="flex items-center gap-2">
                                                    <Avatar name={ticket.from} size={24} />
                                                    <span className="truncate">{ticket.from}</span>
                                                </span>
                                            ) : (
                                                <span className="text-zinc-400">—</span>
                                            )}
                                        </td>
                                        <td className={cn(cellBase, borderClass, cellBg, 'text-zinc-600')}>
                                            {ticket.assignee ?? <span className="text-zinc-400">Unassigned</span>}
                                        </td>
                                        <td className={cn(cellBase, borderClass, cellBg, 'text-zinc-500')}>
                                            {formatTicketDate(ticket.created) ?? '—'}
                                        </td>
                                        <td className={cn(cellBase, borderClass, lastBorder, cellBg, 'rounded-r-lg text-right')}>
                                            <button
                                                type="button"
                                                disabled
                                                aria-disabled="true"
                                                title="Row actions coming soon"
                                                className="rounded p-1 text-zinc-400 transition-colors hover:text-zinc-600"
                                            >
                                                <HugeiconsIcon icon={MoreHorizontalIcon} size={16} />
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            <footer className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-100 px-3 pt-4 text-xs text-zinc-500">
                <span>
                    {pagination.total === 0
                        ? 'No tickets'
                        : `Showing ${showingFrom}–${showingTo} of ${pagination.total}`}
                </span>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={pagination.page <= 1}
                        aria-disabled={pagination.page <= 1}
                        onClick={() => navigatePage(pagination.page - 1)}
                    >
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={showingTo >= pagination.total}
                        aria-disabled={showingTo >= pagination.total}
                        onClick={() => navigatePage(pagination.page + 1)}
                    >
                        Next
                    </Button>
                </div>
            </footer>
        </div>
    );
}
