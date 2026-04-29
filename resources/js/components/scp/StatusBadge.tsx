import { cn } from '@/lib/utils';

interface StatusBadgeProps {
    status?: string | null;
    state?: string | null;
    className?: string;
}

const STATE_TONES: Record<string, string> = {
    open: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    answered: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    pending: 'border-amber-200 bg-amber-50 text-amber-800',
    overdue: 'border-rose-200 bg-rose-50 text-rose-700',
    resolved: 'border-sky-200 bg-sky-50 text-sky-700',
    closed: 'border-[#E2E8F0] bg-[#F1F5F9] text-[#475569]',
    archived: 'border-[#E2E8F0] bg-[#F1F5F9] text-[#475569]',
    deleted: 'border-rose-200 bg-rose-50 text-rose-700',
};

const NEUTRAL = 'border-[#E2E8F0] bg-[#F8FAFC] text-[#64748B]';

export function StatusBadge({ status, state, className }: StatusBadgeProps) {
    if (!status && !state) return null;

    const tone = STATE_TONES[(state ?? '').toLowerCase()] ?? NEUTRAL;
    const label = status ?? state ?? '';

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em]',
                tone,
                className,
            )}
        >
            <span className="h-1.5 w-1.5 rounded-full bg-current" aria-hidden />
            {label}
        </span>
    );
}
