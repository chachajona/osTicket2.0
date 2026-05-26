import { forwardRef, useEffect, useImperativeHandle, useState } from 'react';
import { cn } from '@/lib/utils';

interface StaffItem {
    id: number;
    name: string;
    username: string;
}

interface MentionListProps {
    items: StaffItem[];
    command: (item: StaffItem) => void;
}

export interface MentionListHandle {
    onKeyDown: (props: { event: KeyboardEvent }) => boolean;
}

export const MentionList = forwardRef<MentionListHandle, MentionListProps>(
    function MentionList({ items, command }, ref) {
        const [selectedIndex, setSelectedIndex] = useState(0);

        useEffect(() => {
            setSelectedIndex(0);
        }, [items]);

        useImperativeHandle(ref, () => ({
            onKeyDown: ({ event }) => {
                if (event.key === 'ArrowUp') {
                    setSelectedIndex((i) => (i + items.length - 1) % Math.max(items.length, 1));
                    return true;
                }
                if (event.key === 'ArrowDown') {
                    setSelectedIndex((i) => (i + 1) % Math.max(items.length, 1));
                    return true;
                }
                if ((event.key === 'Enter' || event.key === 'Tab') && items[selectedIndex]) {
                    command(items[selectedIndex]);
                    return true;
                }
                return false;
            },
        }));

        if (items.length === 0) {
            return (
                <div className="inline-flex items-center rounded-lg border border-[#E2E0D8] bg-white px-3 py-2 text-xs text-[#A1A1AA] shadow-[0_4px_12px_-2px_rgba(24,24,27,0.12)]">
                    No staff found
                </div>
            );
        }

        return (
            <div className="z-50 w-56 rounded-lg border border-[#E2E0D8] bg-white p-1 shadow-[0_4px_12px_-2px_rgba(24,24,27,0.12)]">
                {items.map((item, index) => (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => command(item)}
                        className={cn(
                            'flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left transition-colors duration-100',
                            index === selectedIndex ? 'bg-[#FAFAF8]' : 'hover:bg-[#FAFAF8]'
                        )}
                    >
                        <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#E2E0D8] text-[9px] font-semibold text-[#18181B]">
                            {item.name.split(' ').map((p) => p[0]).join('').slice(0, 2).toUpperCase()}
                        </span>
                        <span className="min-w-0 flex-1">
                            <div className="truncate text-[13px] font-medium text-[#18181B]">{item.name}</div>
                            <div className="truncate text-[11px] text-[#A1A1AA]">@{item.username}</div>
                        </span>
                    </button>
                ))}
            </div>
        );
    }
);
