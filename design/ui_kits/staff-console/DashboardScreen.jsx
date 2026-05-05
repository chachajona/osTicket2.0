/* osTicket 2.0 — Dashboard Screen
   Aligned to DESIGN.md: Inter-only, orange primary, cream surfaces,
   Solar icons via Iconify, editorial caption style */

const DASH_NAV = [
  { icon: 'home-2-bold', label: 'Dashboard', id: 'dashboard' },
  { icon: 'ticket-bold', label: 'Tickets', id: 'tickets' },
  { icon: 'checklist-minimalistic-bold', label: 'Tasks', id: 'tasks' },
  { icon: 'book-minimalistic-bold', label: 'Knowledgebase', id: 'kb' },
];
const DASH_ADMIN = [
  { icon: 'settings-bold', label: 'Settings', id: 'settings' },
  { icon: 'users-group-two-rounded-bold', label: 'Agents', id: 'agents' },
  { icon: 'buildings-bold', label: 'Departments', id: 'depts' },
];

const DASH_STATS = [
  { label: 'Open Tickets', value: '142', change: '+12', positive: false },
  { label: 'Resolved Today', value: '38', change: '+8', positive: true },
  { label: 'Avg Response', value: '2.4h', change: '-18min', positive: true },
  { label: 'SLA Compliance', value: '94%', change: '+2%', positive: true },
];

const DASH_TICKETS = [
  { id: '#1247', subject: 'Unable to access VPN after password reset', requester: 'Maria Chen', dept: 'IT Support', status: 'open', updated: '5 min ago' },
  { id: '#1246', subject: 'License renewal request for Adobe Suite', requester: 'James Liu', dept: 'Procurement', status: 'pending', updated: '22 min ago' },
  { id: '#1245', subject: 'New employee onboarding — equipment request', requester: 'Sarah Patel', dept: 'HR', status: 'open', updated: '1 hour ago' },
  { id: '#1244', subject: 'Email forwarding rule not working correctly', requester: 'David Kim', dept: 'IT Support', status: 'resolved', updated: '2 hours ago' },
  { id: '#1243', subject: 'Conference room AV system troubleshooting', requester: 'Emma Wilson', dept: 'Facilities', status: 'open', updated: '3 hours ago' },
];

function StatusBadge({ status }) {
  const map = {
    open: { bg: '#FFF4EC', color: '#D66313', border: '#FDD2B4' },
    pending: { bg: '#FFFBEB', color: '#92400E', border: '#FCD34D' },
    resolved: { bg: '#F0FDF4', color: '#16A34A', border: '#BBF7D0' },
  };
  const c = map[status] || map.open;
  return React.createElement('span', {
    style: {
      display: 'inline-flex', padding: '2px 10px', borderRadius: '3px', fontSize: '10px', fontWeight: 500,
      letterSpacing: '0.1em', textTransform: 'uppercase', fontFamily: "'Inter', sans-serif",
      background: c.bg, color: c.color, border: `1px solid ${c.border}`,
    }
  }, status);
}

function DashboardScreen({ onLogout }) {
  const [activeNav, setActiveNav] = React.useState('dashboard');
  const [activeTab, setActiveTab] = React.useState(0);

  const captionStyle = { fontFamily: "'Inter', sans-serif", fontSize: '10px', fontWeight: 500, letterSpacing: '0.1em', textTransform: 'uppercase', lineHeight: '15px' };
  const thStyle = { textAlign: 'left', padding: '10px 16px', ...captionStyle, color: '#A1A1AA', borderBottom: '1px solid #E2E0D8' };
  const tdStyle = { padding: '14px 16px', borderBottom: '1px solid #F4F2EB', fontSize: '14px', color: '#18181B', fontFamily: "'Inter', sans-serif", lineHeight: '22.75px' };

  return React.createElement('div', {
    style: { display: 'flex', minHeight: '100vh', fontFamily: "'Inter', sans-serif", background: '#FFFFFF', color: '#18181B' }
  },
    // ─── Sidebar ─────────────────────────────────────────────
    React.createElement('nav', {
      style: { width: '260px', background: '#FFFFFF', borderRight: '1px solid #E2E0D8', display: 'flex', flexDirection: 'column', padding: '24px 0', flexShrink: 0 }
    },
      // Brand
      React.createElement('div', { style: { padding: '0 24px 24px', display: 'flex', alignItems: 'center', gap: '8px' } },
        // Gradient dot
        React.createElement('div', { style: { width: '8px', height: '8px', borderRadius: '50%', background: 'linear-gradient(135deg, #FB923C, #EC4899, #6366F1)', flexShrink: 0 } }),
        React.createElement('span', { style: { ...captionStyle, color: '#18181B' } }, 'osTicket'),
        React.createElement('span', { style: { ...captionStyle, color: '#A1A1AA' } }, '· v2.0'),
      ),
      React.createElement('div', { style: { margin: '0 24px 16px', height: '1px', background: '#E2E0D8' } }),

      // Main nav
      React.createElement('div', { style: { padding: '0 12px', marginBottom: '8px' } },
        React.createElement('div', { style: { ...captionStyle, color: '#A1A1AA', padding: '4px 12px 8px' } }, 'Main'),
        DASH_NAV.map(n => React.createElement(NavItem, { key: n.id, icon: n.icon, label: n.label, active: activeNav === n.id, onClick: () => setActiveNav(n.id) })),
      ),

      // Admin nav
      React.createElement('div', { style: { padding: '0 12px', marginTop: '8px' } },
        React.createElement('div', { style: { ...captionStyle, color: '#A1A1AA', padding: '4px 12px 8px' } }, 'Admin'),
        DASH_ADMIN.map(n => React.createElement(NavItem, { key: n.id, icon: n.icon, label: n.label, active: activeNav === n.id, onClick: () => setActiveNav(n.id) })),
      ),

      // Sign out
      React.createElement('div', { style: { marginTop: 'auto', padding: '0 12px' } },
        React.createElement('div', { style: { margin: '0 12px 12px', height: '1px', background: '#E2E0D8' } }),
        React.createElement('button', {
          onClick: onLogout,
          style: {
            display: 'flex', alignItems: 'center', gap: '10px', padding: '8px 12px', borderRadius: '8px',
            fontSize: '14px', fontWeight: 400, color: '#DC2626', background: 'transparent',
            border: 'none', cursor: 'pointer', width: '100%', textAlign: 'left',
            fontFamily: "'Inter', sans-serif", transition: 'all 150ms ease',
          }
        },
          React.createElement(SolarIcon, { name: 'logout-2-bold', size: 20, color: '#DC2626' }),
          'Sign Out',
        ),
      ),
    ),

    // ─── Main content ────────────────────────────────────────
    React.createElement('div', { style: { flex: 1, display: 'flex', flexDirection: 'column', background: '#FFFFFF' } },
      // Top bar
      React.createElement('div', {
        style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '16px 32px', borderBottom: '1px solid #E2E0D8' }
      },
        React.createElement('div', null,
          React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '12px' } },
            React.createElement('h1', { style: { fontSize: '20px', fontWeight: 500, letterSpacing: '-0.02em' } }, 'Dashboard'),
            React.createElement('span', { style: { ...captionStyle, color: '#A1A1AA' } }, '· Overview'),
          ),
          React.createElement('p', { style: { fontSize: '12px', color: '#A1A1AA', marginTop: '2px', letterSpacing: '0.1em', textTransform: 'uppercase', fontWeight: 500 } }, 'April 25, 2026'),
        ),
        React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '16px' } },
          React.createElement(TabsPill, { tabs: ['Overview', 'My Tickets', 'Reports'], active: activeTab, onChange: setActiveTab }),
          // Avatar
          React.createElement('div', {
            style: { width: '32px', height: '32px', borderRadius: '50%', background: '#F4F2EB', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '11px', fontWeight: 600, color: '#71717A', letterSpacing: '0.05em' }
          }, 'JD'),
        ),
      ),

      // Content
      React.createElement('div', { style: { padding: '32px', flex: 1 } },
        // Stats grid
        React.createElement('div', { style: { display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '16px', marginBottom: '32px' } },
          DASH_STATS.map(s => React.createElement('div', {
            key: s.label,
            style: { padding: '20px', borderRadius: '8px', border: '1px solid #E2E0D8', background: '#FFFFFF' }
          },
            React.createElement('div', { style: { ...captionStyle, color: '#A1A1AA' } }, s.label),
            React.createElement('div', { style: { fontSize: '28px', fontWeight: 500, letterSpacing: '-0.025em', color: '#18181B', marginTop: '8px', lineHeight: '28px' } }, s.value),
            React.createElement('div', { style: { fontSize: '12px', color: s.positive ? '#16A34A' : '#DC2626', marginTop: '6px', fontWeight: 500 } }, s.change),
          )),
        ),

        // Ticket table
        React.createElement('div', { style: { borderRadius: '8px', border: '1px solid #E2E0D8', overflow: 'hidden' } },
          React.createElement('div', { style: { padding: '16px 16px 12px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
            React.createElement('span', { style: { fontSize: '14px', fontWeight: 500 } }, 'Recent Tickets'),
            React.createElement(Button, { variant: 'primary', size: 'sm', icon: 'add-circle-bold' }, 'New Ticket'),
          ),
          React.createElement('table', { style: { width: '100%', borderCollapse: 'collapse' } },
            React.createElement('thead', null,
              React.createElement('tr', null,
                ['ID', 'Subject', 'Requester', 'Department', 'Status', 'Updated'].map(h =>
                  React.createElement('th', { key: h, style: thStyle }, h)
                ),
              ),
            ),
            React.createElement('tbody', null,
              DASH_TICKETS.map(t => React.createElement('tr', {
                key: t.id,
                style: { cursor: 'pointer', transition: 'background 100ms ease' },
                onMouseEnter: e => e.currentTarget.style.background = '#FAFAF8',
                onMouseLeave: e => e.currentTarget.style.background = 'transparent',
              },
                React.createElement('td', { style: { ...tdStyle, fontFamily: "'Geist Mono', monospace", fontSize: '12px', color: '#A1A1AA' } }, t.id),
                React.createElement('td', { style: { ...tdStyle, fontWeight: 500 } }, t.subject),
                React.createElement('td', { style: tdStyle }, t.requester),
                React.createElement('td', { style: { ...tdStyle, color: '#71717A' } }, t.dept),
                React.createElement('td', { style: tdStyle }, React.createElement(StatusBadge, { status: t.status })),
                React.createElement('td', { style: { ...tdStyle, color: '#A1A1AA', fontSize: '12px' } }, t.updated),
              )),
            ),
          ),
        ),
      ),
    ),
  );
}

Object.assign(window, { DashboardScreen, StatusBadge });
