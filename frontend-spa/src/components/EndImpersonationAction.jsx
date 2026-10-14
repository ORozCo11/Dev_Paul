import { useState } from 'react';
import api from '../api/axios';
import Icon from './Icon';

export default function EndImpersonationAction() {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const originalToken = localStorage.getItem('impersonator_token');
  if (!originalToken) return null;
  const end = async () => {
    if (busy) return;
    setBusy(true);
    try {
      try { await api.post('/logout'); } catch (requestError) {
        if (![401, 419].includes(requestError.response?.status)) throw requestError;
      }
      const role = localStorage.getItem('impersonator_role');
      const sessionOnly = localStorage.getItem('impersonator_storage') === 'session';
      localStorage.removeItem('token');
      sessionStorage.removeItem('token');
      (sessionOnly ? sessionStorage : localStorage).setItem('token', originalToken);
      ['impersonator_token', 'impersonator_name', 'impersonator_role', 'impersonator_storage'].forEach((key) => localStorage.removeItem(key));
      window.location.assign(({ 'Super Admin': '/superadmin', Admin: '/admin', Custodian: '/custodian', 'Maintenance Personnel': '/maintenance' })[role] ?? '/login');
    } catch {
      setError('Could not end the support session. Please try again.');
      setBusy(false);
    }
  };
  return <>
    <button type="button" className="profile-menu-item" disabled={busy} onClick={end}>
      <Icon name="arrowLeft" size={15} />{busy ? 'Ending session…' : 'End Impersonation'}
    </button>
    {error && <p className="profile-menu-impersonation-error" role="alert">{error}</p>}
  </>;
}
