import LoadingOverlay from '../components/LoadingOverlay';
import { useContext, useRef, useState } from 'react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import api from '../api/axios';
import Icon from '../components/Icon';
import AuthHeader from '../components/AuthHeader';
import AuthFooter from '../components/AuthFooter';
import { AuthContext } from '../context/AuthContextObject';
import peopleSearch from '../assets/people-search.svg';
import './Login.css';

// Matches RegistrationSetting::generateCode() on the backend: 8 uppercase letters or digits.
const STAFF_CODE_LENGTH = 8;
const cleanCode = (text) => text.toUpperCase().replace(/[^A-Z0-9]/g, '');

// One box per character, so the applicant can see every character they entered.
function StaffCodeBoxes({ value, onChange, onComplete, disabled }) {
  const boxes = useRef([]);
  // Empty boxes are kept as spaces, so clearing one box never shifts the others.
  const chars = Array.from({ length: STAFF_CODE_LENGTH }, (_, i) => (value[i] ?? ' ').trim());
  const join = (list) => list.map((char) => char || ' ').join('').trimEnd();
  const focusBox = (index) => boxes.current[Math.max(0, Math.min(STAFF_CODE_LENGTH - 1, index))]?.focus();
  const fill = (start, text) => {
    const typed = cleanCode(text);
    if (!typed) return;
    const next = [...chars];
    typed.slice(0, STAFF_CODE_LENGTH - start).split('').forEach((char, i) => { next[start + i] = char; });
    const code = join(next);
    onChange(code);
    focusBox(start + typed.length);
    // A full code pasted in one go is submitted right away.
    if (typed.length > 1 && cleanCode(code).length === STAFF_CODE_LENGTH) onComplete(code);
  };
  const handleKeyDown = (index) => (event) => {
    if (event.key === 'Backspace') {
      event.preventDefault();
      const next = [...chars];
      const target = chars[index] ? index : index - 1;
      if (target < 0) return;
      next[target] = '';
      onChange(join(next));
      focusBox(target);
    } else if (event.key === 'ArrowLeft') { event.preventDefault(); focusBox(index - 1); }
    else if (event.key === 'ArrowRight') { event.preventDefault(); focusBox(index + 1); }
  };
  return <div className="staff-code-boxes" role="group" aria-label={`Staff registration code, ${STAFF_CODE_LENGTH} characters`}>
    {chars.map((char, index) => <input
      key={index}
      ref={(element) => { boxes.current[index] = element; }}
      className={char ? 'is-filled' : ''}
      aria-label={`Character ${index + 1} of ${STAFF_CODE_LENGTH}`}
      autoComplete={index === 0 ? 'one-time-code' : 'off'}
      autoCapitalize="characters"
      autoFocus={index === 0}
      disabled={disabled}
      inputMode="text"
      maxLength={1}
      spellCheck={false}
      type="text"
      value={char}
      onChange={(event) => fill(index, event.target.value.slice(-1))}
      onFocus={(event) => event.target.select()}
      onKeyDown={handleKeyDown(index)}
      onPaste={(event) => { event.preventDefault(); fill(index, event.clipboardData.getData('text')); }}
    />)}
  </div>;
}

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
    if (cleanCode(code ?? '').length !== STAFF_CODE_LENGTH) {
      setActivation((current) => ({ ...current, error: `Enter all ${STAFF_CODE_LENGTH} characters of the staff registration code from your email.` }));
      return;
    }
    setActivation((current) => ({ ...current, busy: true, error: '' }));
    try {
      const response = await api.post('/activate-account', { ...credentials, staff_code: cleanCode(code) });
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

  const handleCodeChange = (code) => setActivation((current) => ({ ...current, code, error: '' }));

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
    <div className="auth-page-shell login-page">
      <AuthHeader />

      <main className="auth-page">
      {submitting && <LoadingOverlay />}
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
          <form className="confirm-dialog activation-dialog" role="dialog" aria-modal="true" aria-labelledby="activation-title" onSubmit={(event) => { event.preventDefault(); activate(activation.code); }} noValidate>
            <div className="activation-top">
            <img className="activation-illustration" src={peopleSearch} alt="" aria-hidden="true" />
            <div className="confirm-dialog-copy">
              <p className="eyebrow">Under verification</p>
              <h3 id="activation-title">We're sorry, you can't sign in yet</h3>
              <p>{activation.sent
                ? 'Your account is still under verification by the Super Admin. Please enter the 8-character Staff Registration Code that was sent to your email. Check your Spam or Promotions folder if you cannot find it.'
                : 'Your account is still under verification by the Super Admin. Once it has been reviewed, you will receive a Staff Registration Code by email. Enter it below to activate your account.'}</p>
            </div>
            </div>
            {activation.expired ? (
              <p className="notice error" role="alert">{activation.error} <Link to="/register">Register again</Link></p>
            ) : (
              <>
                <div className="auth-field activation-code-field">
                  <span>Staff Registration Code</span>
                  <StaffCodeBoxes value={activation.code} onChange={handleCodeChange} onComplete={activate} disabled={activation.busy} />
                </div>
                {activation.error ? <p className="notice error" role="alert">{activation.error}</p> : null}
              </>
            )}
            <div className="confirm-dialog-actions">
              <button className="ghost-button" disabled={activation.busy} onClick={() => setActivation(null)} type="button">Close</button>
              {!activation.expired && <button className="primary-button" disabled={activation.busy || cleanCode(activation.code).length !== STAFF_CODE_LENGTH} type="submit">{activation.busy ? 'Activating…' : 'Activate'}</button>}
            </div>
          </form>
        </div>
      )}

      <AuthFooter />
    </div>
  );
}

export default Login;
