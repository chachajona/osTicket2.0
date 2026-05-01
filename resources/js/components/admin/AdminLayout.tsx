import { type ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { AdminTabs } from './AdminTabs';
import { buttonVariants } from '@/components/ui/button';
import { HugeiconsIcon } from '@hugeicons/react';
import { ArrowLeft01Icon } from '@hugeicons/core-free-icons';
import { cn } from '@/lib/utils';

interface AdminLayoutProps extends Omit<React.ComponentProps<typeof DashboardLayout>, 'headerActions'> {
    activeAdminNav?: string;
    children: ReactNode;
}

function AdminHeaderActions() {
    return (
        <Link
            href="/scp"
            className={cn(
                buttonVariants({ variant: 'outline', size: 'sm' }),
                'rounded-[4px] border-[#E2E8F0] bg-white text-xs font-medium uppercase tracking-[0.12em] text-[#64748B] hover:border-[#C4A5F3] hover:bg-[#F8FAFC] hover:text-[#0F172A]',
            )}
        >
            <HugeiconsIcon icon={ArrowLeft01Icon} size={14} className="mr-1.5" />
            Agent Panel
        </Link>
    );
}

export function AdminLayout({ activeAdminNav, children, contentClassName, ...props }: AdminLayoutProps) {
    return (
        <DashboardLayout
            {...props}
            activeNav="admin"
            headerActions={<AdminHeaderActions />}
            contentClassName="w-full"
        >
            <div className="-mx-4 -mt-5 sm:-mx-6 sm:-mt-6 lg:-mx-8 xl:-mx-10 xl:-mt-8">
                <AdminTabs activeSubId={activeAdminNav} />
                <div className={cn('mx-auto max-w-7xl px-4 py-6 sm:px-6 xl:px-10', contentClassName)}>
                    {children}
                </div>
            </div>
        </DashboardLayout>
    );
}

export default AdminLayout;
