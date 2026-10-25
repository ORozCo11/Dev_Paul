import { useContext, useEffect, useRef, useState } from 'react';
import { AuthContext } from '../context/AuthContextObject';
import api from '../api/axios';
import LoadingOverlay from '../components/LoadingOverlay';
import ImpersonationPicker from '../components/ImpersonationPicker';
import { openImpersonatedWorkspace } from '../utils/impersonation';

export default function ImpersonationHub() {
  const { user } = useContext(AuthContext);
  const requestRef = useRef(null);
  const openedRef = useRef(false);
  const [error, setError] = useState('');
  useEffect(() => {
    let current = true;
    // Reuse the request during Strict Mode's effect replay so only one session starts.
    requestRef.current ??= api.post('/impersonate/default');
    requestRef.current.then(({ data }) => {
      if (current && !openedRef.current) {
        openedRef.current = true;
        openImpersonatedWorkspace(user, data);
      }
    }).catch((err) => {
      if (current) setError(err.response?.data?.message || 'The default workspace could not be opened.');
    });
    return () => { current = false; };
  }, [user]);
  if (!error) return <LoadingOverlay label="Opening your Admin workspace..." />;
  return <main className="auth-page"><section className="auth-card"><h1>Unable to open the default workspace</h1><p role="alert">{error}</p><button type="button" className="primary-button" onClick={() => window.location.reload()}>Retry</button><ImpersonationPicker user={user} /></section></main>;
}
