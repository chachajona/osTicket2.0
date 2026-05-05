import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
  type ReactElement,
} from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { HugeiconsIcon } from '@hugeicons/react';
import {
  ArrowDown01Icon,
  Logout01Icon,
  Search01Icon,
  Settings01Icon,
  SidebarLeft01Icon,
} from '@hugeicons/core-free-icons';

import { cn } from '@/lib/utils';
import { PANEL_NAV, type NavItem, type NavSubItem } from '@/lib/panelNavigation';
import { PanelSwitcher } from '@/components/panel/PanelSwitcher';
import { ADMIN_TAB_MAP } from '@/components/admin/AdminTabs';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import { SUPPORTED_LANGUAGES, type SupportedLanguage } from '@/i18n';

const MOBILE_BREAKPOINT = '(max-width: 1023px)';
const SIDEBAR_COLLAPSED_KEY = 'app.sidebar.collapsed';
const SIDEBAR_EXPANDED_WIDTH = 260;
const SIDEBAR_COLLAPSED_WIDTH = 64;

function useActiveNav(currentPanel: 'scp' | 'admin', url: string, subId?: string): string | undefined {
  if (currentPanel === 'admin') {
    if (!subId) return undefined;
    return ADMIN_TAB_MAP[subId]?.tabId;
  }
  const path = url.split('?')[0];
  if (path === '/scp' || path === '/scp/') return 'dashboard';
  const match = path.match(/^\/scp\/([^/]+)/);
  if (match) return match[1];
  return undefined;
}

const PageHeaderSlotContext = createContext<{ setNode: (node: ReactNode) => void }>({
  setNode: () => {},
});

export function SetPageHeader({ children }: { children: ReactNode }) {
  const { setNode } = useContext(PageHeaderSlotContext);
  useLayoutEffect(() => {
    setNode(children);
    return () => setNode(null);
  }, [children, setNode]);
  return null;
}

interface SidebarContextValue {
  collapsed: boolean;
  toggleCollapsed: () => void;
  isMobile: boolean;
  mobileOpen: boolean;
  setMobileOpen: (open: boolean) => void;
}

const SidebarContext = createContext<SidebarContextValue | null>(null);

function useSidebar(): SidebarContextValue {
  const ctx = useContext(SidebarContext);
  if (!ctx) throw new Error('useSidebar must be used inside SidebarContext.Provider');
  return ctx;
}

function readInitialCollapsed(): boolean {
  if (typeof window === 'undefined') return false;
  try {
    return window.localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === '1';
  } catch {
    return false;
  }
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

function getInitials(name?: string | null, username?: string | null): string {
  const source = (name ?? username ?? '').trim();
  if (!source) return '·';
  const parts = source.split(/\s+/).filter(Boolean);
  if (parts.length === 0) return source.charAt(0).toUpperCase();
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

function Avatar({ name, username, size = 28 }: { name?: string | null; username?: string | null; size?: number }) {
  const initials = getInitials(name, username);
  const fontSize = Math.round(size * 0.42);
  return (
    <span
      aria-hidden
      className="grid shrink-0 place-items-center rounded-full bg-[#F4F2EB] font-medium text-[#71717A]"
      style={{ width: size, height: size, fontSize, letterSpacing: '0.02em' }}
    >
      {initials}
    </span>
  );
}

function SearchField({ collapsed }: { collapsed: boolean }) {
  if (collapsed) {
    return (
      <Tooltip>
        <TooltipTrigger
          render={(props) => (
            <button
              {...props}
              type="button"
              onClick={() => router.get('/scp/search')}
              aria-label="Search tickets"
              className="grid h-9 w-9 place-items-center rounded-[6px] border border-[#E2E0D8] bg-white text-[#71717A] transition-colors hover:bg-[#F4F2EB] hover:text-[#18181B]"
            />
          )}
        >
          <HugeiconsIcon icon={Search01Icon} size={16} strokeWidth={1.75} />
        </TooltipTrigger>
        <TooltipContent side="right">Search tickets</TooltipContent>
      </Tooltip>
    );
  }

  return (
    <form
      role="search"
      onSubmit={(event) => {
        event.preventDefault();
        const query = new FormData(event.currentTarget).get('q')?.toString().trim() ?? '';
        router.get('/scp/search', query === '' ? {} : { q: query });
      }}
    >
      <div className="flex items-center gap-2 rounded-[4px] border border-[#E2E0D8] bg-white px-3 py-2 transition-colors focus-within:border-[#18181B]">
        <HugeiconsIcon icon={Search01Icon} size={16} strokeWidth={1.75} className="text-[#A1A1AA]" />
        <input
          name="q"
          type="text"
          placeholder="Search tickets..."
          aria-label="Search tickets"
          className="min-w-0 flex-1 bg-transparent text-[14px] text-[#18181B] outline-none placeholder:text-[#A1A1AA]"
        />
      </div>
    </form>
  );
}

interface NavRowProps {
  item: NavItem;
  activeNav: string | undefined;
  activeSubId: string | undefined;
  onNavigate?: () => void;
}

function navRowClasses(state: 'active' | 'disabled' | 'idle', collapsed: boolean): string {
  return cn(
    'group flex w-full items-center rounded-[8px] text-left text-[14px] leading-[22.75px] transition-colors duration-150',
    collapsed ? 'h-9 justify-center px-0' : 'gap-2.5 px-3 py-2',
    state === 'active' && 'bg-[#F4F2EB] font-medium text-[#18181B]',
    state === 'disabled' && 'cursor-not-allowed text-[#A1A1AA] opacity-50',
    state === 'idle' && 'text-[#71717A] hover:bg-[#F4F2EB]/80 hover:text-[#18181B]',
  );
}

function NavIcon({ icon, state }: { icon: NavItem['icon']; state: 'active' | 'disabled' | 'idle' }) {
  const color = state === 'active' ? '#18181B' : state === 'disabled' ? '#A1A1AA' : '#71717A';
  return (
    <span
      className={cn(
        'flex h-5 w-5 shrink-0 items-center justify-center transition-colors duration-150',
        state === 'idle' && 'group-hover:text-[#18181B]',
      )}
      style={{ color }}
      aria-hidden
    >
      <HugeiconsIcon icon={icon} size={20} strokeWidth={1.5} />
    </span>
  );
}

function MaybeTooltip({ collapsed, label, children }: { collapsed: boolean; label: string; children: ReactElement }) {
  if (!collapsed) return children;
  return (
    <Tooltip>
      <TooltipTrigger render={children} />
      <TooltipContent side="right">{label}</TooltipContent>
    </Tooltip>
  );
}

function NavLeaf({ item, activeNav, onNavigate }: NavRowProps) {
  const { collapsed } = useSidebar();
  const { t } = useTranslation();
  const { id, labelKey, icon, href } = item;
  const isActive = activeNav === id;
  const disabled = !href;
  const state: 'active' | 'disabled' | 'idle' = isActive ? 'active' : disabled ? 'disabled' : 'idle';
  const label = t(labelKey, { defaultValue: labelKey });
  const className = navRowClasses(state, collapsed);

  const inner = (
    <>
      <NavIcon icon={icon} state={state} />
      {!collapsed && <span className="min-w-0 flex-1 truncate">{label}</span>}
    </>
  );

  if (disabled) {
    return (
      <MaybeTooltip collapsed={collapsed} label={label}>
        <button type="button" disabled aria-disabled="true" className={className}>
          {inner}
        </button>
      </MaybeTooltip>
    );
  }

  return (
    <MaybeTooltip collapsed={collapsed} label={label}>
      <Link href={href!} className={className} onClick={onNavigate} aria-current={isActive ? 'page' : undefined}>
        {inner}
      </Link>
    </MaybeTooltip>
  );
}

function NavGroup({ item, activeNav, activeSubId, onNavigate }: NavRowProps) {
  const { collapsed } = useSidebar();
  const { t } = useTranslation();
  const { id, labelKey, icon, children = [] } = item;
  const groupActive = activeNav === id;
  const childActive = useMemo(
    () => children.some((child) => child.id === activeSubId),
    [children, activeSubId],
  );
  const [open, setOpen] = useState(groupActive || childActive);

  useEffect(() => {
    if (groupActive || childActive) setOpen(true);
  }, [groupActive, childActive]);

  const state: 'active' | 'idle' = groupActive || childActive ? 'active' : 'idle';
  const label = t(labelKey, { defaultValue: labelKey });

  if (collapsed) {
    return <CollapsedNavGroupTrigger item={item} state={state} label={label} activeSubId={activeSubId} onNavigate={onNavigate} />;
  }

  const triggerId = `nav-group-${id}`;
  const panelId = `nav-group-${id}-panel`;

  return (
    <div className="flex flex-col">
      <button
        id={triggerId}
        type="button"
        onClick={() => setOpen((prev) => !prev)}
        aria-expanded={open}
        aria-controls={panelId}
        className={navRowClasses(state, false)}
      >
        <NavIcon icon={icon} state={state} />
        <span className="min-w-0 flex-1 truncate">{label}</span>
        <span
          aria-hidden
          className={cn(
            'flex h-4 w-4 shrink-0 items-center justify-center text-[#A1A1AA] transition-transform duration-200',
            open && 'rotate-180',
          )}
        >
          <HugeiconsIcon icon={ArrowDown01Icon} size={14} strokeWidth={1.75} />
        </span>
      </button>

      <div
        id={panelId}
        role="region"
        aria-labelledby={triggerId}
        hidden={!open}
        className="mt-0.5 ml-3 flex flex-col gap-0.5 border-l border-[#E2E0D8] pl-3"
      >
        {children.map((child) => (
          <NavSubLink
            key={child.id}
            child={child}
            activeSubId={activeSubId}
            onNavigate={onNavigate}
          />
        ))}
      </div>
    </div>
  );
}

interface CollapsedNavGroupTriggerProps {
  item: NavItem;
  state: 'active' | 'idle';
  label: string;
  activeSubId: string | undefined;
  onNavigate?: () => void;
}

function CollapsedNavGroupTrigger({ item, state, label, activeSubId, onNavigate }: CollapsedNavGroupTriggerProps) {
  const [open, setOpen] = useState(false);
  const className = navRowClasses(state, true);
  const children = item.children ?? [];

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger
        render={(props) => (
          <button
            {...props}
            type="button"
            aria-label={label}
            className={className}
          />
        )}
      >
        <NavIcon icon={item.icon} state={state} />
      </PopoverTrigger>
      <PopoverContent side="right" align="start" sideOffset={10} className="w-56 p-1.5">
        <div className="px-2.5 pt-1 pb-2 text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">
          {label}
        </div>
        <ul className="flex flex-col gap-0.5">
          {children.map((child) => (
            <li key={child.id}>
              <NavSubLink
                child={child}
                activeSubId={activeSubId}
                onNavigate={() => {
                  setOpen(false);
                  onNavigate?.();
                }}
              />
            </li>
          ))}
        </ul>
      </PopoverContent>
    </Popover>
  );
}

interface NavSubLinkProps {
  child: NavSubItem;
  activeSubId: string | undefined;
  onNavigate?: () => void;
}

function NavSubLink({ child, activeSubId, onNavigate }: NavSubLinkProps) {
  const { t } = useTranslation();
  const isActive = child.id === activeSubId;
  const disabled = !child.enabled || !child.href;
  const label = t(child.label, { defaultValue: child.label });

  const className = cn(
    'flex w-full items-center rounded-[6px] px-3 py-1.5 text-left text-[13px] leading-[20px] transition-colors duration-150',
    isActive
      ? 'bg-[#F4F2EB] font-medium text-[#18181B]'
      : disabled
        ? 'cursor-not-allowed text-[#A1A1AA]'
        : 'text-[#71717A] hover:bg-[#F4F2EB]/80 hover:text-[#18181B]',
  );

  if (disabled) {
    return (
      <span className={className} aria-disabled title="Not yet implemented">
        {label}
      </span>
    );
  }

  return (
    <Link
      href={child.href!}
      className={className}
      onClick={onNavigate}
      aria-current={isActive ? 'page' : undefined}
    >
      {label}
    </Link>
  );
}

function NavRow(props: NavRowProps) {
  return props.item.children && props.item.children.length > 0 ? (
    <NavGroup {...props} />
  ) : (
    <NavLeaf {...props} />
  );
}

interface AccountBlockProps {
  staff: { name?: string; username?: string } | null | undefined;
  isLoggingOut: boolean;
  onLogout: () => void;
}

function LanguagePicker() {
  const { t, i18n } = useTranslation();
  const active = (i18n.resolvedLanguage ?? i18n.language ?? 'en').slice(0, 2);
  return (
    <div className="flex items-center justify-between gap-3 rounded-[6px] px-2.5 py-1.5">
      <span className="text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">
        {t('language_switcher.select_language', { defaultValue: 'Language' })}
      </span>
      <div className="inline-flex overflow-hidden rounded-[4px] border border-[#E2E0D8] bg-white">
        {SUPPORTED_LANGUAGES.map((lang, index) => {
          const isActive = active === lang.code || active.startsWith(lang.code);
          return (
            <button
              key={lang.code}
              type="button"
              onClick={() => i18n.changeLanguage(lang.code as SupportedLanguage)}
              aria-pressed={isActive}
              className={cn(
                'px-2.5 py-1 text-[11px] font-medium uppercase tracking-[0.08em] transition-colors',
                index > 0 && 'border-l border-[#E2E0D8]',
                isActive
                  ? 'bg-[#18181B] text-white'
                  : 'text-[#71717A] hover:bg-[#F4F2EB] hover:text-[#18181B]',
              )}
            >
              {lang.code.toUpperCase()}
            </button>
          );
        })}
      </div>
    </div>
  );
}

function AccountBlock({ staff, isLoggingOut, onLogout }: AccountBlockProps) {
  const { collapsed } = useSidebar();
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);

  if (!staff) return null;

  const name = staff.name ?? staff.username ?? '—';
  const username = staff.username ?? '';

  const trigger = collapsed ? (
    <PopoverTrigger
      render={(props) => (
        <button
          {...props}
          type="button"
          aria-label={name}
          className="grid h-10 w-10 place-items-center rounded-[8px] transition-colors hover:bg-[#F4F2EB]"
        />
      )}
    >
      <Avatar name={staff.name} username={staff.username} size={32} />
    </PopoverTrigger>
  ) : (
    <PopoverTrigger
      render={(props) => (
        <button
          {...props}
          type="button"
          className="flex w-full items-center gap-3 rounded-[8px] px-2 py-2 text-left transition-colors hover:bg-[#F4F2EB]"
          aria-label={`${name} account menu`}
        />
      )}
    >
      <Avatar name={staff.name} username={staff.username} size={32} />
      <span className="min-w-0 flex-1">
        <span className="block truncate text-[13px] font-medium leading-[18px] text-[#18181B]">{name}</span>
        {username && (
          <span className="block truncate text-[11px] leading-[14px] text-[#A1A1AA]">{username}</span>
        )}
      </span>
      <span aria-hidden className="text-[#A1A1AA]">
        <HugeiconsIcon icon={ArrowDown01Icon} size={14} strokeWidth={1.75} />
      </span>
    </PopoverTrigger>
  );

  return (
    <Popover open={open} onOpenChange={setOpen}>
      {trigger}
      <PopoverContent side="top" align={collapsed ? 'center' : 'start'} sideOffset={8} className="w-60 p-1.5">
        <div className="flex items-center gap-3 rounded-[6px] px-2 py-2">
          <Avatar name={staff.name} username={staff.username} size={36} />
          <div className="min-w-0 flex-1">
            <div className="truncate text-[13px] font-medium text-[#18181B]">{name}</div>
            {username && <div className="truncate text-[11px] text-[#A1A1AA]">{username}</div>}
          </div>
        </div>
        <div className="my-1 h-px bg-[#E2E0D8]" />
        <LanguagePicker />
        <div className="my-1 h-px bg-[#E2E0D8]" />
        <Link
          href="/scp/preferences"
          onClick={() => setOpen(false)}
          className="flex items-center gap-2.5 rounded-[6px] px-2.5 py-2 text-[13px] text-[#27272A] transition-colors hover:bg-[#F4F2EB]"
        >
          <HugeiconsIcon icon={Settings01Icon} size={16} strokeWidth={1.5} />
          <span className="flex-1">{t('dashboard.layout.preferences')}</span>
        </Link>
        <button
          type="button"
          onClick={() => { setOpen(false); onLogout(); }}
          disabled={isLoggingOut}
          className="flex w-full items-center gap-2.5 rounded-[6px] px-2.5 py-2 text-left text-[13px] text-[#DC2626] transition-colors hover:bg-[#FEF2F2] disabled:opacity-50"
        >
          <HugeiconsIcon icon={Logout01Icon} size={16} strokeWidth={1.5} />
          <span className="flex-1">{t('dashboard.layout.logout')}</span>
        </button>
      </PopoverContent>
    </Popover>
  );
}

interface SidebarProps {
  navItems: NavItem[];
  activeNav: string | undefined;
  activeSubId: string | undefined;
  isLoggingOut: boolean;
  onLogout: () => void;
  onClose?: () => void;
}

function Sidebar({ navItems, activeNav, activeSubId, isLoggingOut, onLogout, onClose }: SidebarProps) {
  const { collapsed } = useSidebar();
  const { props } = usePage<{
    auth?: { staff?: { name?: string; username?: string } | null };
  }>();
  const staff = props.auth?.staff;

  return (
    <div className="flex h-full flex-col bg-white">
      <div className={cn('flex items-center gap-3 py-4', collapsed ? 'justify-center px-2' : 'px-5')}>
        <div className="flex min-w-0 flex-1 items-center">
          <PanelSwitcher collapsed={collapsed} />
        </div>
        {onClose && (
          <button
            type="button"
            onClick={onClose}
            className="grid h-8 w-8 shrink-0 place-items-center rounded-[4px] text-[#A1A1AA] transition-colors hover:bg-[#F4F2EB] hover:text-[#18181B]"
            aria-label="Close menu"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M18 6 6 18" />
              <path d="m6 6 12 12" />
            </svg>
          </button>
        )}
      </div>

      <div className={cn('pb-2', collapsed ? 'flex justify-center px-2' : 'px-5')}>
        <SearchField collapsed={collapsed} />
      </div>

      <nav aria-label="Primary" className={cn('custom-scrollbar flex-1 overflow-y-auto py-3', collapsed ? 'px-2' : 'px-4')}>
        <ul className="flex flex-col gap-0.5">
          {navItems.map((item) => (
            <li key={item.id}>
              <NavRow
                item={item}
                activeNav={activeNav}
                activeSubId={activeSubId}
                onNavigate={onClose}
              />
            </li>
          ))}
        </ul>
      </nav>

      <div className={cn('border-t border-[#E2E0D8] py-2', collapsed ? 'flex justify-center px-2' : 'px-3')}>
        <AccountBlock staff={staff} isLoggingOut={isLoggingOut} onLogout={onLogout} />
      </div>
    </div>
  );
}

function SidebarTrigger({ className }: { className?: string }) {
  const { collapsed, toggleCollapsed, isMobile, setMobileOpen } = useSidebar();
  const handleClick = () => {
    if (isMobile) setMobileOpen(true);
    else toggleCollapsed();
  };
  const label = isMobile ? 'Open menu' : collapsed ? 'Expand sidebar' : 'Collapse sidebar';
  return (
    <button
      type="button"
      onClick={handleClick}
      aria-label={label}
      title={label}
      className={cn(
        'grid h-9 w-9 shrink-0 place-items-center rounded-[6px] border border-[#E2E0D8] text-[#71717A] transition-colors hover:bg-[#F4F2EB] hover:text-[#18181B]',
        className,
      )}
    >
      <HugeiconsIcon icon={SidebarLeft01Icon} size={18} strokeWidth={1.5} />
    </button>
  );
}

export default function AppShell({ children }: { children: ReactNode }) {
  const { url } = usePage();
  const { props } = usePage<{
    currentPanel?: 'scp' | 'admin';
    currentPanelNav?: { subId?: string };
  }>();

  const currentPanel = props.currentPanel || 'scp';
  const subId = props.currentPanelNav?.subId;
  const activeNav = useActiveNav(currentPanel, url, subId);
  const navItems = PANEL_NAV[currentPanel] || PANEL_NAV.scp;

  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const isLoggingOutRef = useRef(false);
  const isMobile = useMediaQuery(MOBILE_BREAKPOINT);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [collapsed, setCollapsed] = useState<boolean>(readInitialCollapsed);

  useEffect(() => {
    try {
      window.localStorage.setItem(SIDEBAR_COLLAPSED_KEY, collapsed ? '1' : '0');
    } catch {
      // ignore quota / privacy errors
    }
  }, [collapsed]);

  const toggleCollapsed = useCallback(() => setCollapsed((prev) => !prev), []);

  const sidebarCtx = useMemo<SidebarContextValue>(
    () => ({ collapsed, toggleCollapsed, isMobile, mobileOpen, setMobileOpen }),
    [collapsed, toggleCollapsed, isMobile, mobileOpen],
  );

  const [pageHeaderNode, setPageHeaderNode] = useState<ReactNode>(null);
  const setNode = useCallback((node: ReactNode) => setPageHeaderNode(node), []);

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

  const desktopWidth = collapsed ? SIDEBAR_COLLAPSED_WIDTH : SIDEBAR_EXPANDED_WIDTH;

  return (
    <PageHeaderSlotContext.Provider value={{ setNode }}>
      <SidebarContext.Provider value={sidebarCtx}>
        <TooltipProvider delay={250}>
          <div className="flex h-screen overflow-hidden bg-white font-sans text-[#18181B]" style={{ fontFeatureSettings: '"ss01", "cv11"' }}>
            {isMobile && mobileOpen && (
              <>
                <div
                  className="fixed inset-0 z-40 bg-[#18181B]/30 transition-opacity"
                  onClick={() => setMobileOpen(false)}
                />
                <aside
                  className="fixed inset-y-0 left-0 z-50 flex w-[260px] flex-col border-r border-[#E2E0D8] bg-white shadow-xl transition-transform"
                  style={{ transform: mobileOpen ? 'translateX(0)' : 'translateX(-100%)' }}
                >
                  {/* Mobile sheet always renders expanded — wrap in its own context override */}
                  <SidebarContext.Provider value={{ ...sidebarCtx, collapsed: false }}>
                    <Sidebar
                      navItems={navItems}
                      activeNav={activeNav}
                      activeSubId={subId}
                      isLoggingOut={isLoggingOut}
                      onLogout={() => { setMobileOpen(false); logout(); }}
                      onClose={() => setMobileOpen(false)}
                    />
                  </SidebarContext.Provider>
                </aside>
              </>
            )}

            {!isMobile && (
              <aside
                className="flex shrink-0 flex-col border-r border-[#E2E0D8] bg-white transition-[width] duration-200 ease-in-out"
                style={{ width: desktopWidth }}
              >
                <Sidebar
                  navItems={navItems}
                  activeNav={activeNav}
                  activeSubId={subId}
                  isLoggingOut={isLoggingOut}
                  onLogout={logout}
                />
              </aside>
            )}

            <div className="flex min-w-0 flex-1 flex-col overflow-hidden bg-white">
              <div className="flex shrink-0 items-center gap-3 border-b border-[#E2E0D8] bg-white px-6 py-4 sm:px-8 lg:px-10">
                <SidebarTrigger />
                <div className="min-w-0 flex-1">
                  {pageHeaderNode}
                </div>
              </div>

              <main className="custom-scrollbar flex-1 overflow-y-auto bg-white px-6 py-5 sm:px-8 lg:px-10">
                {children}
              </main>
            </div>
          </div>
        </TooltipProvider>
      </SidebarContext.Provider>
    </PageHeaderSlotContext.Provider>
  );
}

export const appShellLayout = (page: ReactElement) => <AppShell>{page}</AppShell>;
