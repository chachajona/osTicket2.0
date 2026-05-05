/* osTicket 2.0 — Ticket List View (Kirridesk-faithful) */

function TicketListView({ onOpenTicket, onNewTicket }) {
  const [selectedRows, setSelectedRows] = React.useState([]);
  const [hoveredRow, setHoveredRow] = React.useState(null);

  const toggleRow = (id, e) => { e.stopPropagation(); setSelectedRows(prev => prev.includes(id) ? prev.filter(r => r !== id) : [...prev, id]); };
  const toggleAll = () => setSelectedRows(prev => prev.length === TICKET_DATA.length ? [] : TICKET_DATA.map(t => t.id));

  const thStyle = {
    textAlign: 'left', padding: '10px 16px', fontSize: '11px', fontWeight: 500,
    letterSpacing: '0.04em', color: 'var(--text-muted)', borderBottom: '1px solid var(--border)',
    whiteSpace: 'nowrap', fontFamily: 'var(--font-sans)', background: 'var(--background)', position: 'sticky', top: 0, zIndex: 2,
  };
  const tdBase = {
    padding: '11px 16px', borderBottom: '1px solid #F4F2EB', fontSize: '13px',
    color: 'var(--text-primary)', fontFamily: 'var(--font-sans)', lineHeight: '20px', whiteSpace: 'nowrap',
  };

  return React.createElement('div', { style: { display: 'flex', flexDirection: 'column', height: '100%' } },

    /* ── Header ── */
    React.createElement('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '20px 28px 16px', flexShrink: 0 } },
      React.createElement('h1', { style: { fontSize: '20px', fontWeight: 600, letterSpacing: '-0.02em' } }, 'Ticket'),
      React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '10px' } },
        React.createElement(IconBtn, { icon: 'solar:menu-dots-bold', size: 36 }),
        React.createElement('button', { style: {
          padding: '9px 18px', borderRadius: 'var(--radius-sm)', border: '1px solid var(--border)',
          background: 'var(--background)', fontSize: '13px', fontWeight: 500, color: 'var(--text-primary)',
          cursor: 'pointer', fontFamily: 'var(--font-sans)', transition: 'all 150ms',
        } }, 'Focus Mode'),
        React.createElement(SplitButton, { label: 'Add Ticket', onClick: onNewTicket }),
      ),
    ),

    /* ── Filter bar ── */
    React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '6px', padding: '0 28px 12px', flexShrink: 0, flexWrap: 'wrap' } },
      /* Search */
      React.createElement('div', { style: { position: 'relative', width: '200px' } },
        React.createElement('iconify-icon', { icon: 'solar:magnifer-linear', width: 15, style: { position: 'absolute', left: 10, top: '50%', transform: 'translateY(-50%)', color: 'var(--text-secondary)' } }),
        React.createElement('input', {
          placeholder: 'Search',
          style: {
            width: '100%', padding: '7px 12px 7px 32px', background: 'var(--background)', border: '1px solid var(--border)',
            borderRadius: 'var(--radius-sm)', fontSize: '13px', fontFamily: 'var(--font-sans)', color: 'var(--text-primary)', outline: 'none',
          }
        }),
        React.createElement('div', { style: { position: 'absolute', right: 8, top: '50%', transform: 'translateY(-50%)', display: 'flex', gap: '2px', fontSize: '10px', fontFamily: 'var(--font-mono)', color: 'var(--text-secondary)', background: 'var(--surface)', border: '1px solid var(--border)', padding: '1px 5px', borderRadius: '3px' } }, '⌘ K'),
      ),
      /* Filter chips */
      ['Type', 'Source', 'Priority', 'Date Added'].map(f =>
        React.createElement('button', {
          key: f,
          style: {
            display: 'flex', alignItems: 'center', gap: '4px', padding: '7px 14px', borderRadius: 'var(--radius-sm)',
            border: '1px solid var(--border)', background: 'var(--background)', fontSize: '13px', fontWeight: 400,
            color: 'var(--text-muted)', cursor: 'pointer', fontFamily: 'var(--font-sans)', transition: 'all 150ms',
          },
          onMouseEnter: e => e.currentTarget.style.borderColor = 'var(--primary)',
          onMouseLeave: e => e.currentTarget.style.borderColor = 'var(--border)',
        },
          React.createElement('iconify-icon', { icon: 'solar:filter-linear', width: 13, style: { color: 'var(--text-secondary)' } }),
          f,
        )
      ),
      React.createElement('button', {
        style: {
          display: 'flex', alignItems: 'center', gap: '4px', padding: '7px 14px', borderRadius: 'var(--radius-sm)',
          border: '1px solid var(--border)', background: 'var(--background)', fontSize: '13px', fontWeight: 400,
          color: 'var(--text-muted)', cursor: 'pointer', fontFamily: 'var(--font-sans)',
        }
      },
        React.createElement('iconify-icon', { icon: 'solar:tuning-2-linear', width: 13, style: { color: 'var(--text-secondary)' } }),
        'Ticket Filters',
      ),
    ),

    /* ── Table ── */
    React.createElement('div', { style: { flex: 1, overflow: 'auto' }, className: 'custom-scrollbar' },
      React.createElement('table', { style: { width: '100%', borderCollapse: 'collapse' } },
        React.createElement('thead', null,
          React.createElement('tr', null,
            React.createElement('th', { style: { ...thStyle, width: 40, textAlign: 'center', paddingLeft: '28px' } },
              React.createElement('input', { type: 'checkbox', checked: selectedRows.length === TICKET_DATA.length && TICKET_DATA.length > 0, onChange: toggleAll, style: { cursor: 'pointer', accentColor: 'var(--primary)' } })
            ),
            React.createElement('th', { style: thStyle },
              React.createElement('span', { style: { display: 'flex', alignItems: 'center', gap: '3px' } }, 'Ticket ID', React.createElement('iconify-icon', { icon: 'solar:sort-vertical-linear', width: 12, style: { color: 'var(--neutral-200)' } })),
            ),
            React.createElement('th', { style: thStyle }, 'Subject'),
            React.createElement('th', { style: thStyle },
              React.createElement('span', { style: { display: 'flex', alignItems: 'center', gap: '3px' } }, 'Priority', React.createElement('iconify-icon', { icon: 'solar:sort-vertical-linear', width: 12, style: { color: 'var(--neutral-200)' } })),
            ),
            React.createElement('th', { style: thStyle },
              React.createElement('span', { style: { display: 'flex', alignItems: 'center', gap: '3px' } }, 'Type', React.createElement('iconify-icon', { icon: 'solar:sort-vertical-linear', width: 12, style: { color: 'var(--neutral-200)' } })),
            ),
            React.createElement('th', { style: thStyle },
              React.createElement('span', { style: { display: 'flex', alignItems: 'center', gap: '3px' } }, 'Client', React.createElement('iconify-icon', { icon: 'solar:sort-vertical-linear', width: 12, style: { color: 'var(--neutral-200)' } })),
            ),
            React.createElement('th', { style: thStyle },
              React.createElement('span', { style: { display: 'flex', alignItems: 'center', gap: '3px' } }, 'Request Date', React.createElement('iconify-icon', { icon: 'solar:sort-vertical-linear', width: 12, style: { color: 'var(--neutral-200)' } })),
            ),
            React.createElement('th', { style: { ...thStyle, width: 40 } }),
          )
        ),
        React.createElement('tbody', null,
          TICKET_DATA.map(t => React.createElement('tr', {
            key: t.id,
            onMouseEnter: () => setHoveredRow(t.id),
            onMouseLeave: () => setHoveredRow(null),
            onClick: () => onOpenTicket(t),
            style: {
              cursor: 'pointer', transition: 'background 80ms',
              background: selectedRows.includes(t.id) ? 'var(--primary-50)' : hoveredRow === t.id ? '#FAFAF8' : 'transparent',
            },
          },
            React.createElement('td', { style: { ...tdBase, textAlign: 'center', paddingLeft: '28px', width: 40 }, onClick: e => toggleRow(t.id, e) },
              React.createElement('input', { type: 'checkbox', checked: selectedRows.includes(t.id), readOnly: true, style: { cursor: 'pointer', accentColor: 'var(--primary)' } })
            ),
            React.createElement('td', { style: { ...tdBase, fontFamily: 'var(--font-mono)', fontSize: '12px', color: 'var(--text-muted)', fontWeight: 500 } }, '#' + t.id),
            React.createElement('td', { style: { ...tdBase, fontWeight: 400, maxWidth: '260px', overflow: 'hidden', textOverflow: 'ellipsis' } }, t.subject),
            React.createElement('td', { style: tdBase }, React.createElement(PriorityDot, { priority: t.priority })),
            React.createElement('td', { style: tdBase }, React.createElement(TypeBadge, { type: t.type })),
            React.createElement('td', { style: tdBase }, React.createElement(ClientCell, { name: t.client })),
            React.createElement('td', { style: { ...tdBase, fontSize: '12px', color: 'var(--text-muted)' } }, t.date),
            React.createElement('td', { style: { ...tdBase, width: 40, textAlign: 'center' } },
              React.createElement('iconify-icon', { icon: 'solar:menu-dots-bold', width: 16, style: { color: 'var(--neutral-200)' } })
            ),
          ))
        ),
      ),
    ),
  );
}

Object.assign(window, { TicketListView });
