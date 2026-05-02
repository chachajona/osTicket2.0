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
import { ArrowDown01Icon } from '@hugeicons/core-free-icons';

import { cn } from '@/lib/utils';
import { PANEL_NAV, type NavItem, type NavSubItem } from '@/lib/panelNavigation';
import { PanelSwitcher } from '@/components/panel/PanelSwitcher';
import { ADMIN_TAB_MAP } from '@/components/admin/AdminTabs';

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

const MOBILE_BREAKPOINT = '(max-width: 1023px)';

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

function SearchField() {
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
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#A1A1AA" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <circle cx="11" cy="11" r="8" />
          <path d="m21 21-4.3-4.3" />
        </svg>
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

function navRowClasses(state: 'active' | 'disabled' | 'idle'): string {
  return cn(
    'group flex w-full items-center gap-2.5 rounded-[8px] px-3 py-2 text-left text-[14px] leading-[22.75px] transition-colors duration-150',
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
        state === 'idle' && 'text-[#71717A] group-hover:text-[#18181B]',
      )}
      style={{ color }}
      aria-hidden
    >
      <HugeiconsIcon icon={icon} size={20} strokeWidth={1.5} />
    </span>
  );
}

function NavLeaf({ item, activeNav, onNavigate }: NavRowProps) {
  const { t } = useTranslation();
  const { id, labelKey, icon, href } = item;
  const isActive = activeNav === id;
  const disabled = !href;
  const state: 'active' | 'disabled' | 'idle' = isActive ? 'active' : disabled ? 'disabled' : 'idle';
  const label = t(labelKey, { defaultValue: labelKey });
  const className = navRowClasses(state);

  const content = (
    <>
      <NavIcon icon={icon} state={state} />
      <span className="min-w-0 flex-1 truncate">{label}</span>
    </>
  );

  if (disabled) {
    return (
      <button type="button" disabled aria-disabled="true" className={className}>
        {content}
      </button>
    );
  }

  return (
    <Link href={href!} className={className} onClick={onNavigate} aria-current={isActive ? 'page' : undefined}>
      {content}
    </Link>
  );
}

function NavGroup({ item, activeNav, activeSubId, onNavigate }: NavRowProps) {
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
        className={navRowClasses(state)}
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

interface SidebarProps {
  navItems: NavItem[];
  activeNav: string | undefined;
  activeSubId: string | undefined;
  isLoggingOut: boolean;
  onLogout: () => void;
  onClose?: () => void;
}

function Sidebar({ navItems, activeNav, activeSubId, isLoggingOut, onLogout, onClose }: SidebarProps) {
  const { t } = useTranslation();
  const { props } = usePage<{
    auth?: { staff?: { name?: string; username?: string } | null };
  }>();
  const staff = props.auth?.staff;

  return (
    <div className="flex h-full flex-col bg-white">
      <div className="flex items-center gap-3 px-5 py-4">
        <div className="flex min-w-0 flex-1 items-center">
          <PanelSwitcher />
        </div>
        {onClose && (
          <button
            type="button"
            onClick={onClose}
            className="grid h-8 w-8 place-items-center rounded-[4px] text-[#A1A1AA] transition-colors hover:bg-[#F4F2EB] hover:text-[#18181B]"
            aria-label="Close menu"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M18 6 6 18" />
              <path d="m6 6 12 12" />
            </svg>
          </button>
        )}
      </div>

      <div className="px-5 pb-2">
        <SearchField />
      </div>

      <nav aria-label="Primary" className="custom-scrollbar flex-1 overflow-y-auto px-4 py-3">
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

      <div className="border-t border-[#E2E0D8] px-4 py-3">
        <div className="flex gap-2">
          <Link
            href="/scp/preferences"
            className="inline-flex flex-1 items-center justify-center gap-1.5 rounded-[3px] bg-[#F4F2EB] px-3 py-2 text-[12px] font-medium uppercase tracking-[1.2px] text-[#27272A] transition-colors hover:bg-[#E2E0D8]"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="12" cy="12" r="3" />
              <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.67 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.67 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.67a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.33 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />
            </svg>
            <span className="text-[12px] uppercase tracking-[1.2px]">{t('dashboard.layout.preferences')}</span>
          </Link>

          <button
            type="button"
            onClick={onLogout}
            disabled={isLoggingOut}
            className="inline-flex flex-1 items-center justify-center gap-1.5 rounded-[3px] border border-[#E2E0D8] bg-white px-3 py-2 text-[12px] font-medium uppercase tracking-[1.2px] text-[#71717A] transition-colors hover:border-[#18181B] hover:text-[#18181B] disabled:opacity-50"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <polyline points="16 17 21 12 16 7" />
              <line x1="21" x2="9" y1="12" y2="12" />
            </svg>
            <span className="text-[12px] uppercase tracking-[1.2px]">{t('dashboard.layout.logout')}</span>
          </button>
        </div>

        {staff && (
          <div className="mt-3 flex items-center gap-2.5 px-1">
            <div className="h-7 w-7 rounded-full bg-[#F4F2EB]" />
            <div className="min-w-0">
              <p className="truncate text-[12px] font-medium leading-[16px] text-[#18181B]">{staff.name}</p>
              <p className="truncate text-[10px] uppercase tracking-[0.1em] text-[#A1A1AA]">{staff.username}</p>
            </div>
          </div>
        )}
      </div>
    </div>
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

  return (
    <PageHeaderSlotContext.Provider value={{ setNode }}>
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
              <Sidebar
                navItems={navItems}
                activeNav={activeNav}
                activeSubId={subId}
                isLoggingOut={isLoggingOut}
                onLogout={() => { setMobileOpen(false); logout(); }}
                onClose={() => setMobileOpen(false)}
              />
            </aside>
          </>
        )}

        {!isMobile && (
          <aside className="flex w-[260px] shrink-0 flex-col border-r border-[#E2E0D8] bg-white">
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
          <div className="flex shrink-0 items-center gap-4 border-b border-[#E2E0D8] bg-white px-6 py-4 sm:px-8 lg:px-10">
            {isMobile && (
              <button
                type="button"
                onClick={() => setMobileOpen(true)}
                className="grid h-9 w-9 shrink-0 place-items-center rounded-[4px] border border-[#E2E0D8] text-[#71717A] transition-colors hover:bg-[#F4F2EB] hover:text-[#18181B]"
                aria-label="Open menu"
              >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <line x1="4" x2="20" y1="12" y2="12" />
                  <line x1="4" x2="20" y1="6" y2="6" />
                  <line x1="4" x2="20" y1="18" y2="18" />
                </svg>
              </button>
            )}
            <div className="min-w-0 flex-1">
              {pageHeaderNode}
            </div>
          </div>

          <main className="custom-scrollbar flex-1 overflow-y-auto bg-white px-6 py-5 sm:px-8 lg:px-10">
            {children}
          </main>
        </div>
      </div>
    </PageHeaderSlotContext.Provider>
  );
}

export const appShellLayout = (page: ReactElement) => <AppShell>{page}</AppShell>;
