/* osTicket 2.0 — New Ticket Modal (Kirridesk-faithful) */

function NewTicketModal({ onClose, onSubmit }) {
  const [ticketName, setTicketName] = React.useState('');
  const [priority, setPriority] = React.useState('Medium');
  const [ticketType, setTicketType] = React.useState('');
  const [requester, setRequester] = React.useState('');
  const [assignee, setAssignee] = React.useState('');
  const [tags, setTags] = React.useState('');
  const [followers, setFollowers] = React.useState('');
  const [message, setMessage] = React.useState('');
  const [submitting, setSubmitting] = React.useState(false);

  const selectStyle = {
    width: '100%', padding: '8px 12px', borderRadius: 'var(--radius-sm)', border: '1px solid var(--border)',
    fontSize: '13px', fontFamily: 'var(--font-sans)', color: 'var(--text-primary)', background: 'var(--background)',
    appearance: 'none', cursor: 'pointer', outline: 'none',
    backgroundImage: `url("data:image/svg+xml,%3Csvg width='12' height='12' viewBox='0 0 12 12' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M3 5l3 3 3-3' fill='none' stroke='%23A1A1AA' stroke-width='1.5'/%3E%3C/svg%3E")`,
    backgroundRepeat: 'no-repeat', backgroundPosition: 'right 10px center',
  };
  const inputStyle = {
    width: '100%', padding: '8px 12px', borderRadius: 'var(--radius-sm)', border: '1px solid var(--border)',
    fontSize: '13px', fontFamily: 'var(--font-sans)', color: 'var(--text-primary)', background: 'var(--background)', outline: 'none',
  };
  const labelStyle = { fontSize: '12px', fontWeight: 600, color: 'var(--text-primary)', display: 'block', marginBottom: '6px' };

  const handleSubmit = () => {
    setSubmitting(true);
    setTimeout(() => { setSubmitting(false); onSubmit && onSubmit(); }, 2000);
  };

  return React.createElement('div', {
    style: {
      position: 'fixed', inset: 0, zIndex: 100, display: 'flex', alignItems: 'center', justifyContent: 'center',
      background: 'rgba(0,0,0,0.35)', backdropFilter: 'blur(2px)',
    },
    onClick: e => { if (e.target === e.currentTarget) onClose(); },
  },
    React.createElement('div', {
      style: {
        width: '90%', maxWidth: '860px', height: '85%', maxHeight: '680px', background: 'var(--background)',
        borderRadius: 'var(--radius-lg)', border: '1px solid var(--border)',
        boxShadow: '0 25px 50px rgba(0,0,0,0.2)', display: 'flex', flexDirection: 'column', overflow: 'hidden',
      }
    },

      /* ── Title bar (window chrome) ── */
      React.createElement('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '12px 20px', borderBottom: '1px solid var(--border)', flexShrink: 0 } },
        React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '10px' } },
          React.createElement('div', { style: { width: 24, height: 24, borderRadius: 'var(--radius-sm)', background: 'var(--gradient-brand)', display: 'flex', alignItems: 'center', justifyContent: 'center' } },
            React.createElement('iconify-icon', { icon: 'solar:ticket-linear', width: 13, style: { color: '#fff' } }),
          ),
          React.createElement('span', { style: { fontSize: '14px', fontWeight: 600 } }, 'Create New Ticket'),
        ),
        React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '2px' } },
          /* Minimize */
          React.createElement('button', { style: { width: 28, height: 28, border: 'none', background: 'none', cursor: 'pointer', borderRadius: '4px', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--text-secondary)', transition: 'background 100ms' },
            onMouseEnter: e => e.currentTarget.style.background = 'var(--surface)',
            onMouseLeave: e => e.currentTarget.style.background = 'none',
          }, React.createElement('iconify-icon', { icon: 'solar:minimize-linear', width: 14 })),
          /* Maximize */
          React.createElement('button', { style: { width: 28, height: 28, border: 'none', background: 'none', cursor: 'pointer', borderRadius: '4px', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--text-secondary)', transition: 'background 100ms' },
            onMouseEnter: e => e.currentTarget.style.background = 'var(--surface)',
            onMouseLeave: e => e.currentTarget.style.background = 'none',
          }, React.createElement('iconify-icon', { icon: 'solar:maximize-linear', width: 14 })),
          /* Close */
          React.createElement('button', { onClick: onClose, style: { width: 28, height: 28, border: 'none', background: 'none', cursor: 'pointer', borderRadius: '4px', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--text-secondary)', transition: 'all 100ms' },
            onMouseEnter: e => { e.currentTarget.style.background = '#FEF2F2'; e.currentTarget.style.color = 'var(--destructive)'; },
            onMouseLeave: e => { e.currentTarget.style.background = 'none'; e.currentTarget.style.color = 'var(--text-secondary)'; },
          }, React.createElement('iconify-icon', { icon: 'solar:close-circle-linear', width: 14 })),
        ),
      ),

      /* ── Body: two columns ── */
      React.createElement('div', { style: { flex: 1, display: 'flex', overflow: 'hidden' } },

        /* Left: Message area */
        React.createElement('div', { style: { flex: 1, display: 'flex', flexDirection: 'column', borderRight: '1px solid var(--border)' } },
          React.createElement('div', { style: { padding: '20px 24px', flex: 1, display: 'flex', flexDirection: 'column' } },
            React.createElement('h4', { style: { fontSize: '13px', fontWeight: 600, marginBottom: '14px' } }, 'Message'),
            /* From pill */
            React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '14px' } },
              React.createElement('span', { style: { fontSize: '12px', color: 'var(--text-secondary)' } }, 'From'),
              React.createElement(FromPill, { from: 'Fikri Studio Support' }),
            ),
            /* Rich text area */
            React.createElement('div', { style: { flex: 1, position: 'relative' } },
              React.createElement('textarea', {
                value: message, onChange: e => setMessage(e.target.value),
                placeholder: 'Comment or Type "/" For commands',
                style: {
                  width: '100%', height: '100%', border: 'none', resize: 'none', outline: 'none',
                  fontSize: '14px', fontFamily: 'var(--font-sans)', color: 'var(--text-primary)', lineHeight: '22px',
                  background: 'transparent',
                }
              }),
            ),
          ),
          /* Formatting toolbar */
          React.createElement('div', { style: { padding: '8px 24px', borderTop: '1px solid var(--border)', display: 'flex', flexDirection: 'column', gap: '6px', flexShrink: 0 } },
            /* Rich text icons row */
            React.createElement('div', { style: { display: 'flex', gap: '1px', flexWrap: 'wrap' } },
              ['solar:undo-left-linear', 'solar:undo-right-linear', 'solar:text-field-linear', 'solar:bold-linear', 'solar:text-italic-linear', 'solar:underline-linear', 'solar:text-bold-linear', 'solar:text-strikethrough-linear',
               'solar:align-left-linear', 'solar:align-horizontal-center-linear', 'solar:list-linear', 'solar:list-1-linear', 'solar:align-left-linear', 'solar:align-right-linear',
              ].map((ic, i) =>
                React.createElement('button', { key: ic + i, style: { width: 28, height: 28, display: 'flex', alignItems: 'center', justifyContent: 'center', border: 'none', background: 'none', cursor: 'pointer', borderRadius: '3px', color: 'var(--text-muted)', transition: 'all 100ms' },
                  onMouseEnter: e => { e.currentTarget.style.background = 'var(--surface)'; },
                  onMouseLeave: e => { e.currentTarget.style.background = 'none'; },
                }, React.createElement('iconify-icon', { icon: ic, width: 14 }))
              ),
            ),
            /* Bottom tools row */
            React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '2px', paddingBottom: '4px' } },
              ['solar:text-bold-linear', 'solar:emoji-funny-circle-linear', 'solar:paperclip-linear', 'solar:microphone-linear', 'solar:link-linear', 'solar:letter-linear', 'solar:gallery-add-linear'].map((ic, i) =>
                React.createElement('button', { key: ic + i, style: { width: 28, height: 28, display: 'flex', alignItems: 'center', justifyContent: 'center', border: 'none', background: 'none', cursor: 'pointer', borderRadius: '3px', color: 'var(--text-muted)' } },
                  React.createElement('iconify-icon', { icon: ic, width: 15 }))
              ),
              React.createElement('div', { style: { width: 1, height: 16, background: 'var(--border)', margin: '0 6px' } }),
              React.createElement('button', { style: { display: 'flex', alignItems: 'center', gap: '4px', padding: '4px 10px', border: 'none', background: 'none', cursor: 'pointer', fontSize: '12px', fontWeight: 500, color: 'var(--text-muted)', fontFamily: 'var(--font-sans)' } },
                'Macros', React.createElement('iconify-icon', { icon: 'solar:alt-arrow-down-linear', width: 12 })),
            ),
          ),
        ),

        /* Right: Metadata fields */
        React.createElement('div', { style: { width: 300, overflow: 'auto', padding: '20px', display: 'flex', flexDirection: 'column', gap: '16px' }, className: 'custom-scrollbar' },
          /* Ticket Name */
          React.createElement('div', null,
            React.createElement('label', { style: labelStyle }, 'Ticket Name'),
            React.createElement('input', { value: ticketName, onChange: e => setTicketName(e.target.value), placeholder: 'My Suggestion for this product', style: inputStyle }),
          ),
          /* Priority */
          React.createElement('div', null,
            React.createElement('label', { style: labelStyle }, 'Priority'),
            React.createElement(PrioritySegmented, { value: priority, onChange: setPriority }),
          ),
          /* Ticket Type */
          React.createElement('div', null,
            React.createElement('label', { style: labelStyle }, 'Ticket Type'),
            React.createElement('select', { value: ticketType, onChange: e => setTicketType(e.target.value), style: { ...selectStyle, color: ticketType ? 'var(--text-primary)' : 'var(--text-secondary)' } },
              React.createElement('option', { value: '' }, 'Ticket type'),
              ['Incident', 'Problem', 'Question', 'Suggestion'].map(o => React.createElement('option', { key: o, value: o }, o)),
            ),
          ),
          /* Requester + Assignee side by side */
          React.createElement('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' } },
            React.createElement('div', null,
              React.createElement('label', { style: labelStyle }, 'Requester'),
              React.createElement('select', { value: requester, onChange: e => setRequester(e.target.value), style: { ...selectStyle, color: requester ? 'var(--text-primary)' : 'var(--text-secondary)' } },
                React.createElement('option', { value: '' }, 'Select requester'),
                ['Martin Ødegaard', 'Santi Carloza', 'Fast Response'].map(o => React.createElement('option', { key: o, value: o }, o)),
              ),
            ),
            React.createElement('div', null,
              React.createElement('label', { style: labelStyle }, 'Assignee'),
              React.createElement('select', { value: assignee, onChange: e => setAssignee(e.target.value), style: { ...selectStyle, color: assignee ? 'var(--text-primary)' : 'var(--text-secondary)' } },
                React.createElement('option', { value: '' }, 'Select assignee'),
                ['Fikri Studio', 'John Smith', 'Sarah Patel'].map(o => React.createElement('option', { key: o, value: o }, o)),
              ),
            ),
          ),
          /* Tags */
          React.createElement('div', null,
            React.createElement('label', { style: labelStyle }, 'Tags'),
            React.createElement('input', { value: tags, onChange: e => setTags(e.target.value), placeholder: 'Add tags', style: { ...inputStyle, minHeight: '44px' } }),
          ),
          /* Followers */
          React.createElement('div', null,
            React.createElement('label', { style: labelStyle }, 'Followers'),
            React.createElement('input', { value: followers, onChange: e => setFollowers(e.target.value), placeholder: 'Add followers', style: { ...inputStyle, minHeight: '44px' } }),
          ),
        ),

        /* Right edge icon strip */
        React.createElement('div', { style: { width: 40, borderLeft: '1px solid var(--border)', display: 'flex', flexDirection: 'column', alignItems: 'center', paddingTop: '16px', gap: '4px', flexShrink: 0 } },
          ['solar:clipboard-list-linear', 'solar:users-group-rounded-linear', 'solar:chat-round-dots-linear', 'solar:phone-linear', 'solar:letter-linear'].map((ic, i) =>
            React.createElement('button', {
              key: ic,
              style: {
                width: 32, height: 32, borderRadius: 'var(--radius-sm)', border: 'none',
                background: i === 0 ? 'var(--primary)' : 'none',
                color: i === 0 ? '#fff' : 'var(--text-secondary)',
                display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer', transition: 'all 150ms',
              },
              onMouseEnter: e => { if (i !== 0) { e.currentTarget.style.background = 'var(--surface)'; } },
              onMouseLeave: e => { if (i !== 0) { e.currentTarget.style.background = 'none'; } },
            }, React.createElement('iconify-icon', { icon: ic, width: 16 }))
          ),
        ),
      ),

      /* ── Footer ── */
      React.createElement('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: '12px', padding: '12px 20px', borderTop: '1px solid var(--border)', flexShrink: 0 } },
        React.createElement('button', { onClick: onClose, style: { background: 'none', border: 'none', cursor: 'pointer', fontSize: '13px', fontWeight: 500, color: 'var(--text-muted)', fontFamily: 'var(--font-sans)' } }, 'Cancel'),
        React.createElement(SplitButton, { label: 'Submit as New', onClick: handleSubmit }),
      ),

      /* Submitting overlay */
      submitting && React.createElement('div', { style: {
        position: 'absolute', inset: 0, background: 'rgba(255,255,255,0.85)', display: 'flex', flexDirection: 'column',
        alignItems: 'center', justifyContent: 'center', zIndex: 10, borderRadius: 'var(--radius-lg)',
      } },
        React.createElement('div', { style: { width: 64, height: 64, borderRadius: '16px', background: 'var(--surface)', display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: '16px' } },
          React.createElement('iconify-icon', { icon: 'solar:ticket-linear', width: 28, style: { color: 'var(--text-muted)' } }),
        ),
        React.createElement('h3', { style: { fontSize: '18px', fontWeight: 600, marginBottom: '4px' } }, 'Submitting Ticket'),
        React.createElement('p', { style: { fontSize: '13px', color: 'var(--text-secondary)' } }, 'Preparing New Ticket'),
        React.createElement('button', { onClick: () => setSubmitting(false), style: { marginTop: '16px', background: 'none', border: 'none', cursor: 'pointer', fontSize: '13px', fontWeight: 500, color: 'var(--text-muted)', fontFamily: 'var(--font-sans)' } }, 'Cancel'),
      ),
    ),
  );
}

Object.assign(window, { NewTicketModal });
