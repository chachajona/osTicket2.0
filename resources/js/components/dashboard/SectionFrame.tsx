import type { ReactNode } from 'react';

import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export function SectionFrame({ children, className }: { children: ReactNode; className?: string }) {
    return <Card className={cn('overflow-hidden rounded-none border-0 py-0 shadow-none ring-0', className)}>{children}</Card>;
}
