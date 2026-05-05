/* osTicket 2.0 — Ticket Detail View (Kirridesk-faithful) */

function TicketDetailView({ ticket, onBack }) {
  const [replyText, setReplyText] = React.useState('');
  const [activeTab, setActiveTab] = React.useState('conversation');
  const [moreMenuOpen, setMoreMenuOpen] = React.useState(false);

  const t = ticket || TICKET_DATA[0];
  const tabs = [
    { id: 'conversation', label: 'Conversation' },
    { id: 'task', label: 'Task' },
    { id: 'activity', label: 'Activity Logs' },
    { id: 'notes', label: 'Notes' },
  ];

  /* Right sidebar icon buttons */
  const sideIcons = [
    'solar:user-rounded-linear',
    'solar:users-group-rounded-linear',
    'solar:chat-round-dots-linear',
    'solar:phone-linear',
    'solar:letter-linear',
  ];

  return React.createElement('div', { style: { display: 'flex', height: '100%', overflow: 'hidden' } },

    /* ── Center: Thread area ── */
    React.createElement('div', { style: { flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 } },

      /* Top bar */
      React.createElement('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '14px 24px', borderBottom: '1px solid var(--border)', flexShrink: 0 } },
        React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '12px', minWidth: 0 } },
          /* Back */
          React.createElement('button', {
            onClick: onBack,
            style: { display: 'flex', alignItems: 'center', gap: '6px', background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-muted)', fontFamily: 'var(--font-sans)', fontSize: '13px', fontWeight: 500, padding: 0, whiteSpace: 'nowrap' }
          },
            React.createElement('iconify-icon', { icon: 'solar:arrow-left-linear', width: 16 }),
            'Ticket List',
          ),
          /* Prev/Next */
          React.createElement('div', { style: { display: 'flex', gap: '2px' } },
            React.createElement(IconBtn, { icon: 'solar:alt-arrow-left-linear', size: 28 }),
            React.createElement(IconBtn, { icon: 'solar:alt-arrow-right-linear', size: 28 }),
          ),
          /* Ticket title */
          React.createElement('div', { style: { display: 'flex', alignItems: 'baseline', gap: '10px', minWidth: 0 } },
            React.createElement('span', { style: { fontFamily: 'var(--font-mono)', fontSize: '14px', fontWeight: 600, color: 'var(--text-primary)', flexShrink: 0 } }, '#' + t.id),
            React.createElement('span', { style: { fontSize: '15px', fontWeight: 500, color: 'var(--text-primary)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' } }, t.subject || 'Help me cancel my order.'),
          ),
        ),
        React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '10px', flexShrink: 0, position: 'relative' } },
          React.createElement(IconBtn, { icon: 'solar:menu-dots-bold', size: 34, onClick: () => setMoreMenuOpen(!moreMenuOpen) }),
          React.createElement(SplitButton, { label: 'Submit as New' }),
          /* Dropdown menu */
          moreMenuOpen && React.createElement('div', {
            style: {
              position: 'absolute', top: '100%', right: 60, marginTop: 4, background: 'var(--background)',
              border: '1px solid var(--border)', borderRadius: 'var(--radius-md)', boxShadow: '0 8px 24px rgba(0,0,0,0.1)',
              padding: '6px 0', zIndex: 50, width: 180,
            }
          },
            ['Remote Assist', 'Refresh', 'Filters', 'Merge Ticket', 'Add to Shortcut', 'Delete'].map((item, i) =>
              React.createElement('button', {
                key: item,
                style: {
                  display: 'flex', alignItems: 'center', gap: '8px', width: '100%', padding: '8px 16px',
                  border: 'none', background: 'none', cursor: 'pointer', fontSize: '13px',
                  color: item === 'Delete' ? 'var(--destructive)' : 'var(--text-primary)',
                  fontFamily: 'var(--font-sans)', transition: 'background 100ms', textAlign: 'left',
                },
                onMouseEnter: e => e.currentTarget.style.background = 'var(--surface)',
                onMouseLeave: e => e.currentTarget.style.background = 'none',
              },
                React.createElement('iconify-icon', { icon: [
                  'solar:monitor-linear', 'solar:refresh-linear', 'solar:filter-linear',
                  'solar:copy-linear', 'solar:bookmark-linear', 'solar:trash-bin-trash-linear'
                ][i], width: 16, style: { color: item === 'Delete' ? 'var(--destructive)' : 'var(--text-muted)' } }),
                item,
              )
            ),
          ),
        ),
      ),

      /* Tabs */
      React.createElement('div', { style: { display: 'flex', justifyContent: 'center', gap: '0', borderBottom: '1px solid var(--border)', flexShrink: 0 } },
        tabs.map(tab => React.createElement('button', {
          key: tab.id, onClick: () => setActiveTab(tab.id),
          style: {
            padding: '10px 20px', fontSize: '13px', fontWeight: activeTab === tab.id ? 500 : 400,
            color: activeTab === tab.id ? 'var(--primary)' : 'var(--text-muted)',
            background: 'none', border: 'none', borderBottom: activeTab === tab.id ? '2px solid var(--primary)' : '2px solid transparent',
            cursor: 'pointer', fontFamily: 'var(--font-sans)', marginBottom: '-1px', transition: 'all 150ms',
          }
        }, tab.label))
      ),

      /* Thread */
      React.createElement('div', { style: { flex: 1, overflow: 'auto', padding: '24px 32px' }, className: 'custom-scrollbar' },
        THREAD_MESSAGES.map(msg => {
          if (msg.type === 'system') {
            return React.createElement('div', { key: msg.id, style: { display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '24px' } },
              React.createElement('div', { style: { width: 32, height: 32, borderRadius: '50%', background: 'var(--surface)', border: '1px solid var(--border)', display: 'flex', alignItems: 'center', justifyContent: 'center' } },
                React.createElement('iconify-icon', { icon: 'solar:ticket-linear', width: 14, style: { color: 'var(--text-muted)' } }),
              ),
              React.createElement('span', { style: { fontSize: '13px', color: 'var(--text-muted)' } },
                React.createElement('strong', { style: { fontWeight: 500, color: 'var(--text-primary)' } }, msg.content),
                ' · ' + msg.time,
              ),
            );
          }
          const isAgent = msg.type === 'agent';
          return React.createElement('div', { key: msg.id, style: { display: 'flex', gap: '12px', marginBottom: '24px' } },
            React.createElement('div', { style: {
              width: 36, height: 36, borderRadius: '50%', flexShrink: 0,
              background: isAgent ? 'var(--gradient-brand)' : '#E2E0D8',
              color: isAgent ? '#fff' : 'var(--text-muted)',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              fontSize: '11px', fontWeight: 600, letterSpacing: '0.02em',
            } }, msg.avatar),
            React.createElement('div', { style: { flex: 1 } },
              React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px', flexWrap: 'wrap' } },
                React.createElement('span', { style: { fontSize: '13px', fontWeight: 600, color: 'var(--text-primary)' } }, msg.author),
                React.createElement('span', { style: { fontSize: '12px', color: 'var(--text-secondary)' } }, '· ' + msg.time),
                React.createElement('span', { style: { fontSize: '11px', color: 'var(--text-secondary)' } }, '· Via'),
                msg.via && React.createElement('span', { style: { display: 'inline-flex', alignItems: 'center', gap: '3px', fontSize: '11px', color: 'var(--text-muted)' } },
                  React.createElement('iconify-icon', { icon: msg.via === 'Email' ? 'solar:letter-linear' : 'solar:chat-round-dots-linear', width: 12, style: { color: msg.via === 'Whatsapp' ? '#25D366' : 'var(--text-secondary)' } }),
                  msg.via,
                ),
              ),
              React.createElement('p', { style: { fontSize: '14px', lineHeight: '22px', color: 'var(--text-primary)' } }, msg.content),
            ),
          );
        }),
      ),

      /* ── Composer ── */
      React.createElement('div', { style: { borderTop: '1px solid var(--border)', padding: '16px 32px 16px', flexShrink: 0, background: 'var(--background)' } },
        React.createElement('div', { style: { background: 'var(--background)', border: '1px solid var(--border)', borderRadius: 'var(--radius-lg)', padding: '14px 16px', boxShadow: '0 -2px 8px rgba(0,0,0,0.03)' } },
          /* Via / From row */
          React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '10px' } },
            React.createElement('div', { style: { width: 32, height: 32, borderRadius: '50%', background: 'var(--gradient-brand)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '10px', fontWeight: 600, flexShrink: 0 } }, 'FIK'),
            React.createElement('span', { style: { fontSize: '12px', color: 'var(--text-secondary)' } }, 'Via'),
            React.createElement(ChannelPill, { channel: 'Whatsapp' }),
            React.createElement('span', { style: { fontSize: '12px', color: 'var(--text-secondary)' } }, 'From'),
            React.createElement(FromPill, { from: 'Fikri Studio Sales' }),
            React.createElement('div', { style: { marginLeft: 'auto' } },
              React.createElement(IconBtn, { icon: 'solar:refresh-linear', size: 28 }),
            ),
          ),
          /* Text input */
          React.createElement('input', {
            value: replyText, onChange: e => setReplyText(e.target.value),
            placeholder: 'Comment or Type "/" For commands',
            style: {
              width: '100%', padding: '8px 0', border: 'none', fontSize: '14px',
              fontFamily: 'var(--font-sans)', color: 'var(--text-primary)', outline: 'none',
              background: 'transparent',
            }
          }),
          /* Toolbar */
          React.createElement('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginTop: '8px', paddingTop: '8px', borderTop: '1px solid var(--border)' } },
            React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '2px' } },
              ['solar:text-bold-linear', 'solar:emoji-funny-circle-linear', 'solar:paperclip-linear', 'solar:microphone-linear'].map(ic =>
                React.createElement('button', { key: ic, style: { width: 30, height: 30, display: 'flex', alignItems: 'center', justifyContent: 'center', border: 'none', background: 'none', cursor: 'pointer', borderRadius: '4px', color: 'var(--text-muted)', transition: 'all 100ms' },
                  onMouseEnter: e => { e.currentTarget.style.background = 'var(--surface)'; e.currentTarget.style.color = 'var(--text-primary)'; },
                  onMouseLeave: e => { e.currentTarget.style.background = 'none'; e.currentTarget.style.color = 'var(--text-muted)'; },
                }, React.createElement('iconify-icon', { icon: ic, width: 16 }))
              ),
              React.createElement('div', { style: { width: 1, height: 16, background: 'var(--border)', margin: '0 6px' } }),
              React.createElement('button', { style: { display: 'flex', alignItems: 'center', gap: '4px', padding: '4px 10px', border: 'none', background: 'none', cursor: 'pointer', fontSize: '12px', fontWeight: 500, color: 'var(--text-muted)', fontFamily: 'var(--font-sans)' } },
                'Macros', React.createElement('iconify-icon', { icon: 'solar:alt-arrow-down-linear', width: 12 })),
            ),
            React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '10px' } },
              React.createElement('button', { style: { background: 'none', border: 'none', cursor: 'pointer', fontSize: '13px', fontWeight: 500, color: 'var(--text-muted)', fontFamily: 'var(--font-sans)' } }, 'End Chat'),
              React.createElement('button', { style: {
                padding: '8px 24px', borderRadius: 'var(--radius-sm)', background: 'var(--neutral-500)',
                border: 'none', color: '#fff', fontFamily: 'var(--font-sans)', fontSize: '13px', fontWeight: 500,
                cursor: 'pointer', transition: 'background 150ms',
              } }, 'Send'),
            ),
          ),
        ),
      ),
    ),

    /* ── Right sidebar: Ticket Details ── */
    React.createElement('div', { style: { width: 260, borderLeft: '1px solid var(--border)', display: 'flex', flexShrink: 0, overflow: 'hidden' } },
      /* Main sidebar content */
      React.createElement('div', { style: { flex: 1, overflow: 'auto', padding: '20px 16px' }, className: 'custom-scrollbar' },
        React.createElement('h3', { style: { fontSize: '14px', fontWeight: 600, marginBottom: '20px' } }, 'Ticket Details'),

        /* Ticket type */
        React.createElement('div', { style: { marginBottom: '18px' } },
          React.createElement('label', { style: { fontSize: '12px', fontWeight: 500, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' } }, 'Ticket type'),
          React.createElement('select', { defaultValue: 'Incident', style: {
            width: '100%', padding: '8px 12px', borderRadius: 'var(--radius-sm)', border: '1px solid var(--border)',
            fontSize: '13px', fontFamily: 'var(--font-sans)', color: 'var(--text-primary)', background: 'var(--background)',
            appearance: 'none', cursor: 'pointer',
            backgroundImage: `url("data:image/svg+xml,%3Csvg width='12' height='12' viewBox='0 0 12 12' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M3 5l3 3 3-3' fill='none' stroke='%23A1A1AA' stroke-width='1.5'/%3E%3C/svg%3E")`,
            backgroundRepeat: 'no-repeat', backgroundPosition: 'right 10px center', outline: 'none',
          } },
            ['Incident', 'Problem', 'Question', 'Suggestion'].map(o => React.createElement('option', { key: o, value: o }, o)),
          ),
        ),

        /* Priority */
        React.createElement('div', { style: { marginBottom: '18px' } },
          React.createElement('label', { style: { fontSize: '12px', fontWeight: 500, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' } }, 'Priority'),
          React.createElement(PrioritySegmented, { value: t.priority || 'High', onChange: () => {} }),
        ),

        /* Linked Problem */
        React.createElement('div', { style: { marginBottom: '18px' } },
          React.createElement('label', { style: { fontSize: '12px', fontWeight: 500, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' } }, 'Linked Problem'),
          React.createElement('select', { defaultValue: '', style: {
            width: '100%', padding: '8px 12px', borderRadius: 'var(--radius-sm)', border: '1px solid var(--border)',
            fontSize: '13px', fontFamily: 'var(--font-sans)', color: 'var(--text-secondary)', background: 'var(--background)',
            appearance: 'none', cursor: 'pointer',
            backgroundImage: `url("data:image/svg+xml,%3Csvg width='12' height='12' viewBox='0 0 12 12' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M3 5l3 3 3-3' fill='none' stroke='%23A1A1AA' stroke-width='1.5'/%3E%3C/svg%3E")`,
            backgroundRepeat: 'no-repeat', backgroundPosition: 'right 10px center', outline: 'none',
          } },
            React.createElement('option', { value: '' }, 'Select problems'),
          ),
        ),

        /* Tags */
        React.createElement('div', { style: { marginBottom: '18px' } },
          React.createElement('label', { style: { fontSize: '12px', fontWeight: 500, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' } }, 'Tags'),
          React.createElement('div', { style: { display: 'flex', gap: '6px', flexWrap: 'wrap', padding: '10px', border: '1px solid var(--border)', borderRadius: 'var(--radius-sm)', minHeight: '60px' } },
            React.createElement(TagChip, { label: 'Support', onRemove: () => {} }),
            React.createElement(TagChip, { label: 'Order', onRemove: () => {} }),
          ),
        ),

        /* SLA cards */
        React.createElement('div', { style: { display: 'flex', flexDirection: 'column', gap: '10px', marginTop: '24px' } },
          React.createElement('div', { style: { padding: '12px', borderRadius: 'var(--radius-md)', border: '1px solid var(--border)', background: 'var(--background)' } },
            React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '4px' } },
              React.createElement('div', { style: { width: 6, height: 6, borderRadius: '50%', background: 'var(--primary)' } }),
              React.createElement('span', { style: { fontSize: '11px', fontWeight: 500, color: 'var(--text-muted)' } }, 'First Response Due'),
            ),
            React.createElement('p', { style: { fontSize: '13px', fontWeight: 500 } }, 'Wed, 14 Dec 2022, 06:00PM'),
          ),
          React.createElement('div', { style: { padding: '12px', borderRadius: 'var(--radius-md)', border: '1px solid var(--border)', background: 'var(--background)' } },
            React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '4px' } },
              React.createElement('div', { style: { width: 6, height: 6, borderRadius: '50%', background: 'var(--secondary)' } }),
              React.createElement('span', { style: { fontSize: '11px', fontWeight: 500, color: 'var(--text-muted)' } }, 'Resolution Due'),
            ),
            React.createElement('p', { style: { fontSize: '13px', fontWeight: 500 } }, 'Wed, 14 Dec 2022, 06:00PM'),
          ),
        ),
      ),

      /* Icon strip along right edge */
      React.createElement('div', { style: { width: 40, borderLeft: '1px solid var(--border)', display: 'flex', flexDirection: 'column', alignItems: 'center', paddingTop: '16px', gap: '4px', flexShrink: 0 } },
        sideIcons.map((ic, i) =>
          React.createElement('button', {
            key: ic,
            style: {
              width: 32, height: 32, borderRadius: 'var(--radius-sm)', border: 'none',
              background: i === 0 ? 'var(--primary)' : 'none',
              color: i === 0 ? '#fff' : 'var(--text-secondary)',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              cursor: 'pointer', transition: 'all 150ms',
            },
            onMouseEnter: e => { if (i !== 0) { e.currentTarget.style.background = 'var(--surface)'; e.currentTarget.style.color = 'var(--text-primary)'; } },
            onMouseLeave: e => { if (i !== 0) { e.currentTarget.style.background = 'none'; e.currentTarget.style.color = 'var(--text-secondary)'; } },
          }, React.createElement('iconify-icon', { icon: ic, width: 16 }))
        ),
      ),
    ),
  );
}

Object.assign(window, { TicketDetailView });
