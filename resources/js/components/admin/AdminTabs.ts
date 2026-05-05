import {
    DashboardSquare01Icon,
    Settings01Icon,
    ToolsIcon,
    Mail01Icon,
    UserMultiple02Icon,
} from '@hugeicons/core-free-icons';

export interface AdminSubItem {
    id: string;
    label: string;
    href: string | null;
    enabled: boolean;
}

export interface AdminTopTab {
    id: string;
    label: string;
    icon: typeof DashboardSquare01Icon;
    defaultSubId: string | null;
    submenu: AdminSubItem[];
}

// Legacy IA from osTicket AdminNav::getTabs() / getSubMenus().
// Items marked enabled=false render as disabled placeholders so the IA
// shape is preserved while Phase 2a only ships 9 surfaces.
//
// Divergence from legacy:
//   - "Canned Responses" is added under Manage as an additive item
//     (legacy exposes it inline from ticket compose; we need a top-level
//     entry for admins).
export const ADMIN_TABS: AdminTopTab[] = [
    {
        id: 'dashboard',
        label: 'Dashboard',
        icon: DashboardSquare01Icon,
        defaultSubId: null,
        submenu: [
            { id: 'logs', label: 'System Logs', href: null, enabled: false },
            { id: 'audits', label: 'Audit Logs', href: null, enabled: false },
            { id: 'system', label: 'Information', href: null, enabled: false },
        ],
    },
    {
        id: 'settings',
        label: 'Settings',
        icon: Settings01Icon,
        defaultSubId: null,
        submenu: [
            { id: 'company', label: 'Company', href: null, enabled: false },
            { id: 'system', label: 'System', href: null, enabled: false },
            { id: 'tickets', label: 'Tickets', href: null, enabled: false },
            { id: 'tasks', label: 'Tasks', href: null, enabled: false },
            { id: 'agents', label: 'Agents', href: null, enabled: false },
            { id: 'users', label: 'Users', href: null, enabled: false },
            { id: 'kb', label: 'Knowledgebase', href: null, enabled: false },
        ],
    },
    {
        id: 'manage',
        label: 'Manage',
        icon: ToolsIcon,
        defaultSubId: 'help-topics',
        submenu: [
            { id: 'help-topics', label: 'Help Topics', href: '/admin/help-topics', enabled: true },
            { id: 'filters', label: 'Filters', href: '/admin/filters', enabled: true },
            { id: 'slas', label: 'SLA', href: '/admin/slas', enabled: true },
            { id: 'canned-responses', label: 'Canned Responses', href: '/admin/canned-responses', enabled: true },
            { id: 'schedules', label: 'Schedules', href: null, enabled: false },
            { id: 'api', label: 'API', href: null, enabled: false },
            { id: 'pages', label: 'Pages', href: null, enabled: false },
            { id: 'forms', label: 'Forms', href: null, enabled: false },
            { id: 'lists', label: 'Lists', href: null, enabled: false },
            { id: 'plugins', label: 'Plugins', href: null, enabled: false },
        ],
    },
    {
        id: 'emails',
        label: 'Emails',
        icon: Mail01Icon,
        defaultSubId: 'email-config',
        submenu: [
            { id: 'email-config', label: 'Emails', href: '/admin/email-config', enabled: true },
            { id: 'email-settings', label: 'Settings', href: null, enabled: false },
            { id: 'banlist', label: 'Banlist', href: null, enabled: false },
            { id: 'templates', label: 'Templates', href: null, enabled: false },
            { id: 'diagnostic', label: 'Diagnostic', href: null, enabled: false },
        ],
    },
    {
        id: 'agents',
        label: 'Agents',
        icon: UserMultiple02Icon,
        defaultSubId: 'staff',
        submenu: [
            { id: 'staff', label: 'Agents', href: '/admin/staff', enabled: true },
            { id: 'teams', label: 'Teams', href: '/admin/teams', enabled: true },
            { id: 'roles', label: 'Roles', href: '/admin/roles', enabled: true },
            { id: 'departments', label: 'Departments', href: '/admin/departments', enabled: true },
        ],
    },
];

export const ADMIN_TAB_MAP: Record<string, { tabId: string; subId: string }> = ADMIN_TABS
    .flatMap((tab) =>
        tab.submenu.map((sub) => [sub.id, { tabId: tab.id, subId: sub.id }] as const),
    )
    .reduce<Record<string, { tabId: string; subId: string }>>((acc, [id, value]) => {
        acc[id] = value;
        return acc;
    }, {});
