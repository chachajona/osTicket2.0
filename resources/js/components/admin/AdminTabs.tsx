import { Link } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import { cn } from '@/lib/utils';
import { ADMIN_TABS, type AdminTopTab, type AdminSubItem } from './AdminTabs.constants';

interface AdminTabsProps {
    activeSubId?: string;
}

function findActiveTab(activeSubId?: string): AdminTopTab | undefined {
    if (!activeSubId) return undefined;
    return ADMIN_TABS.find((tab) => tab.submenu.some((sub) => sub.id === activeSubId));
}

function topTabClasses(isActive: boolean): string {
    return cn(
        'inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition-colors',
        isActive
            ? 'border-[#5B619D] text-[#0F172A]'
            : 'border-transparent text-[#64748B] hover:border-[#CBD5E1] hover:text-[#0F172A]',
    );
}

function subItemClasses(isActive: boolean, enabled: boolean): string {
    if (!enabled) {
        return 'inline-flex cursor-not-allowed items-center px-3 py-2 text-sm font-medium text-[#94A3B8]';
    }
    return cn(
        'inline-flex items-center rounded-md px-3 py-2 text-sm font-medium transition-colors',
        isActive
            ? 'bg-[#F1F5F9] text-[#0F172A] shadow-[inset_0_0_0_1px_rgba(226,232,240,0.8)]'
            : 'text-[#64748B] hover:bg-white hover:text-[#0F172A]',
    );
}

export function AdminTabs({ activeSubId }: AdminTabsProps) {
    const activeTab = findActiveTab(activeSubId) ?? ADMIN_TABS[2]; // Default to Manage if nothing matches

    return (
        <nav aria-label="Admin navigation" className="border-b border-[#E2E8F0] bg-white">
            <div className="mx-auto flex max-w-7xl items-center gap-1 overflow-x-auto px-4 sm:px-6 xl:px-10">
                {ADMIN_TABS.map((tab) => {
                    const isActive = tab.id === activeTab.id;
                    const firstEnabled = tab.submenu.find((sub) => sub.enabled);
                    const href = firstEnabled?.href ?? null;
                    const className = topTabClasses(isActive);
                    const content = (
                        <>
                            <HugeiconsIcon icon={tab.icon} size={16} />
                            <span>{tab.label}</span>
                        </>
                    );
                    if (!href) {
                        return (
                            <button key={tab.id} type="button" disabled aria-disabled className={cn(className, 'cursor-not-allowed opacity-60')}>
                                {content}
                            </button>
                        );
                    }
                    return (
                        <Link key={tab.id} href={href} className={className} aria-current={isActive ? 'page' : undefined}>
                            {content}
                        </Link>
                    );
                })}
            </div>

            <div className="border-t border-[#E2E8F0] bg-[#F8FAFC]">
                <div className="mx-auto flex max-w-7xl items-center gap-1 overflow-x-auto px-4 py-2 sm:px-6 xl:px-10">
                    {activeTab.submenu.map((sub: AdminSubItem) => {
                        const isActive = sub.id === activeSubId;
                        const className = subItemClasses(isActive, sub.enabled);
                        if (!sub.enabled || !sub.href) {
                            return (
                                <span key={sub.id} className={className} aria-disabled title="Not yet implemented">
                                    {sub.label}
                                </span>
                            );
                        }
                        return (
                            <Link key={sub.id} href={sub.href} className={className} aria-current={isActive ? 'page' : undefined}>
                                {sub.label}
                            </Link>
                        );
                    })}
                </div>
            </div>
        </nav>
    );
}

export default AdminTabs;