import { cn } from '@/lib/utils';

interface PriorityBadgeProps {
    priority?: string | null;
    className?: string;
}

const PRIORITY_TONES: Record<string, string> = {
    emergency: 'border-rose-300 bg-rose-50 text-rose-700',
    critical: 'border-rose-300 bg-rose-50 text-rose-700',
    urgent: 'border-rose-300 bg-rose-50 text-rose-700',
    high: 'border-amber-300 bg-amber-50 text-amber-800',
    normal: 'border-[#E2E8F0] bg-[#F8FAFC] text-[#475569]',
    medium: 'border-[#E2E8F0] bg-[#F8FAFC] text-[#475569]',
    low: 'border-[#E2E8F0] bg-[#F8FAFC] text-[#94A3B8]',
};

const NEUTRAL = 'border-[#E2E8F0] bg-[#F8FAFC] text-[#64748B]';

export function PriorityBadge({ priority, className }: PriorityBadgeProps) {
    if (!priority) {
        return <span className="text-[#94A3B8]">—</span>;
    }

    const tone = PRIORITY_TONES[priority.toLowerCase()] ?? NEUTRAL;

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-medium',
                tone,
                className,
            )}
        >
            {priority}
        </span>
    );
}
