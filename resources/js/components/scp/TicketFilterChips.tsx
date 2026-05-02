import { router } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    AlertCircleIcon,
    ArrowDown01Icon,
    Calendar01Icon,
    Cancel01Icon,
    FilterIcon,
    InboxIcon,
    Layers01Icon,
    Tick02Icon,
} from '@hugeicons/core-free-icons';

import type { QueueFilterOptions, QueueFilters, QueueSortState } from './QueueTypes';

import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type { QueueFilterOptions, QueueFilters, QueueSortState } from './QueueTypes';

interface TicketFilterChipsProps {
    queueId: number;
    filters: QueueFilters;
    filterOptions: QueueFilterOptions;
    sort: QueueSortState;
}

const DATE_PRESETS: { id: string; label: string; days: number | null }[] = [
    { id: 'today', label: 'Today', days: 0 },
    { id: 'last_7', label: 'Last 7 days', days: 6 },
    { id: 'last_30', label: 'Last 30 days', days: 29 },
    { id: 'last_90', label: 'Last 90 days', days: 89 },
    { id: 'all', label: 'All time', days: null },
];

function isoDate(date: Date): string {
    const yyyy = date.getFullYear();
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const dd = String(date.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

function startOfRange(days: number): string {
    const start = new Date();
    start.setHours(0, 0, 0, 0);
    start.setDate(start.getDate() - days);
    return isoDate(start);
}

function activeDatePreset(filters: QueueFilters): string | null {
    if (!filters.created_from && !filters.created_to) return 'all';
    const today = isoDate(new Date());
    if (filters.created_to !== today && filters.created_to !== null) return null;

    for (const preset of DATE_PRESETS) {
        if (preset.days === null) continue;
        if (filters.created_from === startOfRange(preset.days) && (filters.created_to === today || filters.created_to === null)) {
            return preset.id;
        }
    }
    return null;
}

function formatDateRange(filters: QueueFilters): string {
    if (filters.created_from && filters.created_to) {
        return `${filters.created_from} → ${filters.created_to}`;
    }
    if (filters.created_from) return `From ${filters.created_from}`;
    if (filters.created_to) return `Until ${filters.created_to}`;
    return 'Any';
}

function titleCase(value: string): string {
    return value.replace(/(^|[\s_-])([a-z])/g, (_, prefix, letter) => prefix + letter.toUpperCase());
}

export function TicketFilterChips({ queueId, filters, filterOptions, sort }: TicketFilterChipsProps) {
    const [openId, setOpenId] = useState<string | null>(null);

    const totalActive = useMemo(() => {
        return (
            filters.state.length +
            filters.source.length +
            filters.priority.length +
            (filters.created_from || filters.created_to ? 1 : 0)
        );
    }, [filters]);

    function applyFilters(next: QueueFilters) {
        const params: Record<string, string | number | string[] | number[]> = {
            page: 1,
            sort: sort.by,
            dir: sort.dir,
        };
        if (next.state.length > 0) params.state = next.state;
        if (next.source.length > 0) params.source = next.source;
        if (next.priority.length > 0) params.priority = next.priority;
        if (next.created_from) params.created_from = next.created_from;
        if (next.created_to) params.created_to = next.created_to;

        router.get(`/scp/queues/${queueId}`, params, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }

    function toggleString(key: 'state' | 'source', value: string) {
        const set = new Set(filters[key]);
        if (set.has(value)) set.delete(value); else set.add(value);
        applyFilters({ ...filters, [key]: Array.from(set) });
    }

    function togglePriority(id: number) {
        const set = new Set(filters.priority);
        if (set.has(id)) set.delete(id); else set.add(id);
        applyFilters({ ...filters, priority: Array.from(set) });
    }

    function setDatePreset(preset: { days: number | null }) {
        if (preset.days === null) {
            applyFilters({ ...filters, created_from: null, created_to: null });
            return;
        }
        applyFilters({
            ...filters,
            created_from: startOfRange(preset.days),
            created_to: isoDate(new Date()),
        });
    }

    function setCustomDate(field: 'created_from' | 'created_to', value: string) {
        applyFilters({ ...filters, [field]: value === '' ? null : value });
    }

    function clearAll() {
        applyFilters({ state: [], source: [], priority: [], created_from: null, created_to: null });
    }

    return (
        <div className="flex flex-wrap items-center gap-2.5">
            <Chip
                id="state"
                openId={openId}
                onOpenChange={setOpenId}
                icon={Layers01Icon}
                label="Status"
                count={filters.state.length}
            >
                <PopoverList>
                    {filterOptions.states.length === 0 ? (
                        <PopoverEmpty text="No statuses available." />
                    ) : (
                        filterOptions.states.map((state) => (
                            <CheckRow
                                key={state}
                                checked={filters.state.includes(state)}
                                onSelect={() => toggleString('state', state)}
                                label={titleCase(state)}
                            />
                        ))
                    )}
                </PopoverList>
                {filters.state.length > 0 && (
                    <PopoverFooter onClear={() => applyFilters({ ...filters, state: [] })} />
                )}
            </Chip>

            <Chip
                id="source"
                openId={openId}
                onOpenChange={setOpenId}
                icon={InboxIcon}
                label="Source"
                count={filters.source.length}
            >
                <PopoverList>
                    {filterOptions.sources.map((source) => (
                        <CheckRow
                            key={source}
                            checked={filters.source.includes(source)}
                            onSelect={() => toggleString('source', source)}
                            label={source}
                        />
                    ))}
                </PopoverList>
                {filters.source.length > 0 && (
                    <PopoverFooter onClear={() => applyFilters({ ...filters, source: [] })} />
                )}
            </Chip>

            <Chip
                id="priority"
                openId={openId}
                onOpenChange={setOpenId}
                icon={AlertCircleIcon}
                label="Priority"
                count={filters.priority.length}
            >
                <PopoverList>
                    {filterOptions.priorities.length === 0 ? (
                        <PopoverEmpty text="No priorities available." />
                    ) : (
                        filterOptions.priorities.map((priority) => (
                            <CheckRow
                                key={priority.id}
                                checked={filters.priority.includes(priority.id)}
                                onSelect={() => togglePriority(priority.id)}
                                label={priority.name}
                            />
                        ))
                    )}
                </PopoverList>
                {filters.priority.length > 0 && (
                    <PopoverFooter onClear={() => applyFilters({ ...filters, priority: [] })} />
                )}
            </Chip>

            <Chip
                id="date"
                openId={openId}
                onOpenChange={setOpenId}
                icon={Calendar01Icon}
                label="Date Added"
                count={filters.created_from || filters.created_to ? 1 : 0}
                value={filters.created_from || filters.created_to ? formatDateRange(filters) : undefined}
            >
                <PopoverList>
                    {DATE_PRESETS.map((preset) => {
                        const isActive = activeDatePreset(filters) === preset.id;
                        return (
                            <CheckRow
                                key={preset.id}
                                checked={isActive}
                                onSelect={() => setDatePreset(preset)}
                                label={preset.label}
                                rounded
                            />
                        );
                    })}
                </PopoverList>
                <div className="mt-2 border-t border-[#E2E0D8] px-2 py-3">
                    <p className="mb-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-[#A1A1AA]">Custom range</p>
                    <div className="space-y-2">
                        <DateInput
                            label="From"
                            value={filters.created_from ?? ''}
                            onChange={(value) => setCustomDate('created_from', value)}
                        />
                        <DateInput
                            label="To"
                            value={filters.created_to ?? ''}
                            onChange={(value) => setCustomDate('created_to', value)}
                        />
                    </div>
                </div>
            </Chip>

            <span className="hidden h-4 w-px bg-[#E2E0D8] sm:inline-block" aria-hidden />

            <button
                type="button"
                onClick={clearAll}
                disabled={totalActive === 0}
                className={cn(
                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs transition-colors',
                    totalActive === 0
                        ? 'cursor-not-allowed text-[#A1A1AA]'
                        : 'text-[#71717A] hover:bg-[#F4F2EB] hover:text-[#18181B]',
                )}
            >
                <HugeiconsIcon icon={Cancel01Icon} size={12} />
                Clear filters{totalActive > 0 ? ` (${totalActive})` : ''}
            </button>
        </div>
    );
}

interface ChipProps {
    id: string;
    openId: string | null;
    onOpenChange: (id: string | null) => void;
    icon: typeof FilterIcon;
    label: string;
    count: number;
    value?: string;
    children: ReactNode;
}

function Chip({ id, openId, onOpenChange, icon, label, count, value, children }: ChipProps) {
    const open = openId === id;
    const active = count > 0;
    return (
        <Popover open={open} onOpenChange={(next) => onOpenChange(next ? id : null)}>
            <PopoverTrigger
                render={
                    <button
                        type="button"
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                            active
                                ? 'border-[#F97316] bg-[#F3ECFF] text-[#3F4577]'
                                : 'border-[#E2E0D8] bg-white text-[#71717A] hover:border-[#CBD5E1] hover:text-[#18181B]',
                        )}
                    >
                        <HugeiconsIcon icon={icon} size={13} />
                        <span>{label}</span>
                        {value && <span className="text-[#A1A1AA]">·</span>}
                        {value && <span className="max-w-[12rem] truncate text-[#18181B]">{value}</span>}
                        {count > 0 && (
                            <span className="ml-0.5 inline-flex items-center justify-center rounded-full bg-[#F97316] px-1.5 text-[10px] font-semibold leading-4 text-white">
                                {count}
                            </span>
                        )}
                        <HugeiconsIcon icon={ArrowDown01Icon} size={11} className="opacity-60" />
                    </button>
                }
            />
            <PopoverContent className="w-60">
                {children}
            </PopoverContent>
        </Popover>
    );
}

function PopoverList({ children }: { children: ReactNode }) {
    return <div className="max-h-72 space-y-0.5 overflow-y-auto">{children}</div>;
}

function PopoverEmpty({ text }: { text: string }) {
    return <p className="px-2 py-3 text-xs text-[#71717A]">{text}</p>;
}

function PopoverFooter({ onClear }: { onClear: () => void }) {
    return (
        <div className="mt-1 border-t border-[#E2E0D8] pt-2">
            <button
                type="button"
                onClick={onClear}
                className="w-full rounded-md px-3 py-1.5 text-left text-xs font-medium text-[#71717A] transition-colors hover:bg-[#FAFAF8] hover:text-[#18181B]"
            >
                Clear
            </button>
        </div>
    );
}

interface CheckRowProps {
    checked: boolean;
    onSelect: () => void;
    label: string;
    rounded?: boolean;
}

function CheckRow({ checked, onSelect, label, rounded }: CheckRowProps) {
    return (
        <button
            type="button"
            onClick={onSelect}
            className={cn(
                'flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-sm text-[#18181B] transition-colors hover:bg-[#FAFAF8]',
            )}
        >
            <span
                className={cn(
                    'grid h-4 w-4 place-items-center border transition-colors',
                    rounded ? 'rounded-full' : 'rounded',
                    checked
                        ? 'border-[#F97316] bg-[#F97316] text-white'
                        : 'border-[#E2E0D8] bg-white text-transparent',
                )}
            >
                <HugeiconsIcon icon={Tick02Icon} size={10} strokeWidth={3} />
            </span>
            <span className="truncate">{label}</span>
        </button>
    );
}

function DateInput({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
    return (
        <label className="flex items-center justify-between gap-2 text-xs text-[#71717A]">
            <span className="w-12 shrink-0">{label}</span>
            <input
                type="date"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="w-full rounded-md border border-[#E2E0D8] bg-white px-2 py-1 text-xs text-[#18181B] focus:border-[#F97316] focus:outline-none focus:ring-2 focus:ring-[#F97316]/20"
            />
        </label>
    );
}
