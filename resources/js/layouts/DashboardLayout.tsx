import { Children, useRef, useState, type ReactNode } from 'react';
import { Link, router } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import { useTranslation } from 'react-i18next';
import {
    ArrowRight01Icon,
    BarChartIcon,
    BookOpen01Icon,
    CustomerService01Icon,
    DashboardSquare01Icon,
    InboxIcon,
    LogoutSquare01Icon,
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

const navBaseClass = 'flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium font-body transition-colors duration-150';
const navActiveClass = `${navBaseClass} bg-[#F1F5F9] text-[#0F172A] shadow-[inset_0_0_0_1px_rgba(226,232,240,0.8)]`;
const navInactiveClass = `${navBaseClass} text-[#64748B] hover:bg-white hover:text-[#0F172A]`;
const navDisabledClass = `${navBaseClass} cursor-not-allowed text-[#94A3B8] opacity-50`;
const footerActionBaseClass = 'inline-flex items-center justify-center gap-2 rounded-md border px-3 py-2 text-xs font-medium transition-colors';
const footerActionActiveClass = `${footerActionBaseClass} border-[#CBD5E1] bg-[#F1F5F9] text-[#0F172A] shadow-[inset_0_0_0_1px_rgba(226,232,240,0.8)]`;
const footerActionInactiveClass = `${footerActionBaseClass} border-[#E2E8F0] bg-white text-[#64748B] hover:text-[#0F172A]`;

function getNavClasses(activeNav: string | undefined, navItem: string, disabled = false): string {
    if (disabled) return navDisabledClass;

    return activeNav === navItem ? navActiveClass : navInactiveClass;
}

function getNavIconColor(activeNav: string | undefined, navItem: string, disabled = false): string {
    if (disabled) return '#CBD5E1';

    return activeNav === navItem ? '#5B619D' : '#94A3B8';
}

function getFooterActionClasses(activeNav: string | undefined, navItem: string): string {
    return activeNav === navItem ? footerActionActiveClass : footerActionInactiveClass;
}

function getFooterActionIconColor(activeNav: string | undefined, navItem: string): string {
    return activeNav === navItem ? '#5B619D' : '#94A3B8';
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
                <InputGroupInput name="q" defaultValue={defaultQuery} aria-label={t('dashboard.layout.search_tickets')} placeholder={t('dashboard.layout.search_placeholder')} />
                <InputGroupAddon align="inline-end">
                    <Kbd>⌘</Kbd>
                    <Kbd>K</Kbd>
                </InputGroupAddon>
            </InputGroup>
        </form>
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

    function logout() {
        if (isLoggingOutRef.current) {
            return;
        }

        isLoggingOutRef.current = true;
        setIsLoggingOut(true);

        router.post('/scp/logout', {}, {
            onFinish: () => {
                isLoggingOutRef.current = false;
                setIsLoggingOut(false);
            },
        });
    }

    return (
        <div className="auth-theme relative flex h-screen overflow-hidden bg-[#E9ECEF] text-[#0F172A]">
            <div className="auth-mesh pointer-events-none absolute inset-0" aria-hidden />
            <div className="auth-grain pointer-events-none absolute inset-0" aria-hidden />

            <div className="relative z-10 flex h-full w-full">
                <div className="flex h-full w-full overflow-hidden bg-white">
                    <aside className="flex w-72 shrink-0 flex-col border-r border-[#E2E8F0] bg-[#F8FAFC]/90">
                        <div className="px-5 py-5 transition-colors hover:bg-white/70">
                            <div className="flex items-center justify-between gap-3">
                                <div className="flex items-center gap-3">
                                    <div className="auth-gradient flex h-9 w-9 items-center justify-center rounded-full text-[11px] font-semibold tracking-[0.02em] text-white shadow-[0_12px_24px_-18px_rgba(91,97,157,0.9)]">
                                        SCP
                                    </div>
                                    <div>
                                        <div className="font-body text-sm font-medium leading-none text-[#0F172A]">osTicket SCP</div>
                                        <div className="mt-1 text-[10px] font-medium uppercase tracking-[0.1em] text-[#94A3B8]">Agent Admin</div>
                                    </div>
                                </div>
                                <HugeiconsIcon icon={ArrowRight01Icon} size={16} color="#94A3B8" className="rotate-90" />
                            </div>
                        </div>

                        <Separator className="bg-[#E2E8F0]" />

                        <div className="px-5 py-4">
                            <SearchField defaultQuery={searchQuery} />
                        </div>

                        <div className="custom-scrollbar flex-1 overflow-y-auto pb-6">
                            <nav className="space-y-0.5 px-3">
                                {NAV_ITEMS.map(({ id, labelKey, icon, href }) => {
                                    const content = (
                                        <>
                                            <HugeiconsIcon icon={icon} size={18} color={getNavIconColor(activeNav, id, !href)} />
                                            <span>{t(labelKey)}</span>
                                        </>
                                    );

                                    return href ? (
                                        <Link key={id} href={href} className={getNavClasses(activeNav, id)}>
                                            {content}
                                        </Link>
                                    ) : (
                                        <button
                                            key={id}
                                            type="button"
                                            disabled
                                            aria-disabled="true"
                                            className={`${getNavClasses(activeNav, id, true)} w-full text-left`}
                                        >
                                            {content}
                                        </button>
                                    );
                                })}
                            </nav>

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
                        </div>

                        <Separator className="bg-[#E2E8F0]" />

                        <div className="mt-auto px-5 py-5">
                            <Link
                                href="/scp/preferences"
                                className={cn(
                                    'mb-4 inline-flex h-auto items-center gap-2 rounded-md px-0 text-sm transition-colors',
                                    activeNav === 'preferences'
                                        ? 'text-[#0F172A]'
                                        : 'text-[#64748B] hover:text-[#0F172A]',
                                )}
                                aria-current={activeNav === 'preferences' ? 'page' : undefined}
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
                                >
                                    <HugeiconsIcon icon={ShieldCheck} size={14} color={getFooterActionIconColor(activeNav, 'security')} />
                                    {t('dashboard.layout.security')}
                                </Link>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={isLoggingOut}
                                    onClick={logout}
                                    className="cursor-pointer rounded-[4px] border-[#E2E8F0] bg-white text-xs text-[#64748B] hover:bg-[#F8FAFC] hover:text-red-600"
                                >
                                    <HugeiconsIcon icon={LogoutSquare01Icon} size={14} color="#94A3B8" />
                                    {t('actions.logout')}
                                </Button>
                            </div>
                        </div>
                    </aside>

                    <div className="flex min-w-0 flex-1 flex-col overflow-hidden bg-white">
                        <header className="relative z-10 flex shrink-0 items-center justify-between gap-6 border-b border-[#E2E8F0] bg-white px-8 py-5 xl:px-10">
                            <div>
                                {headerLeft ?? (
                                    <>
                                        {eyebrow && <div className="auth-eyebrow mb-2 text-[#94A3B8]">{eyebrow}</div>}
                                        {title && <h1 className="font-display text-xl font-medium tracking-[-0.02em] text-[#0F172A]">{title}</h1>}
                                        {subtitle && <p className="mt-1 font-body text-sm text-[#94A3B8]">{subtitle}</p>}
                                    </>
                                )}
                            </div>
                            <div className="flex items-center gap-3">{resolvedHeaderActions}</div>
                        </header>

                        <main className="custom-scrollbar relative flex-1 overflow-y-auto bg-white px-6 py-6 lg:px-8 xl:px-10 xl:py-8">
                            <div className={contentClassName}>{children}</div>
                        </main>
                    </div>
                </div>
            </div>
        </div>
    );
}
