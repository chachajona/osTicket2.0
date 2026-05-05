/* osTicket 2.0 — Shared Ticket Data & Components (Kirridesk-faithful) */

const TICKET_DATA = [
  { id: 'TC-192', subject: 'Help, I order wrong product...', priority: 'High', type: 'Incident', source: 'Whatsapp', client: 'Santi Carloza', date: '07/11/2023, 06:25AM' },
  { id: 'TC-191', subject: 'My Suggestion for this product', priority: 'Low', type: 'Suggestion', source: 'Email', client: 'Fast Response', date: '06/11/2023, 11:47PM' },
  { id: 'TC-190', subject: 'Can i use the TV in bathroom?', priority: 'Medium', type: 'Question', source: 'Portal', client: 'Arlene McCoy', date: '06/11/2023, 05:31AM' },
  { id: 'TC-189', subject: 'Your offline store is dirty', priority: 'Low', type: 'Suggestion', source: 'Email', client: 'Darlene Robertson', date: '05/11/2023, 09:05PM' },
  { id: 'TC-188', subject: 'Can i get the free battery for ...', priority: 'Low', type: 'Question', source: 'Whatsapp', client: 'Jerome Bell', date: '04/11/2023, 02:30PM' },
  { id: 'TC-187', subject: "I can't move this stroller, it's s...", priority: 'Medium', type: 'Problem', source: 'Email', client: 'Cody Fisher', date: '04/11/2023, 11:28AM' },
  { id: 'TC-186', subject: 'Help! My package is lost', priority: 'Low', type: 'Incident', source: 'Phone', client: 'Courtney Henry', date: '04/11/2023, 10:10AM' },
  { id: 'TC-185', subject: 'This remote tv is broken, can ...', priority: 'Medium', type: 'Problem', source: 'Portal', client: 'Leslie Alexander', date: '31/10/2023, 04:13PM' },
  { id: 'TC-184', subject: "Stroller wheel stuck, i won't a...", priority: 'Medium', type: 'Problem', source: 'Email', client: 'Robert Fox', date: '30/10/2023, 07:46PM' },
  { id: 'TC-183', subject: 'We missing the wheel of the ...', priority: 'High', type: 'Incident', source: 'Whatsapp', client: 'Ronald Richards', date: '29/10/2023, 03:07AM' },
  { id: 'TC-182', subject: 'Black screen on this monitor', priority: 'High', type: 'Incident', source: 'Email', client: 'Floyd Miles', date: '27/10/2023, 12:00PM' },
  { id: 'TC-181', subject: 'How to install this? can you h...', priority: 'Medium', type: 'Question', source: 'Portal', client: 'Esther Howard', date: '26/10/2023, 01:04PM' },
  { id: 'TC-180', subject: "Stroller wheel stuck, i won't a...", priority: 'Low', type: 'Incident', source: 'Email', client: 'Cameron Williamson', date: '26/10/2023, 10:33AM' },
  { id: 'TC-179', subject: 'Help, I order wrong product...', priority: 'High', type: 'Incident', source: 'Whatsapp', client: 'Marvin McKinney', date: '23/10/2023, 04:38AM' },
  { id: 'TC-178', subject: 'Black screen on this monitor', priority: 'High', type: 'Problem', source: 'Phone', client: 'Sarah Johnson', date: '22/10/2023, 10:07PM' },
  { id: 'TC-177', subject: 'Help, I order wrong product...', priority: 'Low', type: 'Incident', source: 'Email', client: 'David Smith', date: '21/10/2023, 11:2 PM' },
  { id: 'TC-176', subject: 'We missing the wheel of the ...', priority: 'Medium', type: 'Incident', source: 'Portal', client: 'Emily Davis', date: '18/10/2023, 06:14PM' },
  { id: 'TC-175', subject: 'Help! My package is lost', priority: 'High', type: 'Problem', source: 'Whatsapp', client: 'Annette Black', date: '18/10/2023, 12:20AM' },
  { id: 'TC-174', subject: "I can't move this stroller, it's s...", priority: 'Medium', type: 'Question', source: 'Email', client: 'Michael Brown', date: '18/10/2023, 08:47PM' },
  { id: 'TC-173', subject: 'My Suggestion for this product', priority: 'Low', type: 'Question', source: 'Portal', client: 'Jessica Lee', date: '16/10/2023, 12:10AM' },
  { id: 'TC-172', subject: 'Help, I order wrong product...', priority: 'High', type: 'Suggestion', source: 'Email', client: 'Kristi Bruen', date: '14/10/2023, 04:13AM' },
];

const THREAD_MESSAGES = [
  { id: 0, type: 'system', content: 'Ticket Created by Fikri Studio for Martin Ødegaard', time: '13:12 AM' },
  { id: 1, type: 'agent', author: 'Fikri Studio', avatar: 'FIK', time: '11:12 AM', via: 'Email', content: 'Hello! We\'re here to help you with any inquiries' },
  { id: 2, type: 'customer', author: 'Martin Ødegaard', avatar: 'MO', time: '11:12 AM', via: 'Whatsapp', content: 'Hello. I recently made a purchase on your website but unfortunately, I ordered the wrong product. Can you help me cancel the order?' },
];

/* Priority dot + label */
function PriorityDot({ priority, size = 'default' }) {
  const colors = { High: '#DC2626', Medium: '#CA8A04', Low: '#16A34A', Urgent: '#DC2626' };
  const c = colors[priority] || '#A1A1AA';
  return React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '6px' } },
    React.createElement('div', { style: { width: 6, height: 6, borderRadius: '50%', background: c, flexShrink: 0 } }),
    React.createElement('span', { style: { fontSize: size === 'sm' ? '12px' : '13px', color: c, fontWeight: 500 } }, priority),
  );
}

/* Type badge */
function TypeBadge({ type }) {
  const colors = {
    Incident: { bg: '#FEF2F2', color: '#DC2626', border: '#FECACA' },
    Problem: { bg: '#FFF4EC', color: '#D66313', border: '#FDD2B4' },
    Question: { bg: '#F0FDF4', color: '#16A34A', border: '#BBF7D0' },
    Suggestion: { bg: '#EEF2FF', color: '#6366F1', border: '#C7D2FE' },
  };
  const c = colors[type] || colors.Incident;
  return React.createElement('span', {
    style: {
      display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '2px 10px', borderRadius: '3px',
      fontSize: '11px', fontWeight: 500, fontFamily: 'var(--font-sans)',
      background: c.bg, color: c.color, border: `1px solid ${c.border}`,
    }
  },
    React.createElement('div', { style: { width: 5, height: 5, borderRadius: '50%', background: c.color } }),
    type,
  );
}

/* Client avatar + name */
function ClientCell({ name }) {
  const initials = name.split(' ').map(n => n[0]).join('').slice(0, 2);
  const hue = name.charCodeAt(0) * 7 % 360;
  return React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
    React.createElement('div', { style: {
      width: 28, height: 28, borderRadius: '50%', background: `oklch(0.7 0.12 ${hue})`,
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      fontSize: '10px', fontWeight: 600, color: '#fff', flexShrink: 0, letterSpacing: '0.02em',
    } }, initials),
    React.createElement('span', { style: { fontSize: '13px' } }, name),
  );
}

/* Priority segmented control (Low / Medium / High) */
function PrioritySegmented({ value, onChange }) {
  const opts = [
    { label: 'Low', color: '#16A34A' },
    { label: 'Medium', color: '#CA8A04' },
    { label: 'High', color: '#DC2626' },
  ];
  return React.createElement('div', { style: { display: 'flex', border: '1px solid var(--border)', borderRadius: 'var(--radius-sm)', overflow: 'hidden' } },
    opts.map((o, i) => React.createElement('button', {
      key: o.label, onClick: () => onChange(o.label),
      style: {
        flex: 1, padding: '7px 0', fontSize: '12px', fontWeight: 500,
        display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '5px',
        background: value === o.label ? 'var(--surface)' : 'var(--background)',
        color: 'var(--text-primary)', border: 'none',
        borderRight: i < 2 ? '1px solid var(--border)' : 'none',
        cursor: 'pointer', fontFamily: 'var(--font-sans)', transition: 'all 150ms',
        outline: value === o.label ? '2px solid var(--text-primary)' : 'none',
        outlineOffset: '-2px', borderRadius: value === o.label ? '3px' : '0',
      }
    },
      React.createElement('div', { style: { width: 6, height: 6, borderRadius: '50%', background: o.color } }),
      o.label,
    )),
  );
}

/* Chip tag */
function TagChip({ label, onRemove }) {
  return React.createElement('span', { style: { display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '3px 8px', borderRadius: '3px', background: 'var(--surface)', fontSize: '12px', fontWeight: 500, color: 'var(--text-primary)' } },
    label,
    onRemove && React.createElement('button', { onClick: onRemove, style: { background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-muted)', fontSize: '14px', lineHeight: 1, padding: 0, display: 'flex' } }, '×'),
  );
}

/* Split button (green primary, matching Kirridesk) */
function SplitButton({ label, onClick }) {
  const [hov, setHov] = React.useState(false);
  return React.createElement('div', { style: { display: 'flex', borderRadius: 'var(--radius-sm)', overflow: 'hidden', boxShadow: 'rgba(249,115,22,0.2) 0 2px 6px' } },
    React.createElement('button', {
      onClick, onMouseEnter: () => setHov(true), onMouseLeave: () => setHov(false),
      style: {
        background: hov ? 'var(--primary-600)' : 'var(--primary)', border: 'none', color: '#fff',
        fontFamily: 'var(--font-sans)', fontSize: '13px', fontWeight: 500,
        cursor: 'pointer', padding: '9px 18px', transition: 'background 150ms', whiteSpace: 'nowrap',
      }
    }, label),
    React.createElement('div', { style: { width: 1, background: 'rgba(255,255,255,0.3)' } }),
    React.createElement('button', {
      style: { background: hov ? 'var(--primary-600)' : 'var(--primary)', border: 'none', color: '#fff', padding: '9px 10px', cursor: 'pointer', display: 'flex', alignItems: 'center', transition: 'background 150ms' }
    }, React.createElement('iconify-icon', { icon: 'solar:alt-arrow-down-linear', width: 14 })),
  );
}

/* Icon button */
function IconBtn({ icon, onClick, size = 34, tooltip, style: extraStyle }) {
  const [hov, setHov] = React.useState(false);
  return React.createElement('button', {
    onClick, title: tooltip,
    onMouseEnter: () => setHov(true), onMouseLeave: () => setHov(false),
    style: {
      width: size, height: size, borderRadius: 'var(--radius-sm)',
      border: '1px solid var(--border)', background: hov ? 'var(--surface)' : 'var(--background)',
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      color: hov ? 'var(--text-primary)' : 'var(--text-muted)',
      cursor: 'pointer', transition: 'all 150ms', flexShrink: 0, ...extraStyle,
    }
  }, React.createElement('iconify-icon', { icon, width: size <= 30 ? 14 : 16 }));
}

/* Via channel pill */
function ChannelPill({ channel }) {
  const icons = { Email: 'solar:letter-linear', Whatsapp: 'solar:chat-round-dots-linear', Phone: 'solar:phone-linear', Portal: 'solar:monitor-linear' };
  return React.createElement('span', {
    style: { display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '3px 10px', borderRadius: '32px', background: 'var(--surface)', border: '1px solid var(--border)', fontSize: '12px', fontWeight: 500, color: 'var(--text-primary)' }
  },
    React.createElement('iconify-icon', { icon: icons[channel] || 'solar:letter-linear', width: 13, style: { color: channel === 'Whatsapp' ? '#25D366' : 'var(--text-muted)' } }),
    channel,
    React.createElement('iconify-icon', { icon: 'solar:alt-arrow-down-linear', width: 12, style: { color: 'var(--text-secondary)', marginLeft: '2px' } }),
  );
}

/* From pill */
function FromPill({ from }) {
  return React.createElement('span', {
    style: { display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '3px 10px', borderRadius: '32px', background: 'var(--neutral-500)', fontSize: '12px', fontWeight: 500, color: '#fff' }
  },
    from,
    React.createElement('iconify-icon', { icon: 'solar:alt-arrow-down-linear', width: 12, style: { color: 'rgba(255,255,255,0.6)', marginLeft: '2px' } }),
  );
}

Object.assign(window, { TICKET_DATA, THREAD_MESSAGES, PriorityDot, TypeBadge, ClientCell, PrioritySegmented, TagChip, SplitButton, IconBtn, ChannelPill, FromPill });
