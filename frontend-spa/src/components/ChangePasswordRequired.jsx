import { useContext, useState } from 'react';
import api from '../api/axios';
import Icon from './Icon';
import Aurora from './Aurora';
import AuthHeader from './AuthHeader';
import AuthFooter from './AuthFooter';
import { AuthContext } from '../context/AuthContextObject';

// Shown instead of the workspace while an account still has the temporary
// password its Admin set. Nothing else works until it is replaced (the API
// refuses everything but this change and sign-out).
export default function ChangePasswordRequired() {
  const { user, logout, refreshUser } = useContext(AuthContext);
  const [values, setValues] = useState({ old_password: '', new_password: '', new_password_confirmation: '' });
  const [show, setShow] = useState(false);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const set = (name) => (event) => { setValues((v) => ({ ...v, [name]: event.target.value })); setError(''); };

  const submit = async (event) => {
    event.preventDefault();
    if (values.new_password.length < 8) { setError('Your new password must be at least 8 characters.'); return; }
    if (values.new_password !== values.new_password_confirmation) { setError('The new passwords do not match.'); return; }
    if (values.new_password === values.old_password) { setError('Choose a password that is different from the temporary one.'); return; }
    setBusy(true);
    try {
      await api.put('/profile/password', values);
      await refreshUser();
    } catch (err) {
      const errors = err.response?.data?.errors;
      setError(err.response?.data?.message ?? (errors ? Object.values(errors).flat()[0] : 'Could not change your password. Try again.'));
    } finally {
      setBusy(false);
    }
  };

  const field = (name, label, autoComplete, autoFocus = false) => (
    <label className="auth-field">
      <span>{label}</span>
      <div className="auth-input-wrapper auth-input-plain">
        <input
          autoComplete={autoComplete}
          autoFocus={autoFocus}
          onChange={set(name)}
          required
          type={show ? 'text' : 'password'}
          value={values[name]}
        />
        <button type="button" className="password-toggle-btn" onClick={() => setShow((v) => !v)} aria-label={show ? 'Hide passwords' : 'Show passwords'}>
          <Icon name={show ? 'eyeOff' : 'eye'} size={18} />
        </button>
      </div>
    </label>
  );

  return (
    <div className="auth-page-shell">
      <AuthHeader />

      <main className="auth-page">
        <Aurora colorStops={['#0b1220', '#1e3a5f', '#0f172a']} amplitude={0.6} blend={0.55} />
      </main>

      <div className="confirm-overlay">
        <form className="confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="change-password-title" onSubmit={submit} noValidate>
          <div className="confirm-dialog-icon" aria-hidden="true"><Icon name="key" size={22} /></div>
          <div className="confirm-dialog-copy">
            <p className="eyebrow">First sign-in</p>
            <h3 id="change-password-title">Set your own password</h3>
            <p>
              {user?.name ? `Welcome, ${user.name.split(' ')[0]}. ` : ''}You signed in with a temporary password from your Admin. Choose a new one now to continue. Only you will know it.
            </p>
          </div>
          {field('old_password', 'Temporary password', 'current-password', true)}
          {field('new_password', 'New password', 'new-password')}
          {field('new_password_confirmation', 'Confirm new password', 'new-password')}
          {error ? <p className="notice error" role="alert">{error}</p> : null}
          <div className="confirm-dialog-actions">
            <button className="ghost-button" disabled={busy} onClick={() => logout()} type="button">Sign out</button>
            <button className="primary-button" disabled={busy} type="submit">{busy ? 'Saving…' : 'Change password'}</button>
          </div>
        </form>
      </div>

      <AuthFooter />
    </div>
  );
}
