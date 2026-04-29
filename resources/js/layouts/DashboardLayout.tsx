import { Children, useEffect, useRef, useState, type ReactNode } from 'react';
import { Link, router } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import { useTranslation } from 'react-i18next';
import {
    ArrowLeft01Icon,
    ArrowRight01Icon,
    BarChartIcon,
    BookOpen01Icon,
    Cancel01Icon,
    CustomerService01Icon,
    DashboardSquare01Icon,
    InboxIcon,
    LogoutSquare01Icon,
    Menu01Icon,
    Message01Icon,
    Notification01Icon,
    Search01Icon,
    Settings01Icon,
    ShieldCheck,
    Ticket01Icon,
} from '@hugeicons/core-free-icons';

import { Button, buttonVariants } from '@/components/ui/button';
import {
    Card,
    CardContent,
} from '@/components/ui/card';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Kbd } from '@/components/ui/kbd';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

interface DashboardLayoutProps {
    title?: string;
    subtitle?: string;
    eyebrow?: string;
    activeNav?: string;
    headerLeft?: ReactNode;
    headerActions?: ReactNode;
    contentClassName?: string;
    searchQuery?: string;
    children: ReactNode;
}

interface NavItem {
    id: string;
    labelKey: string;
    icon: typeof DashboardSquare01Icon;
    href?: string;
}

const NAV_ITEMS: NavItem[] = [
    { id: 'dashboard', labelKey: 'nav.dashboard', icon: DashboardSquare01Icon, href: '/scp' },
    { id: 'queues', labelKey: 'nav.tickets', icon: Ticket01Icon, href: '/scp/queues' },
    { id: 'inbox', labelKey: 'nav.inbox', icon: InboxIcon },
    { id: 'notifications', labelKey: 'nav.notifications', icon: Notification01Icon },
    { id: 'knowledge', labelKey: 'nav.knowledgebase', icon: BookOpen01Icon },
    { id: 'customers', labelKey: 'nav.customers', icon: CustomerService01Icon },
    { id: 'forum', labelKey: 'nav.forum', icon: Message01Icon },
    { id: 'reports', labelKey: 'nav.reports', icon: BarChartIcon },
];

const CONVERSATION_ITEMS = [
    { id: 'call', label: 'Call', subtitle: '(123) 45678...', icon: CustomerService01Icon, badge: '1', badgeActive: true },
    { id: 'side-conversation', label: 'Side Conversation', subtitle: 'No new replies', icon: Message01Icon, badge: '0', badgeActive: false },
] as const;

const PINNED_TICKETS = [
    { label: '#TC-192 product inquiry...' },
    { label: '#TC-191 payment issue...' },
    { label: '+1 678-908-78...' },
] as const;

const COLLAPSE_STORAGE_KEY = 'scp.sidebar.collapsed';
const MOBILE_BREAKPOINT = '(max-width: 1023px)';

const navBaseClass = 'flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium font-body transition-colors duration-150';
const navCollapsedBaseClass = 'flex items-center justify-center rounded-md p-2.5 transition-colors duration-150';
const navActiveBg = 'bg-[#F1F5F9] text-[#0F172A] shadow-[inset_0_0_0_1px_rgba(226,232,240,0.8)]';
const navInactiveBg = 'text-[#64748B] hover:bg-white hover:text-[#0F172A]';
const navDisabledBg = 'cursor-not-allowed text-[#94A3B8] opacity-50';

const footerActionBaseClass = 'inline-flex items-center justify-center gap-2 rounded-md border px-3 py-2 text-xs font-medium transition-colors';
const footerActionActiveClass = `${footerActionBaseClass} border-[#CBD5E1] bg-[#F1F5F9] text-[#0F172A] shadow-[inset_0_0_0_1px_rgba(226,232,240,0.8)]`;
const footerActionInactiveClass = `${footerActionBaseClass} border-[#E2E8F0] bg-white text-[#64748B] hover:text-[#0F172A]`;

function navIconColor(activeNav: string | undefined, navItem: string, disabled = false): string {
    if (disabled) return '#CBD5E1';
    return activeNav === navItem ? '#5B619D' : '#94A3B8';
}

function getFooterActionClasses(activeNav: string | undefined, navItem: string): string {
    return activeNav === navItem ? footerActionActiveClass : footerActionInactiveClass;
}

function getFooterActionIconColor(activeNav: string | undefined, navItem: string): string {
    return activeNav === navItem ? '#5B619D' : '#94A3B8';
}

function useMediaQuery(query: string): boolean {
    const getInitial = () => {
        if (typeof window === 'undefined') return false;
        return window.matchMedia(query).matches;
    };

    const [matches, setMatches] = useState<boolean>(getInitial);

    useEffect(() => {
        if (typeof window === 'undefined') return;

        const mql = window.matchMedia(query);
        const onChange = (event: MediaQueryListEvent) => setMatches(event.matches);

        setMatches(mql.matches);
        mql.addEventListener('change', onChange);
        return () => mql.removeEventListener('change', onChange);
    }, [query]);

    return matches;
}

function DefaultHeaderActions() {
    const { t } = useTranslation();

    return (
        <>
            <Link
                href="/scp/queues"
                className={cn(buttonVariants({ variant: 'outline', size: 'sm' }), "rounded-[4px] border-[#E2E8F0] bg-white text-xs font-medium uppercase tracking-[0.12em] text-[#64748B] hover:border-[#C4A5F3] hover:bg-[#F8FAFC] hover:text-[#0F172A]")}
            >
                {t('dashboard.layout.my_queue')}
            </Link>
            <button
                type="button"
                disabled
                className={cn(buttonVariants({ size: 'sm' }), "rounded-[4px] bg-[#5B619D] px-4 text-xs font-medium uppercase tracking-[0.12em] text-white hover:bg-[#4F548C] disabled:cursor-not-allowed disabled:opacity-60 shadow-[0_10px_25px_-20px_rgba(91,97,157,0.7)]")}
            >
                {t('dashboard.layout.new_ticket')}
            </button>
        </>
    );
}

function SearchField({ defaultQuery = '' }: { defaultQuery?: string }) {
    const { t } = useTranslation();

    return (
        <form
            role="search"
            onSubmit={(event) => {
                event.preventDefault();
                const query = new FormData(event.currentTarget).get('q')?.toString().trim() ?? '';
                router.get('/scp/search', query === '' ? {} : { q: query });
            }}
        >
            <InputGroup>
                <InputGroupAddon align="inline-start">
                    <HugeiconsIcon icon={Search01Icon} size={16} />
                </InputGroupAddon>
                <InputGroupInput
                    key={defaultQuery}
                    name="q"
                    defaultValue={defaultQuery}
                    aria-label={t('dashboard.layout.search_tickets')}
                    placeholder={t('dashboard.layout.search_placeholder')}
                />
                <InputGroupAddon align="inline-end">
                    <Kbd>⌘</Kbd>
                    <Kbd>K</Kbd>
                </InputGroupAddon>
            </InputGroup>
        </form>
    );
}

interface NavLinkProps {
    item: NavItem;
    activeNav: string | undefined;
    collapsed: boolean;
    onNavigate?: () => void;
}

function NavLink({ item, activeNav, collapsed, onNavigate }: NavLinkProps) {
    const { t } = useTranslation();
    const { id, labelKey, icon, href } = item;
    const isActive = activeNav === id;
    const disabled = !href;

    const baseClass = collapsed ? navCollapsedBaseClass : navBaseClass;
    const stateClass = disabled ? navDisabledBg : isActive ? navActiveBg : navInactiveBg;
    const className = cn(baseClass, stateClass, !collapsed && disabled && 'w-full text-left');

    const content = (
        <>
            <HugeiconsIcon icon={icon} size={18} color={navIconColor(activeNav, id, disabled)} />
            {!collapsed && <span>{t(labelKey)}</span>}
        </>
    );

    const inner = href ? (
        <Link href={href} className={className} onClick={onNavigate} aria-label={collapsed ? t(labelKey) : undefined}>
            {content}
        </Link>
    ) : (
        <button
            type="button"
            disabled
            aria-disabled="true"
            aria-label={collapsed ? t(labelKey) : undefined}
            className={className}
        >
            {content}
        </button>
    );

    if (!collapsed) return inner;

    return (
        <Tooltip>
            <TooltipTrigger render={inner} />
            <TooltipContent side="right">{t(labelKey)}</TooltipContent>
        </Tooltip>
    );
}

interface SidebarBodyProps {
    activeNav: string | undefined;
    collapsed: boolean;
    isLoggingOut: boolean;
    onLogout: () => void;
    onToggleCollapse?: () => void;
    onClose?: () => void;
    showCollapseToggle: boolean;
    showCloseButton: boolean;
    searchQuery?: string;
}

function SidebarBody({
    activeNav,
    collapsed,
    isLoggingOut,
    onLogout,
    onToggleCollapse,
    onClose,
    showCollapseToggle,
    showCloseButton,
    searchQuery,
}: SidebarBodyProps) {
    const { t } = useTranslation();

    return (
        <>
            <div className={cn('transition-colors hover:bg-white/70', collapsed ? 'px-3 py-5' : 'px-5 py-5')}>
                <div className={cn('flex items-center gap-3', collapsed ? 'justify-center' : 'justify-between')}>
                    {!collapsed && (
                        <div className="flex items-center gap-3">
                            <div className="auth-gradient flex h-9 w-9 items-center justify-center rounded-full text-[11px] font-semibold tracking-[0.02em] text-white shadow-[0_12px_24px_-18px_rgba(91,97,157,0.9)]">
                                SCP
                            </div>
                            <div>
                                <div className="font-body text-sm font-medium leading-none text-[#0F172A]">osTicket SCP</div>
                                <div className="mt-1 text-[10px] font-medium uppercase tracking-[0.1em] text-[#94A3B8]">Agent Admin</div>
                            </div>
                        </div>
                    )}
                    {collapsed && (
                        <div className="auth-gradient flex h-9 w-9 items-center justify-center rounded-full text-[11px] font-semibold tracking-[0.02em] text-white shadow-[0_12px_24px_-18px_rgba(91,97,157,0.9)]">
                            SCP
                        </div>
                    )}
                    {showCloseButton && (
                        <button
                            type="button"
                            onClick={onClose}
                            aria-label={t('dashboard.layout.close_menu', { defaultValue: 'Close menu' })}
                            className="grid h-8 w-8 place-items-center rounded-md text-[#64748B] transition-colors hover:bg-white hover:text-[#0F172A]"
                        >
                            <HugeiconsIcon icon={Cancel01Icon} size={18} />
                        </button>
                    )}
                    {!showCloseButton && showCollapseToggle && (
                        <Tooltip>
                            <TooltipTrigger
                                render={
                                    <button
                                        type="button"
                                        onClick={onToggleCollapse}
                                        aria-pressed={collapsed}
                                        aria-label={collapsed
                                            ? t('dashboard.layout.expand_sidebar', { defaultValue: 'Expand sidebar' })
                                            : t('dashboard.layout.collapse_sidebar', { defaultValue: 'Collapse sidebar' })
                                        }
                                        className={cn(
                                            'grid h-8 w-8 place-items-center rounded-md text-[#94A3B8] transition-colors hover:bg-white hover:text-[#0F172A]',
                                            collapsed && 'mt-1',
                                        )}
                                    >
                                        <HugeiconsIcon icon={collapsed ? ArrowRight01Icon : ArrowLeft01Icon} size={16} />
                                    </button>
                                }
                            />
                            <TooltipContent side="right">
                                {collapsed
                                    ? t('dashboard.layout.expand_sidebar', { defaultValue: 'Expand sidebar' })
                                    : t('dashboard.layout.collapse_sidebar', { defaultValue: 'Collapse sidebar' })
                                }
                            </TooltipContent>
                        </Tooltip>
                    )}
                </div>
            </div>

            <Separator className="bg-[#E2E8F0]" />

            {!collapsed && (
                <div className="px-5 py-4">
                    <SearchField defaultQuery={searchQuery} />
                </div>
            )}

            <div className="custom-scrollbar flex-1 overflow-y-auto pb-6">
                <nav className={cn('space-y-0.5', collapsed ? 'px-2' : 'px-3')}>
                    {NAV_ITEMS.map((item) => (
                        <NavLink
                            key={item.id}
                            item={item}
                            activeNav={activeNav}
                            collapsed={collapsed}
                            onNavigate={onClose}
                        />
                    ))}
                </nav>

                {!collapsed && (
                    <>
                        <section className="mt-7 px-6">
                            <div className="auth-caption mb-3">{t('dashboard.layout.conversation')}</div>
                            <div className="space-y-2">
                                {CONVERSATION_ITEMS.map(({ id, label, subtitle, icon, badge, badgeActive }) => (
                                    <Card key={id} className={cn(
                                        'rounded-xl border py-0 opacity-70 ring-0 shadow-none transition-colors',
                                        id === 'side-conversation'
                                            ? 'border-[#E2E8F0] bg-white shadow-sm shadow-[#0F172A]/[0.03]'
                                            : 'border-transparent bg-transparent',
                                    )}>
                                        <CardContent className="px-4 py-3">
                                            <button
                                                type="button"
                                                disabled
                                                aria-disabled="true"
                                                className="flex w-full cursor-not-allowed items-center justify-between gap-3 text-left"
                                            >
                                                <div className="flex items-center gap-3">
                                                    <div className={cn(
                                                        'flex h-9 w-9 items-center justify-center rounded-lg',
                                                        id === 'side-conversation' ? 'bg-[#F1F5F9]' : 'border border-[#E2E8F0] bg-white',
                                                    )}>
                                                        <HugeiconsIcon icon={icon} size={18} color={id === 'side-conversation' ? '#64748B' : '#5B619D'} />
                                                    </div>
                                                    <div>
                                                        <div className="font-body text-sm font-medium text-[#0F172A]">{label}</div>
                                                        <div className="text-xs text-[#94A3B8]">{subtitle}</div>
                                                    </div>
                                                </div>
                                                <span className={cn(
                                                    'inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-1 text-[10px] font-semibold leading-none',
                                                    badgeActive ? 'bg-[#C4A5F3] text-[#0F172A]' : 'bg-[#E2E8F0] text-[#64748B]',
                                                )}>
                                                    {badge}
                                                </span>
                                            </button>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        </section>

                        <section className="mt-7 px-6">
                            <div className="auth-caption mb-2">{t('dashboard.layout.favorites')}</div>
                            <p className="max-w-47.5 text-xs leading-5 text-[#94A3B8]">
                                {t('dashboard.layout.favorites_hint')}
                            </p>
                        </section>

                        <section className="mt-7 px-6">
                            <div className="mb-3 flex items-center justify-between gap-3">
                                <div className="auth-caption">{t('dashboard.layout.pinned_tickets')}</div>
                                <Button
                                    variant="ghost"
                                    size="xs"
                                    disabled
                                    aria-disabled="true"
                                    className="h-auto rounded-[4px] px-0 text-[11px] text-[#94A3B8] hover:bg-transparent hover:text-[#94A3B8]"
                                >
                                    {t('dashboard.layout.unpin_all')}
                                </Button>
                            </div>
                            <div className="space-y-3">
                                {PINNED_TICKETS.map(({ label }, index) => (
                                    <button
                                        key={label}
                                        type="button"
                                        disabled
                                        aria-disabled="true"
                                        className="flex w-full cursor-not-allowed items-center justify-between gap-3 text-left opacity-70"
                                    >
                                        <div className="flex min-w-0 items-center gap-2">
                                            <div className={cn(
                                                'flex h-4 w-4 items-center justify-center rounded-full',
                                                index < 2 ? 'bg-[#22C55E] text-white' : 'bg-[#F1F5F9] text-[#64748B]',
                                            )}>
                                                <span className="text-[9px] leading-none">•</span>
                                            </div>
                                            <span className="truncate font-body text-sm font-medium text-[#64748B] transition-colors">
                                                {label}
                                            </span>
                                        </div>
                                        <span className="text-xs text-[#CBD5E1] transition-colors">⌁</span>
                                    </button>
                                ))}

                                <Button
                                    variant="ghost"
                                    disabled
                                    aria-disabled="true"
                                    className="mt-1 h-auto justify-start rounded-[4px] px-0 text-sm font-medium text-[#64748B] hover:bg-transparent hover:text-[#0F172A]"
                                >
                                    <span className="text-lg leading-none">+</span>
                                    {t('dashboard.layout.add_new')}
                                </Button>
                            </div>
                        </section>
                    </>
                )}
            </div>

            <Separator className="bg-[#E2E8F0]" />

            <div className={cn('mt-auto', collapsed ? 'flex flex-col items-center gap-2 px-2 py-4' : 'px-5 py-5')}>
                {collapsed ? (
                    <>
                        <Tooltip>
                            <TooltipTrigger
                                render={
                                    <Link
                                        href="/scp/preferences"
                                        aria-label={t('dashboard.layout.preferences')}
                                        className={cn(
                                            'grid h-9 w-9 place-items-center rounded-md transition-colors',
                                            activeNav === 'preferences'
                                                ? 'bg-[#F1F5F9] text-[#0F172A]'
                                                : 'text-[#64748B] hover:bg-white hover:text-[#0F172A]',
                                        )}
                                        aria-current={activeNav === 'preferences' ? 'page' : undefined}
                                    >
                                        <HugeiconsIcon icon={Settings01Icon} size={18} color={activeNav === 'preferences' ? '#5B619D' : '#94A3B8'} />
                                    </Link>
                                }
                            />
                            <TooltipContent side="right">{t('dashboard.layout.preferences')}</TooltipContent>
                        </Tooltip>

                        <Tooltip>
                            <TooltipTrigger
                                render={
                                    <Link
                                        href="/scp/account/security"
                                        aria-label={t('dashboard.layout.security')}
                                        className={cn(
                                            'grid h-9 w-9 place-items-center rounded-md transition-colors',
                                            activeNav === 'security'
                                                ? 'bg-[#F1F5F9] text-[#0F172A]'
                                                : 'text-[#64748B] hover:bg-white hover:text-[#0F172A]',
                                        )}
                                        aria-current={activeNav === 'security' ? 'page' : undefined}
                                    >
                                        <HugeiconsIcon icon={ShieldCheck} size={16} color={activeNav === 'security' ? '#5B619D' : '#94A3B8'} />
                                    </Link>
                                }
                            />
                            <TooltipContent side="right">{t('dashboard.layout.security')}</TooltipContent>
                        </Tooltip>

                        <Tooltip>
                            <TooltipTrigger
                                render={
                                    <button
                                        type="button"
                                        disabled={isLoggingOut}
                                        onClick={onLogout}
                                        aria-label={t('actions.logout')}
                                        className="grid h-9 w-9 place-items-center rounded-md text-[#64748B] transition-colors hover:bg-white hover:text-red-600 disabled:opacity-60"
                                    >
                                        <HugeiconsIcon icon={LogoutSquare01Icon} size={16} color="#94A3B8" />
                                    </button>
                                }
                            />
                            <TooltipContent side="right">{t('actions.logout')}</TooltipContent>
                        </Tooltip>
                    </>
                ) : (
                    <>
                        <Link
                            href="/scp/preferences"
                            className={cn(
                                'mb-4 inline-flex h-auto items-center gap-2 rounded-md px-0 text-sm transition-colors',
                                activeNav === 'preferences'
                                    ? 'text-[#0F172A]'
                                    : 'text-[#64748B] hover:text-[#0F172A]',
                            )}
                            aria-current={activeNav === 'preferences' ? 'page' : undefined}
                            onClick={onClose}
                        >
                            <HugeiconsIcon
                                icon={Settings01Icon}
                                size={18}
                                color={activeNav === 'preferences' ? '#5B619D' : '#94A3B8'}
                            />
                            <span>{t('dashboard.layout.preferences')}</span>
                        </Link>

                        <Card className="mb-4 rounded-[7px] border-[#E2E8F0] py-0 shadow-sm shadow-[#0F172A]/[0.03] ring-0">
                            <CardContent className="flex items-center justify-between gap-3 px-3 py-3">
                                <div>
                                    <div className="text-[9px] font-semibold uppercase tracking-[0.16em] text-[#94A3B8]">{t('dashboard.layout.powered_by')}</div>
                                    <div className="mt-1 font-display text-lg font-medium tracking-tight text-[#0F172A]">osTicket 2.0</div>
                                </div>
                                <div className="flex h-8 w-8 items-center justify-center rounded-[4px] bg-[#F1F5F9] text-[#5B619D]">
                                    <HugeiconsIcon icon={DashboardSquare01Icon} size={18} color="#5B619D" />
                                </div>
                            </CardContent>
                        </Card>

                        <div className="grid grid-cols-2 gap-2">
                            <Link
                                href="/scp/account/security"
                                className={getFooterActionClasses(activeNav, 'security')}
                                aria-current={activeNav === 'security' ? 'page' : undefined}
                                onClick={onClose}
                            >
                                <HugeiconsIcon icon={ShieldCheck} size={14} color={getFooterActionIconColor(activeNav, 'security')} />
                                {t('dashboard.layout.security')}
                            </Link>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={isLoggingOut}
                                onClick={onLogout}
                                className="cursor-pointer rounded-[4px] border-[#E2E8F0] bg-white text-xs text-[#64748B] hover:bg-[#F8FAFC] hover:text-red-600"
                            >
                                <HugeiconsIcon icon={LogoutSquare01Icon} size={14} color="#94A3B8" />
                                {t('actions.logout')}
                            </Button>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

export default function DashboardLayout({
    title,
    subtitle,
    eyebrow,
    activeNav = 'dashboard',
    headerLeft,
    headerActions,
    contentClassName = 'w-full',
    searchQuery,
    children,
}: DashboardLayoutProps) {
    const { t } = useTranslation();
    const resolvedHeaderActions = headerActions === undefined
        ? <DefaultHeaderActions />
        : (Children.count(headerActions) > 0 ? headerActions : null);
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const isLoggingOutRef = useRef(false);

    const isMobile = useMediaQuery(MOBILE_BREAKPOINT);

    const [desktopCollapsed, setDesktopCollapsed] = useState<boolean>(() => {
        if (typeof window === 'undefined') return false;
        try {
            return window.localStorage.getItem(COLLAPSE_STORAGE_KEY) === '1';
        } catch {
            return false;
        }
    });

    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => {
        if (typeof window === 'undefined') return;
        try {
            window.localStorage.setItem(COLLAPSE_STORAGE_KEY, desktopCollapsed ? '1' : '0');
        } catch {
            /* ignore quota / private mode */
        }
    }, [desktopCollapsed]);

    useEffect(() => {
        if (!isMobile) setMobileOpen(false);
    }, [isMobile]);

    function logout() {
        if (isLoggingOutRef.current) return;
        isLoggingOutRef.current = true;
        setIsLoggingOut(true);
        router.post('/scp/logout', {}, {
            onFinish: () => {
                isLoggingOutRef.current = false;
                setIsLoggingOut(false);
            },
        });
    }

    const sidebarCollapsed = isMobile ? false : desktopCollapsed;
    const sidebarWidthClass = sidebarCollapsed ? 'w-16' : 'w-72';

    return (
        <TooltipProvider delay={150}>
            <div className="auth-theme relative flex h-screen overflow-hidden bg-[#E9ECEF] text-[#0F172A]">
                <div className="auth-mesh pointer-events-none absolute inset-0" aria-hidden />
                <div className="auth-grain pointer-events-none absolute inset-0" aria-hidden />

                <div className="relative z-10 flex h-full w-full">
                    <div className="flex h-full w-full overflow-hidden bg-white">
                        {/* Mobile slide-in sidebar */}
                        {isMobile && (
                            <>
                                <div
                                    className={cn(
                                        'pointer-events-none fixed inset-0 z-40 bg-[#0F172A]/40 transition-opacity duration-200',
                                        mobileOpen ? 'pointer-events-auto opacity-100' : 'opacity-0',
                                    )}
                                    aria-hidden
                                    onClick={() => setMobileOpen(false)}
                                />
                                <aside
                                    aria-label="Sidebar"
                                    className={cn(
                                        'fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] flex-col border-r border-[#E2E8F0] bg-[#F8FAFC] shadow-2xl shadow-black/10 transition-transform duration-200',
                                        mobileOpen ? 'translate-x-0' : '-translate-x-full',
                                    )}
                                >
                                    <SidebarBody
                                        activeNav={activeNav}
                                        collapsed={false}
                                        isLoggingOut={isLoggingOut}
                                        onLogout={() => { setMobileOpen(false); logout(); }}
                                        onClose={() => setMobileOpen(false)}
                                        showCloseButton
                                        showCollapseToggle={false}
                                        searchQuery={searchQuery}
                                    />
                                </aside>
                            </>
                        )}

                        {/* Desktop sidebar */}
                        {!isMobile && (
                            <aside className={cn(
                                'flex shrink-0 flex-col border-r border-[#E2E8F0] bg-[#F8FAFC]/90 transition-[width] duration-200',
                                sidebarWidthClass,
                            )}>
                                <SidebarBody
                                    activeNav={activeNav}
                                    collapsed={sidebarCollapsed}
                                    isLoggingOut={isLoggingOut}
                                    onLogout={logout}
                                    onToggleCollapse={() => setDesktopCollapsed((value) => !value)}
                                    showCollapseToggle
                                    showCloseButton={false}
                                    searchQuery={searchQuery}
                                />
                            </aside>
                        )}

                        <div className="flex min-w-0 flex-1 flex-col overflow-hidden bg-white">
                            <header className="relative z-10 flex shrink-0 items-center justify-between gap-4 border-b border-[#E2E8F0] bg-white px-4 py-4 sm:px-6 sm:py-5 xl:px-10">
                                <div className="flex min-w-0 flex-1 items-center gap-3">
                                    {isMobile && (
                                        <button
                                            type="button"
                                            onClick={() => setMobileOpen(true)}
                                            aria-label={t('dashboard.layout.open_menu', { defaultValue: 'Open menu' })}
                                            className="grid h-9 w-9 shrink-0 place-items-center rounded-md border border-[#E2E8F0] bg-white text-[#64748B] transition-colors hover:bg-[#F8FAFC] hover:text-[#0F172A]"
                                        >
                                            <HugeiconsIcon icon={Menu01Icon} size={18} />
                                        </button>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        {headerLeft ?? (
                                            <>
                                                {eyebrow && <div className="auth-eyebrow mb-2 text-[#94A3B8]">{eyebrow}</div>}
                                                {title && <h1 className="font-display text-xl font-medium tracking-[-0.02em] text-[#0F172A]">{title}</h1>}
                                                {subtitle && <p className="mt-1 font-body text-sm text-[#94A3B8]">{subtitle}</p>}
                                            </>
                                        )}
                                    </div>
                                </div>
                                <div className="flex shrink-0 items-center gap-2 sm:gap-3">{resolvedHeaderActions}</div>
                            </header>

                            <main className="custom-scrollbar relative flex-1 overflow-y-auto bg-white px-4 py-5 sm:px-6 sm:py-6 lg:px-8 xl:px-10 xl:py-8">
                                <div className={contentClassName}>{children}</div>
                            </main>
                        </div>
                    </div>
                </div>
            </div>
        </TooltipProvider>
    );
}
