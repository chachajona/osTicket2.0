import { HugeiconsIcon } from '@hugeicons/react';
import {
    AlertCircleIcon,
    ArrowDown01Icon,
    Calendar01Icon,
    FilterIcon,
    InboxIcon,
    Layers01Icon,
} from '@hugeicons/core-free-icons';

import { cn } from '@/lib/utils';

interface FilterChip {
    id: string;
    label: string;
    icon: typeof FilterIcon;
}

const CHIPS: FilterChip[] = [
    { id: 'type', label: 'Type', icon: Layers01Icon },
    { id: 'source', label: 'Source', icon: InboxIcon },
    { id: 'priority', label: 'Priority', icon: AlertCircleIcon },
    { id: 'date', label: 'Date Added', icon: Calendar01Icon },
];

export function TicketFilterChips({ disabled = true }: { disabled?: boolean }) {
    return (
        <div className="flex items-center gap-5">
            {CHIPS.map(({ id, label, icon }) => (
                <button
                    key={id}
                    type="button"
                    disabled={disabled}
                    aria-disabled={disabled}
                    title={disabled ? 'Coming soon' : undefined}
                    className={cn(
                        'inline-flex items-center gap-1.5 text-sm transition-colors',
                        disabled
                            ? 'cursor-not-allowed text-zinc-400'
                            : 'text-zinc-600 hover:text-zinc-900',
                    )}
                >
                    <HugeiconsIcon icon={icon} size={16} />
                    {label}
                    <HugeiconsIcon icon={ArrowDown01Icon} size={12} className="opacity-60" />
                </button>
            ))}
            <span className="h-4 w-px bg-zinc-200" aria-hidden />
            <button
                type="button"
                disabled={disabled}
                aria-disabled={disabled}
                title={disabled ? 'Coming soon' : undefined}
                className={cn(
                    'inline-flex items-center gap-1.5 text-sm transition-colors',
                    disabled ? 'cursor-not-allowed text-zinc-400' : 'text-zinc-600 hover:text-zinc-900',
                )}
            >
                <HugeiconsIcon icon={FilterIcon} size={16} />
                Ticket Filters
            </button>
        </div>
    );
}
