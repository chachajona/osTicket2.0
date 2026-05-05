/* osTicket 2.0 — Forgot Password Screen */

function ForgotPasswordScreen({ onBack }) {
  const [email, setEmail] = React.useState('');
  const [sent, setSent] = React.useState(false);
  const [loading, setLoading] = React.useState(false);

  const handleSubmit = () => {
    setLoading(true);
    setTimeout(() => { setLoading(false); setSent(true); }, 1000);
  };

  return React.createElement(AuthLayout, {
    title: 'Reset your password.',
    subtitle: 'Enter your registered email address and we\'ll send a secure link to reset your credentials.',
    tag: 'Recovery · Staff',
    eyebrowAccent: 'pink',
    sectionIndex: '02',
    footer: React.createElement('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
      React.createElement(LinkBtn, { onClick: onBack }, 'Back to login'),
      React.createElement(Caption, null, 'Session · Restricted'),
    ),
  },
    sent
      ? React.createElement(Alert, { variant: 'success' }, 'Password reset link sent. Check your inbox.')
      : React.createElement(React.Fragment, null,
          React.createElement('div', { style: { display: 'flex', flexDirection: 'column', gap: '24px' } },
            React.createElement(AuthInput, { label: 'Email Address', id: 'email', type: 'email', value: email, onChange: e => setEmail(e.target.value), autoFocus: true, placeholder: 'staff@company.com' }),
          ),
          React.createElement('div', { style: { marginTop: '32px' } },
            React.createElement(AuthSubmit, { disabled: loading, onClick: handleSubmit }, loading ? 'Sending…' : 'Send Reset Link →'),
          ),
        ),
  );
}

Object.assign(window, { ForgotPasswordScreen });
