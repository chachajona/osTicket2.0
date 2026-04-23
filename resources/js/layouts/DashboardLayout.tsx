import { Children, type ReactNode } from 'react';
import { Link, router } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    ArrowRight01Icon,
    BarChartIcon,
    BookOpen01Icon,
    CustomerService01Icon,
    DashboardSquare01Icon,
    HelpCircleIcon,
    InboxIcon,
    LogoutSquare01Icon,
    Message01Icon,
    Notification01Icon,
    Search01Icon,
    ShieldCheck,
    Ticket01Icon,
} from '@hugeicons/core-free-icons';

import { Button } from '@/components/ui/button';
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
    title: string;
    subtitle?: string;
    eyebrow?: string;
    activeNav?: string;
    headerActions?: ReactNode;
    contentClassName?: string;
    children: ReactNode;
}

interface NavItem {
    id: string;
    label: string;
    icon: typeof DashboardSquare01Icon;
    href?: string;
}

const NAV_ITEMS: NavItem[] = [
    { id: 'dashboard', label: 'Dashboard', icon: DashboardSquare01Icon, href: '/scp' },
    { id: 'inbox', label: 'Inbox', icon: InboxIcon },
    { id: 'notifications', label: 'Notifications', icon: Notification01Icon },
    { id: 'tickets', label: 'Tickets', icon: Ticket01Icon },
    { id: 'knowledge', label: 'Knowledge Base', icon: BookOpen01Icon },
    { id: 'customers', label: 'Customers', icon: CustomerService01Icon },
    { id: 'forum', label: 'Forum', icon: Message01Icon },
    { id: 'reports', label: 'Reports', icon: BarChartIcon },
];

const CONVERSATION_ITEMS = [
    { id: 'call', label: 'Call', subtitle: '(123) 45678...', icon: CustomerService01Icon, badge: '1', badgeActive: true },
    { id: 'side-conversation', label: 'Side Conversation', subtitle: 'No new replies', icon: Message01Icon, badge: '0', badgeActive: false },
] as const;

const PINNED_TICKETS = [
    { href: '#', label: '#TC-192 product inquiry...' },
    { href: '#', label: '#TC-191 payment issue...' },
    { href: '#', label: '+1 678-908-78...' },
] as const;

const navBaseClass = 'flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium font-body transition-colors duration-150';
const navActiveClass = `${navBaseClass} bg-[#F1F5F9] text-[#0F172A] shadow-[inset_0_0_0_1px_rgba(226,232,240,0.8)]`;
const navInactiveClass = `${navBaseClass} text-[#64748B] hover:bg-white hover:text-[#0F172A]`;

function getNavClasses(activeNav: string | undefined, navItem: string): string {
    return activeNav === navItem ? navActiveClass : navInactiveClass;
}

function getNavIconColor(activeNav: string | undefined, navItem: string): string {
    return activeNav === navItem ? '#5B619D' : '#94A3B8';
}

function DefaultHeaderActions() {
    return (
        <>
            <Button
                variant="outline"
                size="icon"
                className="rounded-md border-[#E2E8F0] bg-white text-[#64748B] hover:bg-[#F8FAFC]"
                aria-label="Open dashboard actions"
            >
                <span className="text-lg leading-none">•••</span>
            </Button>

            <div className="flex overflow-hidden rounded-md shadow-[0_10px_25px_-20px_rgba(91,97,157,0.7)]">
                <Button className="rounded-none rounded-l-md bg-[#5B619D] px-4 text-sm text-white hover:bg-[#4F5486]">
                    Export CSV
                </Button>
                <Button
                    size="icon"
                    className="rounded-none rounded-r-md border-l border-white/15 bg-[#5B619D] text-white hover:bg-[#4F5486]"
                    aria-label="Open export options"
                >
                    <HugeiconsIcon icon={ArrowRight01Icon} size={14} className="rotate-90" />
                </Button>
            </div>
        </>
    );
}

function SearchField() {
    return (
        <InputGroup>
            <InputGroupAddon align="inline-start">
                <HugeiconsIcon icon={Search01Icon} size={16} />
            </InputGroupAddon>
            <InputGroupInput aria-label="Search dashboard" placeholder="Search..." />
            <InputGroupAddon align="inline-end">
                <Kbd>⌘</Kbd>
                <Kbd>K</Kbd>
            </InputGroupAddon>
        </InputGroup>
    );
}

export default function DashboardLayout({
    title,
    subtitle,
    eyebrow,
    activeNav = 'dashboard',
    headerActions,
    contentClassName = 'w-full',
    children,
}: DashboardLayoutProps) {
    const resolvedHeaderActions = headerActions === undefined
        ? <DefaultHeaderActions />
        : (Children.count(headerActions) > 0 ? headerActions : null);

    return (
        <div className="auth-theme relative flex h-screen overflow-hidden bg-[#E9ECEF] text-[#0F172A]">
            <div className="auth-mesh pointer-events-none absolute inset-0"></div>
            <div className="auth-grain pointer-events-none absolute inset-0"></div>

            <main className="relative z-10 flex h-full w-full">
                <div className="flex h-full w-full overflow-hidden bg-white">
                    <aside className="flex w-72 shrink-0 flex-col border-r border-[#E2E8F0] bg-[#F8FAFC]/90">
                        <div className="px-5 py-5 transition-colors hover:bg-white/70">
                            <div className="flex items-center justify-between gap-3">
                                <div className="flex items-center gap-3">
                                    <div className="auth-gradient flex h-10 w-10 items-center justify-center rounded-full text-xs font-semibold tracking-[0.14em] text-white shadow-[0_12px_24px_-18px_rgba(91,97,157,0.9)]">
                                        SCP
                                    </div>
                                    <div>
                                        <div className="font-body text-sm font-semibold leading-none text-[#0F172A]">osTicket SCP</div>
                                        <div className="mt-1 text-xs text-[#94A3B8]">Agent Admin</div>
                                    </div>
                                </div>
                                <HugeiconsIcon icon={ArrowRight01Icon} size={16} color="#94A3B8" className="rotate-90" />
                            </div>
                        </div>

                        <Separator className="bg-[#E2E8F0]" />

                        <div className="px-5 py-4">
                            <SearchField />
                        </div>

                        <div className="custom-scrollbar flex-1 overflow-y-auto pb-6">
                            <nav className="space-y-0.5 px-3">
                                {NAV_ITEMS.map(({ id, label, icon, href }) => {
                                    const content = (
                                        <>
                                            <HugeiconsIcon icon={icon} size={18} color={getNavIconColor(activeNav, id)} />
                                            <span>{label}</span>
                                        </>
                                    );

                                    return href ? (
                                        <Link key={id} href={href} className={getNavClasses(activeNav, id)}>
                                            {content}
                                        </Link>
                                    ) : (
                                        <button key={id} type="button" className={`${getNavClasses(activeNav, id)} w-full text-left`}>
                                            {content}
                                        </button>
                                    );
                                })}
                            </nav>

                            <section className="mt-7 px-6">
                                <div className="auth-caption mb-3">Conversation</div>
                                <div className="space-y-2">
                                    {CONVERSATION_ITEMS.map(({ id, label, subtitle, icon, badge, badgeActive }) => (
                                        <Card key={id} className={cn(
                                            'rounded-xl border py-0 ring-0 shadow-none transition-colors',
                                            id === 'side-conversation'
                                                ? 'border-[#E2E8F0] bg-white shadow-sm shadow-[#0F172A]/[0.03]'
                                                : 'border-transparent bg-transparent hover:border-[#E2E8F0] hover:bg-white/80',
                                        )}>
                                            <CardContent className="px-4 py-3">
                                                <button type="button" className="flex w-full items-center justify-between gap-3 text-left">
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
                                <div className="auth-caption mb-2">Favorites</div>
                                <p className="max-w-47.5 text-xs leading-5 text-[#94A3B8]">
                                    Hover over any table and click the star to add it here.
                                </p>
                            </section>

                            <section className="mt-7 px-6">
                                <div className="mb-3 flex items-center justify-between gap-3">
                                    <div className="auth-caption">Pinned Tickets</div>
                                    <Button variant="ghost" size="xs" className="h-auto rounded-md px-0 text-[11px] text-[#94A3B8] hover:bg-transparent hover:text-[#0F172A]">
                                        Unpin all
                                    </Button>
                                </div>
                                <div className="space-y-3">
                                    {PINNED_TICKETS.map(({ href, label }, index) => (
                                        <a key={label} href={href} className="group flex items-center justify-between gap-3">
                                            <div className="flex min-w-0 items-center gap-2">
                                                <div className={cn(
                                                    'flex h-4 w-4 items-center justify-center rounded-full',
                                                    index < 2 ? 'bg-[#22C55E] text-white' : 'bg-[#F1F5F9] text-[#64748B]',
                                                )}>
                                                    <span className="text-[9px] leading-none">•</span>
                                                </div>
                                                <span className="truncate font-body text-sm font-medium text-[#64748B] transition-colors group-hover:text-[#0F172A]">
                                                    {label}
                                                </span>
                                            </div>
                                            <span className="text-xs text-[#CBD5E1] transition-colors group-hover:text-[#5B619D]">⌁</span>
                                        </a>
                                    ))}

                                    <Button variant="ghost" className="mt-1 h-auto justify-start rounded-md px-0 text-sm font-medium text-[#64748B] hover:bg-transparent hover:text-[#0F172A]">
                                        <span className="text-lg leading-none">+</span>
                                        Add new
                                    </Button>
                                </div>
                            </section>
                        </div>

                        <Separator className="bg-[#E2E8F0]" />

                        <div className="mt-auto px-5 py-5">
                            <Button variant="ghost" className="mb-4 h-auto justify-start rounded-md px-0 text-sm text-[#64748B] hover:bg-transparent hover:text-[#0F172A]">
                                <HugeiconsIcon icon={HelpCircleIcon} size={18} color="#94A3B8" />
                                <span>Help &amp; Support</span>
                            </Button>

                            <Card className="mb-4 rounded-xl border-[#E2E8F0] py-0 shadow-sm shadow-[#0F172A]/[0.03] ring-0">
                                <CardContent className="flex items-center justify-between gap-3 px-3 py-3">
                                    <div>
                                        <div className="text-[9px] font-semibold uppercase tracking-[0.16em] text-[#94A3B8]">Powered by</div>
                                        <div className="mt-1 font-display text-lg font-medium tracking-tight text-[#0F172A]">osTicket 2.0</div>
                                    </div>
                                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#F1F5F9] text-[#5B619D]">
                                        <HugeiconsIcon icon={DashboardSquare01Icon} size={18} color="#5B619D" />
                                    </div>
                                </CardContent>
                            </Card>

                            <div className="grid grid-cols-2 gap-2">
                                <Link href="/scp/account/security" className="inline-flex items-center justify-center gap-2 rounded-md border border-[#E2E8F0] bg-white px-3 py-2 text-xs font-medium text-[#64748B] transition-colors hover:text-[#0F172A]">
                                    <HugeiconsIcon icon={ShieldCheck} size={14} color="#94A3B8" />
                                    Security
                                </Link>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.post('/scp/logout')}
                                    className="rounded-md border-[#E2E8F0] bg-white text-xs text-[#64748B] hover:text-red-600"
                                >
                                    <HugeiconsIcon icon={LogoutSquare01Icon} size={14} color="#94A3B8" />
                                    Sign out
                                </Button>
                            </div>
                        </div>
                    </aside>

                    <div className="flex min-w-0 flex-1 flex-col overflow-hidden bg-white">
                        <header className="relative z-10 flex shrink-0 items-center justify-between gap-6 border-b border-[#E2E8F0] bg-white px-8 py-5 xl:px-10">
                            <div>
                                {eyebrow && <div className="auth-eyebrow mb-2 text-[#94A3B8]">{eyebrow}</div>}
                                <h1 className="font-display text-[28px] font-medium tracking-tight text-[#0F172A]">{title}</h1>
                                {subtitle && <p className="mt-1 font-body text-sm text-[#94A3B8]">{subtitle}</p>}
                            </div>
                            <div className="flex items-center gap-3">{resolvedHeaderActions}</div>
                        </header>

                        <main className="custom-scrollbar relative flex-1 overflow-y-auto bg-white px-6 py-6 lg:px-8 xl:px-10 xl:py-8">
                            <div className={contentClassName}>{children}</div>
                        </main>
                    </div>
                </div>
            </main>
        </div>
    );
}
