import { useState, type ReactNode } from 'react';
import { HugeiconsIcon } from '@hugeicons/react';
import { cn } from '@/lib/utils';
import {
    ArrowDown01Icon,
    Mail01Icon,
    Message01Icon,
    SmartPhone01Icon,
    ComputerIcon,
    Cancel01Icon
} from '@hugeicons/core-free-icons';

export function PriorityDot({ priority, size = 'default' }: { priority: string; size?: 'sm' | 'default' }) {
    const colors: Record<string, string> = { High: 'bg-red-600', Medium: 'bg-yellow-500', Low: 'bg-green-600', Urgent: 'bg-red-600' };
    const textColors: Record<string, string> = { High: 'text-red-600', Medium: 'text-yellow-600', Low: 'text-green-600', Urgent: 'text-red-600' };

    const bgClass = colors[priority] || 'bg-[#A1A1AA]';
    const textClass = textColors[priority] || 'text-[#A1A1AA]';

    return (
        <div className="flex items-center gap-1.5">
            <div className={cn('h-1.5 w-1.5 shrink-0 rounded-full', bgClass)} />
            <span className={cn('font-medium', size === 'sm' ? 'text-xs' : 'text-[13px]', textClass)}>
                {priority}
            </span>
        </div>
    );
}

export function TypeBadge({ type }: { type: string }) {
    const styles: Record<string, string> = {
        Incident: 'bg-red-50 text-red-600 border-red-200',
        Problem: 'bg-orange-50 text-orange-600 border-orange-200',
        Question: 'bg-green-50 text-green-600 border-green-200',
        Suggestion: 'bg-indigo-50 text-indigo-600 border-indigo-200',
    };
    const dotColors: Record<string, string> = {
        Incident: 'bg-red-600',
        Problem: 'bg-orange-600',
        Question: 'bg-green-600',
        Suggestion: 'bg-indigo-600',
    };

    const styleClass = styles[type] || styles.Incident;
    const dotClass = dotColors[type] || dotColors.Incident;

    return (
        <span className={cn('inline-flex items-center gap-1 rounded-[3px] border px-2.5 py-0.5 text-[11px] font-medium font-sans', styleClass)}>
            <div className={cn('h-1.5 w-1.5 rounded-full', dotClass)} />
            {type}
        </span>
    );
}

export function ClientCell({ name }: { name: string }) {
    const initials = name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();

    return (
        <div className="flex items-center gap-2">
            <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#EC4899] text-[10px] font-semibold tracking-[0.02em] text-white">
                {initials}
            </div>
            <span className="text-[13px]">{name}</span>
        </div>
    );
}

export function PrioritySegmented({ value, onChange }: { value: string; onChange: (val: string) => void }) {
    const opts = [
        { label: 'Low', color: 'bg-green-600' },
        { label: 'Medium', color: 'bg-yellow-500' },
        { label: 'High', color: 'bg-red-600' },
    ];
    return (
        <div className="flex overflow-hidden rounded-sm border border-[#E2E0D8]">
            {opts.map((o, i) => {
                const isActive = value === o.label;
                return (
                    <button
                        key={o.label}
                        type="button"
                        onClick={() => onChange(o.label)}
                        className={cn(
                            'flex flex-1 items-center justify-center gap-1.5 py-1.5 text-xs font-medium transition-all font-sans',
                            isActive ? 'bg-[#F4F2EB] text-[#18181B] outline outline-2 outline-[#18181B] -outline-offset-2 rounded-[3px] z-10' : 'bg-white text-[#71717A] hover:bg-[#FAFAF8]',
                            i < 2 && !isActive ? 'border-r border-[#E2E0D8]' : ''
                        )}
                    >
                        <div className={cn('h-1.5 w-1.5 rounded-full', o.color)} />
                        {o.label}
                    </button>
                );
            })}
        </div>
    );
}

export function TagChip({ label, onRemove }: { label: string; onRemove?: () => void }) {
    return (
        <span className="inline-flex items-center gap-1 rounded-[3px] bg-[#F4F2EB] px-2 py-0.5 text-xs font-medium text-[#18181B]">
            {label}
            {onRemove && (
                <button type="button" onClick={onRemove} className="flex p-0 text-[#A1A1AA] hover:text-[#18181B]">
                    <HugeiconsIcon icon={Cancel01Icon} size={14} />
                </button>
            )}
        </span>
    );
}

export function SplitButton({ label, onClick }: { label: string; onClick?: () => void }) {
    const [hov, setHov] = useState(false);
    return (
        <div className="flex overflow-hidden rounded-sm shadow-[0_2px_6px_rgba(249,115,22,0.2)]">
            <button
                type="button"
                onClick={onClick}
                onMouseEnter={() => setHov(true)}
                onMouseLeave={() => setHov(false)}
                className={cn(
                    'whitespace-nowrap px-4 py-2 font-sans text-[13px] font-medium text-white transition-colors',
                    hov ? 'bg-orange-600' : 'bg-[#F97316]'
                )}
            >
                {label}
            </button>
            <div className="w-[1px] bg-white/30" />
            <button
                type="button"
                onMouseEnter={() => setHov(true)}
                onMouseLeave={() => setHov(false)}
                className={cn(
                    'flex items-center px-2.5 text-white transition-colors',
                    hov ? 'bg-orange-600' : 'bg-[#F97316]'
                )}
            >
                <HugeiconsIcon icon={ArrowDown01Icon} size={14} />
            </button>
        </div>
    );
}

export function IconBtn({
    icon,
    onClick,
    size = 34,
    tooltip,
    className
}: {
    icon: any;
    onClick?: () => void;
    size?: number;
    tooltip?: string;
    className?: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={tooltip}
            style={{ width: size, height: size }}
            className={cn(
                'flex shrink-0 items-center justify-center rounded-sm border border-[#E2E0D8] bg-white text-[#A1A1AA] transition-colors hover:bg-[#F4F2EB] hover:text-[#18181B]',
                className
            )}
        >
            <HugeiconsIcon icon={icon} size={size <= 30 ? 14 : 16} />
        </button>
    );
}

export function ChannelPill({ channel }: { channel: string }) {
    const icons: Record<string, any> = {
        Email: Mail01Icon,
        Whatsapp: Message01Icon,
        Phone: SmartPhone01Icon,
        Portal: ComputerIcon
    };
    const Icon = icons[channel] || Mail01Icon;
    return (
        <span className="inline-flex items-center gap-1 rounded-full border border-[#E2E0D8] bg-[#F4F2EB] px-2.5 py-0.5 text-xs font-medium text-[#18181B]">
            <HugeiconsIcon icon={Icon} size={13} className={channel === 'Whatsapp' ? 'text-[#25D366]' : 'text-[#A1A1AA]'} />
            {channel}
            <HugeiconsIcon icon={ArrowDown01Icon} size={12} className="ml-0.5 text-[#71717A]" />
        </span>
    );
}

export function FromPill({ from }: { from: string }) {
    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-[#71717A] px-2.5 py-0.5 text-xs font-medium text-white">
            {from}
            <HugeiconsIcon icon={ArrowDown01Icon} size={12} className="ml-0.5 text-white/60" />
        </span>
    );
}
