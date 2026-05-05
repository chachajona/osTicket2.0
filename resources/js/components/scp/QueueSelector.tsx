import { router } from '@inertiajs/react';
import { useMemo } from 'react';
import { HugeiconsIcon } from '@hugeicons/react';
import { Folder01Icon, Search01Icon, UserStar01Icon } from '@hugeicons/core-free-icons';

import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

import type { QueueNavigation, QueueNode, SavedSearch } from './QueueTypes';

interface QueueSelectorProps {
    navigation: QueueNavigation;
    activeQueueId?: number;
    placeholder?: string;
    variant?: 'default' | 'heading';
    label?: string;
}

interface FlatItem {
    id: number;
    title: string;
    depth: number;
    section: 'queues' | 'personal' | 'savedSearches';
}

function flattenTree(nodes: QueueNode[], section: FlatItem['section'], depth = 0, acc: FlatItem[] = []): FlatItem[] {
    for (const node of nodes) {
        acc.push({ id: node.id, title: node.title, depth, section });
        if (node.children?.length) {
            flattenTree(node.children, section, depth + 1, acc);
        }
    }
    return acc;
}

function flattenSavedSearches(items: SavedSearch[]): FlatItem[] {
    return items.map((item) => ({ id: item.id, title: item.title, depth: 0, section: 'savedSearches' as const }));
}

function indentPad(depth: number): string {
    return '  '.repeat(depth);
}

export function QueueSelector({ navigation, activeQueueId, placeholder = 'Select a queue', variant = 'default', label }: QueueSelectorProps) {
    const groups = useMemo(
        () => ({
            queues: flattenTree(navigation.queues, 'queues'),
            personal: flattenTree(navigation.personal, 'personal'),
            savedSearches: flattenSavedSearches(navigation.savedSearches),
        }),
        [navigation],
    );

    const value = activeQueueId !== undefined ? String(activeQueueId) : undefined;

    function handleChange(next: string | null) {
        if (!next) return;
        router.get(`/scp/queues/${next}`);
    }

    const totalCount =
        groups.queues.length + groups.personal.length + groups.savedSearches.length;

    return (
        <Select value={value} onValueChange={handleChange}>
            {variant === 'heading' ? (
                <SelectTrigger className="!h-auto gap-2 rounded-none border-0 bg-transparent px-0 py-0 font-display text-[28px] font-medium tracking-tight text-[#0F172A] shadow-none focus-visible:border-0 focus-visible:ring-0">
                    <span className="flex flex-1 text-left">{label ?? placeholder}</span>
                </SelectTrigger>
            ) : (
                <SelectTrigger className="h-9 min-w-[260px] rounded-md border-[#E2E0D8] bg-white px-3 text-sm">
                    <HugeiconsIcon icon={Folder01Icon} size={14} className="text-[#A1A1AA]" />
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
            )}
            <SelectContent align="start" className="min-w-[280px] rounded-xl">
                {totalCount === 0 ? (
                    <p className="px-3 py-3 text-xs text-[#94A3B8]">No queues are visible to you yet.</p>
                ) : (
                    <>
                        {groups.queues.length > 0 && (
                            <SelectGroup>
                                <SelectLabel>
                                    <span className="inline-flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-[#94A3B8]">
                                        <HugeiconsIcon icon={Folder01Icon} size={11} /> Queues
                                    </span>
                                </SelectLabel>
                                {groups.queues.map((item) => (
                                    <SelectItem key={`q-${item.id}`} value={String(item.id)}>
                                        <span>
                                            {indentPad(item.depth)}
                                            {item.title}
                                        </span>
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        )}

                        {groups.personal.length > 0 && (
                            <>
                                {groups.queues.length > 0 && <SelectSeparator />}
                                <SelectGroup>
                                    <SelectLabel>
                                        <span className="inline-flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-[#94A3B8]">
                                            <HugeiconsIcon icon={UserStar01Icon} size={11} /> Personal
                                        </span>
                                    </SelectLabel>
                                    {groups.personal.map((item) => (
                                        <SelectItem key={`p-${item.id}`} value={String(item.id)}>
                                            <span>
                                                {indentPad(item.depth)}
                                                {item.title}
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </>
                        )}

                        {groups.savedSearches.length > 0 && (
                            <>
                                {(groups.queues.length > 0 || groups.personal.length > 0) && <SelectSeparator />}
                                <SelectGroup>
                                    <SelectLabel>
                                        <span className="inline-flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-[#94A3B8]">
                                            <HugeiconsIcon icon={Search01Icon} size={11} /> My Advanced Searches
                                        </span>
                                    </SelectLabel>
                                    {groups.savedSearches.map((item) => (
                                        <SelectItem key={`s-${item.id}`} value={String(item.id)}>
                                            <span>{item.title}</span>
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </>
                        )}
                    </>
                )}
            </SelectContent>
        </Select>
    );
}
