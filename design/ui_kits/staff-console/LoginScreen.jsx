/* osTicket 2.0 — Login Screen */

function LoginScreen({ onForgot, onLogin }) {
  const [username, setUsername] = React.useState('');
  const [password, setPassword] = React.useState('');
  const [remember, setRemember] = React.useState(false);
  const [loading, setLoading] = React.useState(false);

  const handleSubmit = () => {
    setLoading(true);
    setTimeout(() => { setLoading(false); onLogin && onLogin(); }, 1200);
  };

  return React.createElement(AuthLayout, {
    title: 'Sign in to the console.',
    subtitle: 'Access the osTicket staff support panel with your credentials. Two-factor authentication may be required.',
    tag: 'Login · Staff',
    eyebrowAccent: 'orange',
    sectionIndex: '01',
    footer: React.createElement('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
      React.createElement(LinkBtn, { onClick: onForgot }, 'Forgot password'),
      React.createElement(Caption, null, 'Session · Restricted'),
    ),
  },
    React.createElement('div', { style: { display: 'flex', flexDirection: 'column', gap: '24px' } },
      React.createElement(AuthInput, { label: 'Username', id: 'username', value: username, onChange: e => setUsername(e.target.value), autoFocus: true }),
      React.createElement('div', null,
        React.createElement('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' } },
          React.createElement('label', { style: { fontFamily: "'Inter', sans-serif", fontSize: '10px', fontWeight: 500, letterSpacing: '0.1em', textTransform: 'uppercase', color: '#52525b' } }, 'Password'),
          React.createElement('button', {
            onClick: onForgot,
            style: { fontFamily: "'Inter', sans-serif", fontSize: '10px', fontWeight: 500, letterSpacing: '0.1em', textTransform: 'uppercase', color: '#52525b', background: 'none', border: 'none', cursor: 'pointer', transition: 'color 150ms ease' }
          }, 'Forgot →'),
        ),
        React.createElement(AuthInput, { id: 'password', type: 'password', value: password, onChange: e => setPassword(e.target.value) }),
      ),
      React.createElement(AuthCheckbox, { checked: remember, onChange: setRemember, label: 'Keep me signed in on this device' }),
    ),
    React.createElement('div', { style: { marginTop: '32px' } },
      React.createElement(AuthSubmit, { disabled: loading, onClick: handleSubmit }, loading ? 'Authenticating…' : 'Enter Console →'),
    ),
  );
}

Object.assign(window, { LoginScreen });
