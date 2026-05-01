import { type ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import DashboardLayout from '@/layouts/DashboardLayout';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    Settings01Icon,
    UserMultiple02Icon,
    SecurityCheckIcon,
    Building04Icon,
    MessageMultiple02Icon,
    UserGroupIcon,
    QuestionIcon,
} from '@hugeicons/core-free-icons';

interface AdminLayoutProps extends React.ComponentProps<typeof DashboardLayout> {
    activeAdminNav?: string;
    children: ReactNode;
}

const ADMIN_NAV_ITEMS = [
    { id: 'settings', label: 'Settings', icon: Settings01Icon, href: '/scp/admin/settings' },
    { id: 'agents', label: 'Agents', icon: UserMultiple02Icon, href: '/scp/admin/agents' },
    { id: 'staff', label: 'Staff', icon: UserMultiple02Icon, href: '/admin/staff' },
    { id: 'roles', label: 'Roles', icon: SecurityCheckIcon, href: '/scp/admin/roles' },
    { id: 'teams', label: 'Teams', icon: UserGroupIcon, href: '/admin/teams' },
    { id: 'canned-responses', label: 'Canned Responses', icon: MessageMultiple02Icon, href: '/admin/canned-responses' },
    { id: 'email-config', label: 'Email Config', icon: Settings01Icon, href: '/admin/email-config' },
    { id: 'help-topics', label: 'Help Topics', icon: QuestionIcon, href: '/admin/help-topics' },
    { id: 'filters', label: 'Filters', icon: Settings01Icon, href: '/admin/filters' },
    { id: 'slas', label: 'SLAs', icon: Settings01Icon, href: '/admin/slas' },
    { id: 'departments', label: 'Departments', icon: Building04Icon, href: '/admin/departments' },
];

export function AdminLayout({ activeAdminNav, children, ...props }: AdminLayoutProps) {
    return (
        <DashboardLayout {...props} contentClassName="w-full max-w-7xl mx-auto">
            <div className="flex flex-col md:flex-row gap-8 items-start">
                <aside className="w-full md:w-64 shrink-0">
                    <nav className="flex flex-col space-y-1">
                        <div className="mb-4 px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Admin Panel
                        </div>
                        {ADMIN_NAV_ITEMS.map((item) => {
                            const isActive = activeAdminNav === item.id;
                            return (
                                <Link
                                    key={item.id}
                                    href={item.href}
                                    className={cn(
                                        'flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition-colors',
                                        isActive
                                            ? 'bg-slate-100 text-slate-900 shadow-[inset_0_0_0_1px_rgba(226,232,240,0.8)]'
                                            : 'text-slate-600 hover:bg-white hover:text-slate-900'
                                    )}
                                >
                                    <HugeiconsIcon
                                        icon={item.icon}
                                        size={18}
                                        color={isActive ? '#5B619D' : '#94A3B8'}
                                    />
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>
                <div className="flex-1 min-w-0 bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    {children}
                </div>
            </div>
        </DashboardLayout>
    );
}

export default AdminLayout;
