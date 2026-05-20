import { forwardRef, useEffect, useImperativeHandle, useState } from 'react';
import { cn } from '@/lib/utils';

interface CannedItem {
    id: number;
    title: string;
    response: string;
}

interface SlashCommandListProps {
    items: CannedItem[];
    command: (item: CannedItem) => void;
}

export interface SlashCommandListHandle {
    onKeyDown: (props: { event: KeyboardEvent }) => boolean;
}

function stripHtml(html: string): string {
    return html.replace(/<[^>]+>/g, '').trim().slice(0, 60);
}

export const SlashCommandList = forwardRef<SlashCommandListHandle, SlashCommandListProps>(
    function SlashCommandList({ items, command }, ref) {
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
                    No responses found
                </div>
            );
        }

        return (
            <div className="z-50 w-72 rounded-lg border border-[#E2E0D8] bg-white p-1 shadow-[0_4px_12px_-2px_rgba(24,24,27,0.12)]">
                <div className="px-2.5 py-1.5 text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">
                    Canned Responses
                </div>
                {items.map((item, index) => (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => command(item)}
                        className={cn(
                            'flex w-full flex-col rounded-md px-2.5 py-1.5 text-left transition-colors duration-100',
                            index === selectedIndex ? 'bg-[#FAFAF8]' : 'hover:bg-[#FAFAF8]'
                        )}
                    >
                        <div className="text-[13px] font-medium text-[#18181B]">{item.title}</div>
                        <div className="truncate text-[11px] text-[#A1A1AA]">{stripHtml(item.response)}&hellip;</div>
                    </button>
                ))}
            </div>
        );
    }
);
