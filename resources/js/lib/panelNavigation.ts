import {
    DashboardSquare01Icon,
    Ticket01Icon,
    InboxIcon,
    Notification01Icon,
    BookOpen01Icon,
    CustomerService01Icon,
    Message01Icon,
    BarChartIcon,
} from '@hugeicons/core-free-icons';

import { ADMIN_TABS } from '@/components/admin/AdminTabs';

export interface NavSubItem {
    id: string;
    label: string;
    href?: string | null;
    enabled?: boolean;
}

export interface NavItem {
    id: string;
    labelKey: string;
    icon: typeof DashboardSquare01Icon;
    href?: string | null;
    children?: NavSubItem[];
}

export const PANEL_NAV: Record<'scp' | 'admin', NavItem[]> = {
    scp: [
        { id: 'dashboard', labelKey: 'nav.dashboard', icon: DashboardSquare01Icon, href: '/scp' },
        { id: 'queues', labelKey: 'nav.tickets', icon: Ticket01Icon, href: '/scp/queues' },
        { id: 'inbox', labelKey: 'nav.inbox', icon: InboxIcon },
        { id: 'notifications', labelKey: 'nav.notifications', icon: Notification01Icon },
        { id: 'knowledge', labelKey: 'nav.knowledgebase', icon: BookOpen01Icon },
        { id: 'customers', labelKey: 'nav.customers', icon: CustomerService01Icon },
        { id: 'forum', labelKey: 'nav.forum', icon: Message01Icon },
        { id: 'reports', labelKey: 'nav.reports', icon: BarChartIcon },
    ],
    admin: ADMIN_TABS.map((tab) => {
        const firstEnabled = tab.submenu.find((sub) => sub.enabled);
        return {
            id: tab.id,
            labelKey: tab.label,
            icon: tab.icon,
            href: firstEnabled ? firstEnabled.href : undefined,
            children: tab.submenu.map((sub) => ({
                id: sub.id,
                label: sub.label,
                href: sub.href,
                enabled: sub.enabled,
            })),
        };
    }),
};
