import { type ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { SetPageHeader } from '@/layouts/AppShell';

interface PageHeaderProps {
    title?: string;
    subtitle?: string;
    eyebrow?: string;
    headerLeft?: ReactNode;
    headerActions?: ReactNode;
    className?: string;
}

export function PageHeader({ title, subtitle, eyebrow, headerLeft, headerActions, className }: PageHeaderProps) {
    return (
        <SetPageHeader>
            <div className={cn('flex items-start justify-between gap-4', className)}>
                <div className="min-w-0 flex-1">
                    {headerLeft ?? (
                        <>
                            {eyebrow && <div className="auth-eyebrow mb-2">{eyebrow}</div>}
                            {title && <h1 className="text-[18px] font-medium leading-[22.75px] tracking-[-0.02em] text-[#18181B]">{title}</h1>}
                            {subtitle && <p className="mt-1 text-[14px] leading-[22.75px] text-[#A1A1AA]">{subtitle}</p>}
                        </>
                    )}
                </div>
                {headerActions && (
                    <div className="flex shrink-0 items-center gap-2 sm:gap-3">{headerActions}</div>
                )}
            </div>
        </SetPageHeader>
    );
}
