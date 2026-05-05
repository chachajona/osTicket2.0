/* osTicket 2.0 — Auth Layout
   Aligned to DESIGN.md: Inter-only, orange primary, cream surface, gradient shell */

function ArrowUpRight({ size = 12, color = 'currentColor' }) {
  return React.createElement('svg', { width: size, height: size, viewBox: '0 0 16 16', fill: 'none', stroke: color, strokeWidth: 1.25, strokeLinecap: 'round', strokeLinejoin: 'round' },
    React.createElement('path', { d: 'M5 11 L11 5' }),
    React.createElement('path', { d: 'M6.5 5 L11 5 L11 9.5' }),
  );
}

function AuthLayout({ title, subtitle, tag, eyebrowAccent = 'orange', sectionIndex = '01', children, footer }) {
  const year = new Date().getFullYear();

  return React.createElement('div', {
    style: {
      position: 'relative', display: 'flex', flexDirection: 'column', minHeight: '100vh',
      background: '#FFFFFF', color: '#18181B', fontFamily: "'Inter', sans-serif",
      WebkitFontSmoothing: 'antialiased', fontFeatureSettings: '"ss01", "cv11"',
    }
  },
    // Mesh background
    React.createElement('div', {
      style: {
        position: 'absolute', inset: 0, pointerEvents: 'none', zIndex: 0,
        background: 'radial-gradient(55% 40% at 92% 8%, rgba(249,115,22,0.20) 0%, transparent 70%), radial-gradient(50% 35% at 8% 100%, rgba(99,102,241,0.14) 0%, transparent 70%), radial-gradient(40% 30% at 60% 60%, rgba(236,72,153,0.10) 0%, transparent 70%)',
      }
    }),
    // Grain
    React.createElement('div', {
      style: {
        position: 'absolute', inset: 0, pointerEvents: 'none', zIndex: 0, opacity: 0.6, mixBlendMode: 'multiply',
        backgroundImage: 'radial-gradient(rgba(24,24,27,0.05) 0.5px, transparent 0.5px)', backgroundSize: '3px 3px',
      }
    }),

    // Header
    React.createElement('header', {
      style: { position: 'relative', zIndex: 10, display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', alignItems: 'center', gap: '16px', maxWidth: '1240px', margin: '0 auto', width: '100%', padding: '20px 40px' }
    },
      React.createElement(Caption, { style: { color: '#18181B' } }, 'osTicket', React.createElement('span', { style: { color: '#A1A1AA' } }, ' · Staff Console')),
      React.createElement(Caption, { style: { justifySelf: 'center' } }, 'Secure Session · Encrypted'),
      React.createElement('div', { style: { justifySelf: 'end' } },
        React.createElement(LinkBtn, null, 'Need help', React.createElement(ArrowUpRight, { size: 12 }))
      ),
    ),
    React.createElement('div', { style: { position: 'relative', zIndex: 10, maxWidth: '1240px', margin: '0 auto', width: '100%', padding: '0 40px' } },
      React.createElement('div', { style: { height: '1px', width: '100%', background: '#E2E0D8' } })
    ),

    // Main
    React.createElement('main', {
      style: { position: 'relative', zIndex: 10, display: 'grid', gridTemplateColumns: '3fr 9fr', gap: '40px', maxWidth: '1240px', margin: '0 auto', width: '100%', padding: '80px 40px', flex: 1 }
    },
      React.createElement('aside', { style: { paddingTop: '16px' } },
        React.createElement('div', { style: { display: 'flex', alignItems: 'flex-start', gap: '12px' } },
          React.createElement(Caption, null, sectionIndex),
          React.createElement('div', { style: { marginTop: '6px', height: '1px', width: '24px', background: '#18181B' } }),
          React.createElement(Caption, null, tag || 'Authentication'),
        ),
      ),
      React.createElement('section', null,
        tag && React.createElement(Eyebrow, { accent: eyebrowAccent }, tag),
        React.createElement('h1', {
          style: { marginTop: '20px', fontFamily: "'Inter', sans-serif", fontWeight: 500, fontSize: '72px', lineHeight: '72px', letterSpacing: '-0.05em', color: '#18181B' }
        }, title),
        subtitle && React.createElement('p', {
          style: { marginTop: '24px', maxWidth: '560px', fontFamily: "'Inter', sans-serif", fontSize: '14px', lineHeight: '22.75px', color: '#A1A1AA' }
        }, subtitle),
        React.createElement('div', { style: { marginTop: '32px', maxWidth: '560px' } },
          React.createElement(GradientShell, null,
            React.createElement('div', { style: { padding: '40px' } }, children)
          ),
        ),
        footer && React.createElement('div', { style: { marginTop: '24px', maxWidth: '560px' } }, footer),
      ),
    ),

    React.createElement('div', { style: { position: 'relative', zIndex: 10, maxWidth: '1240px', margin: '0 auto', width: '100%', padding: '0 40px' } },
      React.createElement('div', { style: { height: '1px', width: '100%', background: '#E2E0D8' } })
    ),
    React.createElement('footer', {
      style: { position: 'relative', zIndex: 10, display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', alignItems: 'center', gap: '16px', maxWidth: '1240px', margin: '0 auto', width: '100%', padding: '20px 40px' }
    },
      React.createElement(Caption, null, `© ${year} · osticket.com`),
      React.createElement(Caption, { style: { justifySelf: 'center' } }, 'v2.0 · Support Suite'),
      React.createElement(Caption, { style: { justifySelf: 'end' } }, 'Rate-limited'),
    ),
  );
}

Object.assign(window, { AuthLayout, ArrowUpRight });
