import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

interface FormGridProps {
    children: ReactNode;
    className?: string;
    columns?: 1 | 2 | 3;
}

export function FormGrid({ children, className, columns = 2 }: FormGridProps) {
    return (
        <div
            className={cn(
                'grid gap-6',
                {
                    'grid-cols-1': columns === 1,
                    'grid-cols-1 md:grid-cols-2': columns === 2,
                    'grid-cols-1 md:grid-cols-3': columns === 3,
                },
                className
            )}
        >
            {children}
        </div>
    );
}

export default FormGrid;
