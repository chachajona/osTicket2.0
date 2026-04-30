import { useMemo } from 'react';

import { cn } from '@/lib/utils';

interface AvatarProps {
    name: string | null | undefined;
    size?: number;
    className?: string;
}

const PALETTE = [
    'bg-rose-100 text-rose-700',
    'bg-amber-100 text-amber-800',
    'bg-emerald-100 text-emerald-700',
    'bg-sky-100 text-sky-700',
    'bg-violet-100 text-violet-700',
    'bg-pink-100 text-pink-700',
    'bg-teal-100 text-teal-700',
    'bg-indigo-100 text-indigo-700',
];

function initials(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || '?';
}

function paletteFor(name: string): string {
    let hash = 0;
    for (let index = 0; index < name.length; index += 1) {
        hash = (hash * 31 + name.charCodeAt(index)) >>> 0;
    }
    return PALETTE[hash % PALETTE.length];
}

export function Avatar({ name, size = 24, className }: AvatarProps) {
    const label = name?.trim() ?? '';
    const tone = useMemo(() => (label === '' ? 'bg-zinc-200 text-zinc-500' : paletteFor(label)), [label]);
    const text = label === '' ? '?' : initials(label);

    return (
        <span
            aria-hidden
            style={{ width: size, height: size, fontSize: size * 0.42 }}
            className={cn(
                'inline-flex shrink-0 items-center justify-center rounded-full font-semibold leading-none',
                tone,
                className,
            )}
        >
            {text}
        </span>
    );
}
