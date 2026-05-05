import { useState, type ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { HugeiconsIcon } from '@hugeicons/react';
import { ArrowDown01Icon, ArrowRight01Icon } from '@hugeicons/core-free-icons';

interface FormSectionProps {
    title: ReactNode;
    description?: ReactNode;
    children: ReactNode;
    collapsible?: boolean;
    defaultExpanded?: boolean;
    className?: string;
}

export function FormSection({
    title,
    description,
    children,
    collapsible = false,
    defaultExpanded = true,
    className,
}: FormSectionProps) {
    const [expanded, setExpanded] = useState(defaultExpanded);

    const toggle = () => {
        if (collapsible) {
            setExpanded(!expanded);
        }
    };

    return (
        <section className={cn('rounded-xl border border-slate-200 bg-white shadow-sm', className)}>
            <div
                className={cn(
                    'flex items-start justify-between px-6 py-5',
                    collapsible && 'cursor-pointer hover:bg-slate-50 transition-colors',
                    expanded ? 'border-b border-slate-100' : ''
                )}
                onClick={toggle}
                role={collapsible ? 'button' : undefined}
                tabIndex={collapsible ? 0 : undefined}
                onKeyDown={(e) => {
                    if (collapsible && (e.key === 'Enter' || e.key === ' ')) {
                        e.preventDefault();
                        toggle();
                    }
                }}
            >
                <div className="flex-1 pr-4">
                    <h3 className="text-lg font-medium tracking-tight text-slate-900">{title}</h3>
                    {description && (
                        <p className="mt-1 text-sm text-slate-500">{description}</p>
                    )}
                </div>
                {collapsible && (
                    <div className="flex shrink-0 items-center justify-center h-8 w-8 rounded-md text-slate-400 hover:text-slate-600">
                        <HugeiconsIcon
                            icon={expanded ? ArrowDown01Icon : ArrowRight01Icon}
                            size={20}
                        />
                    </div>
                )}
            </div>

            {expanded && (
                <div className="p-6">
                    {children}
                </div>
            )}
        </section>
    );
}

export default FormSection;
