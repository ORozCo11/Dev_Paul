import LoadingOverlay from '../components/LoadingOverlay';
import { useContext, useState } from 'react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import api from '../api/axios';
import Icon from '../components/Icon';
import Aurora from '../components/Aurora';
import AuthHeader from '../components/AuthHeader';
import AuthFooter from '../components/AuthFooter';
import { AuthContext } from '../context/AuthContextObject';

// Matches RegistrationSetting::generateCode() on the backend.
const STAFF_CODE_LENGTH = 8;

const roleRoutes = {
  Admin: '/admin',
  Custodian: '/custodian',
  'Maintenance Personnel': '/maintenance',
  'Super Admin': '/superadmin',
  Impersonator: '/impersonation',
};

function Login() {
  const navigate = useNavigate();
  const location = useLocation();
  const { login, token, user } = useContext(AuthContext);
  const [credentials, setCredentials] = useState({
    email: '',
    password: '',
  });
  const [rememberMe, setRememberMe] = useState(true);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  const [activation, setActivation] = useState(null);

  if (token && user) {
    return <Navigate to={roleRoutes[user.role] ?? '/unauthorized'} replace />;
  }

  const finishLogin = ({ user: authenticatedUser, access_token: accessToken }) => {
    login(authenticatedUser, accessToken, rememberMe);
    // Return to wherever ProtectedRoute bounced them from, if anywhere —
    // only unauthenticated redirects land here with `from` set, so this
    // can never send someone to a page their role doesn't allow (a wrong
    // role goes to /unauthorized instead, not back through /login).
    const destination = authenticatedUser.role === 'Impersonator' ? '/impersonation' : location.state?.from
      ? `${location.state.from.pathname}${location.state.from.search ?? ''}`
      : (roleRoutes[authenticatedUser.role] ?? '/unauthorized');
    navigate(destination, { replace: true });
  };

  const activate = async (code) => {
    if (activation.busy) return;
    if (!code) {
      setActivation((current) => ({ ...current, error: 'Paste the staff registration code from your email.' }));
      return;
    }
    setActivation((current) => ({ ...current, busy: true, error: '' }));
    try {
      const response = await api.post('/activate-account', { ...credentials, staff_code: code });
      finishLogin(response.data);
    } catch (requestError) {
      setActivation((current) => ({
        ...current,
        busy: false,
        expired: requestError.response?.status === 410,
        error: requestError.response?.data?.message ?? 'Could not activate your account. Please try again.',
      }));
    }
  };

  // Pasting a complete code activates right away; Activate covers typing it.
  const handleCodeChange = (event) => {
    const code = event.target.value.replace(/\s+/g, '').toUpperCase();
    setActivation((current) => ({ ...current, code, error: '' }));
    if (code.length === STAFF_CODE_LENGTH) activate(code);
  };

  const handleChange = (event) => {
    const { name, value } = event.target;
    setCredentials((current) => ({
      ...current,
      [name]: value,
    }));
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    if (submitting) return;
    setError('');

    if (!credentials.email || !credentials.password) {
      setError('Email and password are required.');
      return;
    }

    setSubmitting(true);

    try {
      const response = await api.post('/login', credentials);
      finishLogin(response.data);
    } catch (requestError) {
      // A first Administrator signs in, then activates with the emailed staff code.
      if (requestError.response?.data?.awaiting_staff_code) {
        setActivation({ sent: requestError.response.data.staff_code_sent, code: '', error: '', expired: false, busy: false });
        return;
      }
      setError(
        requestError.response?.data?.message ??
          'Unable to sign in. Please check your connection and try again.',
      );
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="auth-page-shell">
      <AuthHeader />

      <main className="auth-page">
      {submitting && <LoadingOverlay />}
      <Aurora colorStops={['#0b1220', '#1e3a5f', '#0f172a']} amplitude={0.6} blend={0.55} />
      <section className="auth-card" aria-labelledby="login-title">
        <div className="auth-logo-header">
          <h1 id="login-title" className="auth-login-heading">Login</h1>
        </div>

        <form className="auth-form" onSubmit={handleSubmit} noValidate>
          <label className="auth-field">
            <span>Email Address</span>
            <div className="auth-input-wrapper auth-input-plain">
              <input
                autoComplete="email"
                name="email"
                onChange={handleChange}
                placeholder="Email address"
                required
                type="email"
                value={credentials.email}
              />
            </div>
          </label>

          <label className="auth-field">
            <span>Password</span>
            <div className="auth-input-wrapper auth-input-plain">
              <input
                autoComplete="current-password"
                name="password"
                onChange={handleChange}
                placeholder="Password"
                required
                type={showPassword ? 'text' : 'password'}
                value={credentials.password}
              />
              <button
                type="button"
                className="password-toggle-btn"
                onClick={() => setShowPassword((v) => !v)}
                aria-label={showPassword ? 'Hide password' : 'Show password'}
                title={showPassword ? 'Hide password' : 'Show password'}
              >
                <Icon name={showPassword ? 'eyeOff' : 'eye'} size={18} />
              </button>
            </div>
          </label>

          <label className="auth-remember">
            <input
              type="checkbox"
              checked={rememberMe}
              onChange={(e) => setRememberMe(e.target.checked)}
            />
            Remember Me
          </label>

          {error ? <p className="notice error" role="alert">{error}</p> : null}

          <button className="primary-button auth-submit-btn" disabled={submitting} type="submit">
            {submitting ? (
              <span className="btn-loading">
                <span className="btn-spinner" aria-hidden="true" />
                Signing in…
              </span>
            ) : 'Sign in'}
          </button>
        </form>

        <p className="auth-legal">
          <Link to="/privacy">Privacy</Link>
          {' · '}
          <Link to="/terms">Terms</Link>
        </p>
      </section>
      </main>

      {activation && (
        <div className="confirm-overlay">
          <form className="confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="activation-title" onSubmit={(event) => { event.preventDefault(); activate(activation.code); }} noValidate>
            <div className="confirm-dialog-icon" aria-hidden="true"><Icon name="key" size={22} /></div>
            <div className="confirm-dialog-copy">
              <p className="eyebrow">Not verified yet</p>
              <h3 id="activation-title">Your profile is under review</h3>
              <p>{activation.sent
                ? 'You can continue once your account is verified. Paste the Staff Registration Code the Super Admin emailed you below. Check your Spam or Promotions folder if you cannot find it.'
                : 'You can continue once your account is verified. The Super Admin is reviewing your registration and will email you a Staff Registration Code. Once it arrives, sign in again and paste it here.'}</p>
            </div>
            {!activation.sent ? null : activation.expired ? (
              <p className="notice error" role="alert">{activation.error} <Link to="/register">Register again</Link></p>
            ) : (
              <>
                <label className="auth-field">
                  <span>Staff Registration Code</span>
                  <div className="auth-input-wrapper auth-input-plain">
                    <input
                      autoComplete="one-time-code"
                      autoFocus
                      maxLength={STAFF_CODE_LENGTH + 4}
                      onChange={handleCodeChange}
                      placeholder="Paste your staff registration code"
                      type="text"
                      value={activation.code}
                    />
                  </div>
                </label>
                {activation.error ? <p className="notice error" role="alert">{activation.error}</p> : null}
              </>
            )}
            <div className="confirm-dialog-actions">
              <button className="ghost-button" disabled={activation.busy} onClick={() => setActivation(null)} type="button">Close</button>
              {activation.sent && !activation.expired && <button className="primary-button" disabled={activation.busy} type="submit">{activation.busy ? 'Activating…' : 'Activate'}</button>}
            </div>
          </form>
        </div>
      )}

      <AuthFooter />
    </div>
  );
}

export default Login;
