/* osTicket 2.0 — Shared UI Components
   Aligned to DESIGN.md: Inter-only, orange primary, cream buttons, Solar icons */

/* ── Icon helper (Solar via Iconify CDN) ─────────────────── */
function SolarIcon({ name, size = 20, color = 'currentColor' }) {
  const src = `https://api.iconify.design/solar/${name}.svg?color=${encodeURIComponent(color)}&width=${size}&height=${size}`;
  return React.createElement('img', { src, width: size, height: size, alt: '', style: { display: 'block', flexShrink: 0 } });
}

/* ── Button — Primary: cream bg, uppercase tracked ───────── */
function Button({ children, variant = 'primary', size = 'default', style, disabled, onClick, icon }) {
  const base = {
    display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '6px',
    fontFamily: "'Inter', sans-serif", fontSize: '12px', fontWeight: 500,
    letterSpacing: '1.2px', textTransform: 'uppercase', lineHeight: '16px',
    border: 'none', cursor: 'pointer', whiteSpace: 'nowrap',
    transition: 'all 150ms ease', outline: 'none',
    ...(disabled ? { opacity: 0.5, cursor: 'not-allowed' } : {}),
  };
  const variants = {
    primary: { background: '#F4F2EB', color: '#27272A', borderRadius: '3px', padding: '8px 16px' },
    secondary: { background: 'none', color: '#18181B', borderRadius: 0, padding: '0 0 2px 0', borderBottom: '1px solid #18181B' },
    ghost: { background: 'none', color: '#3F3F46', borderRadius: 0, padding: 0 },
    destructive: { background: '#FEF2F2', color: '#DC2626', borderRadius: '3px', padding: '8px 16px' },
    outline: { background: '#fff', color: '#18181B', borderRadius: '3px', padding: '8px 16px', border: '1px solid #E2E0D8' },
  };
  const sizes = {
    sm: { padding: '6px 12px', fontSize: '11px' },
    default: {},
    lg: { padding: '12px 20px' },
  };
  const s = { ...base, ...variants[variant], ...sizes[size], ...style };
  return React.createElement('button', { style: s, disabled, onClick },
    icon && React.createElement(SolarIcon, { name: icon, size: 14, color: s.color }),
    children
  );
}

/* ── Auth Submit — dark pill CTA ─────────────────────────── */
function AuthSubmit({ children, disabled, onClick }) {
  return React.createElement('button', {
    disabled, onClick,
    style: {
      display: 'inline-flex', width: '100%', alignItems: 'center', justifyContent: 'center', gap: '8px',
      padding: '12px 20px', borderRadius: '3px', background: '#18181b', color: '#fff', border: 'none',
      fontFamily: "'Inter', sans-serif", fontSize: '12px', fontWeight: 500, letterSpacing: '1.2px', textTransform: 'uppercase', lineHeight: '16px',
      cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.5 : 1,
      boxShadow: 'rgba(24,24,27,0.1) 0 2px 4px -2px, rgba(24,24,27,0.05) 0 1px 2px 0',
      transition: 'background 200ms ease, transform 150ms ease',
    }
  }, children);
}

/* ── Input ────────────────────────────────────────────────── */
function AuthInput({ label, id, type = 'text', value, onChange, error, disabled, autoFocus, placeholder }) {
  const [focused, setFocused] = React.useState(false);
  return React.createElement('div', { style: { display: 'flex', flexDirection: 'column', gap: '6px', width: '100%' } },
    label && React.createElement('label', {
      htmlFor: id,
      style: { fontFamily: "'Inter', sans-serif", fontSize: '10px', fontWeight: 500, letterSpacing: '0.1em', textTransform: 'uppercase', lineHeight: '15px', color: '#A1A1AA' }
    }, label),
    React.createElement('input', {
      id, type, value, onChange, disabled, autoFocus, placeholder,
      onFocus: () => setFocused(true), onBlur: () => setFocused(false),
      style: {
        height: '44px', borderRadius: '4px', border: `1px solid ${error ? '#DC2626' : focused ? '#18181b' : '#E2E0D8'}`,
        background: '#fff', padding: '0 14px', fontSize: '14px', fontFamily: "'Inter', sans-serif", color: '#18181b', outline: 'none',
        boxShadow: error ? '0 0 0 3px rgba(220,38,38,0.12)' : focused ? '0 0 0 3px rgba(249,115,22,0.18)' : 'none',
        transition: 'border-color 150ms ease, box-shadow 150ms ease',
        ...(disabled ? { opacity: 0.5 } : {}),
      }
    }),
    error && React.createElement('div', { style: { fontSize: '12px', color: '#DC2626', fontFamily: "'Inter', sans-serif" } }, error),
  );
}

/* ── Checkbox ─────────────────────────────────────────────── */
function AuthCheckbox({ checked, onChange, label }) {
  return React.createElement('label', { style: { display: 'flex', alignItems: 'center', gap: '10px', cursor: 'pointer' } },
    React.createElement('div', {
      onClick: () => onChange(!checked),
      style: {
        width: '18px', height: '18px', borderRadius: '3px',
        border: `1px solid ${checked ? '#18181b' : '#E2E0D8'}`,
        background: checked ? '#18181b' : '#fff',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        transition: 'all 150ms ease', cursor: 'pointer',
      }
    }, checked && React.createElement('svg', { width: 12, height: 12, viewBox: '0 0 12 12', fill: 'none', stroke: '#fff', strokeWidth: 2.5, strokeLinecap: 'round', strokeLinejoin: 'round' },
      React.createElement('path', { d: 'M2.5 6.5 L5 9 L9.5 3.5' })
    )),
    React.createElement('span', { style: { fontSize: '14px', fontFamily: "'Inter', sans-serif", color: '#18181b' } }, label),
  );
}

/* ── Alert ────────────────────────────────────────────────── */
function Alert({ variant = 'default', children }) {
  const colors = {
    default: { bg: '#fff', border: '#E2E0D8', color: '#18181b' },
    destructive: { bg: '#fef2f2', border: '#fecaca', color: '#dc2626' },
    success: { bg: '#f0fdf4', border: '#bbf7d0', color: '#16a34a' },
    warning: { bg: '#fffbeb', border: '#fcd34d', color: '#92400e' },
  }[variant];
  return React.createElement('div', {
    style: { borderRadius: '8px', border: `1px solid ${colors.border}`, background: colors.bg, color: colors.color, padding: '10px 16px', fontSize: '14px', fontFamily: "'Inter', sans-serif", lineHeight: '22.75px' }
  }, children);
}

/* ── Eyebrow ──────────────────────────────────────────────── */
function Eyebrow({ accent = 'orange', children }) {
  const accentColors = { orange: '#F97316', pink: '#EC4899', indigo: '#6366F1', gradient: 'linear-gradient(135deg,#FB923C,#EC4899,#6366F1)' };
  return React.createElement('span', {
    style: { display: 'inline-flex', alignItems: 'center', gap: '8px', fontFamily: "'Inter', sans-serif", fontSize: '10px', fontWeight: 500, lineHeight: '15px', letterSpacing: '0.1em', textTransform: 'uppercase', color: '#18181b' }
  },
    React.createElement('span', { style: { width: '6px', height: '6px', borderRadius: '9999px', background: accentColors[accent], flexShrink: 0 } }),
    children
  );
}

/* ── Caption ──────────────────────────────────────────────── */
function Caption({ children, style }) {
  return React.createElement('span', {
    style: { fontFamily: "'Inter', sans-serif", fontSize: '10px', fontWeight: 500, lineHeight: '15px', letterSpacing: '0.1em', textTransform: 'uppercase', color: '#A1A1AA', ...style }
  }, children);
}

/* ── Link Button — underline secondary ────────────────────── */
function LinkBtn({ children, onClick }) {
  const [hovered, setHovered] = React.useState(false);
  return React.createElement('button', {
    onClick, onMouseEnter: () => setHovered(true), onMouseLeave: () => setHovered(false),
    style: {
      display: 'inline-flex', alignItems: 'center', gap: '6px',
      fontFamily: "'Inter', sans-serif", fontSize: '12px', fontWeight: 500, letterSpacing: '1.2px', textTransform: 'uppercase', lineHeight: '16px',
      color: hovered ? '#EC4899' : '#18181B', background: 'none', cursor: 'pointer',
      border: 'none', borderBottom: `1px solid ${hovered ? '#EC4899' : '#18181B'}`,
      padding: '0 0 2px 0', transition: 'color 150ms ease, border-color 150ms ease',
    }
  }, children);
}

/* ── Gradient Shell ───────────────────────────────────────── */
function GradientShell({ children, style }) {
  return React.createElement('div', {
    style: {
      padding: '1px', borderRadius: '8px',
      background: 'linear-gradient(to right bottom, rgb(251,146,60), rgb(236,72,153), rgb(147,51,234))',
      boxShadow: 'rgba(236,72,153,0.10) 0 10px 15px -3px, rgba(236,72,153,0.10) 0 4px 6px -4px',
      ...style,
    }
  },
    React.createElement('div', { style: { borderRadius: '7px', background: '#F4F2EB' } }, children)
  );
}

/* ── Tabs (pill variant) ──────────────────────────────────── */
function TabsPill({ tabs, active, onChange }) {
  return React.createElement('div', {
    style: { display: 'inline-flex', background: '#F4F2EB', borderRadius: '32px', padding: '3px' }
  }, tabs.map((t, i) =>
    React.createElement('button', {
      key: t, onClick: () => onChange(i),
      style: {
        padding: '6px 14px', borderRadius: '32px', fontFamily: "'Inter', sans-serif", fontSize: '12px', fontWeight: 500,
        letterSpacing: '1.2px', textTransform: 'uppercase',
        color: active === i ? '#18181b' : '#A1A1AA', background: active === i ? '#fff' : 'none',
        border: 'none', cursor: 'pointer', transition: 'all 150ms ease',
      }
    }, t)
  ));
}

/* ── Nav Item (sidebar) ───────────────────────────────────── */
function NavItem({ icon, label, active, onClick }) {
  const [hovered, setHovered] = React.useState(false);
  return React.createElement('button', {
    onClick,
    onMouseEnter: () => setHovered(true), onMouseLeave: () => setHovered(false),
    style: {
      display: 'flex', alignItems: 'center', gap: '10px', padding: '8px 12px', borderRadius: '8px',
      fontSize: '14px', fontWeight: active ? 500 : 400, lineHeight: '22.75px',
      color: active ? '#18181B' : '#71717A', background: active ? '#F4F2EB' : hovered ? '#F4F2EB80' : 'transparent',
      border: 'none', cursor: 'pointer', width: '100%', textAlign: 'left',
      fontFamily: "'Inter', sans-serif", transition: 'all 150ms ease',
    }
  },
    React.createElement(SolarIcon, { name: icon, size: 20, color: active ? '#18181B' : '#D4D4D8' }),
    label,
  );
}

Object.assign(window, {
  SolarIcon, Button, AuthSubmit, AuthInput, AuthCheckbox, Alert, Eyebrow, Caption, LinkBtn, GradientShell, TabsPill, NavItem,
});
