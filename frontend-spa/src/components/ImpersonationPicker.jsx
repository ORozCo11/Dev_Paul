import SearchableSelect from './SearchableSelect';
import { useEffect, useRef, useState } from 'react';
import api from '../api/axios';
import { openImpersonatedWorkspace, impersonationRootToken } from '../utils/impersonation';
import './ImpersonationPicker.css';

const supportedRoles = ['Admin', 'Custodian', 'Maintenance Personnel', 'Super Admin'];
const keyOf = (value) => value == null ? 'platform' : String(value);
const options = (rows, id, label, fallback) => [...new Map(rows.map((row) => [keyOf(row[id]), row[label] || fallback])).entries()].sort((a, b) => a[1].localeCompare(b[1]));

export default function ImpersonationPicker({ user }) {
  const rootToken = impersonationRootToken(user);
  // The dedicated switcher auto-opens a role workspace and must remain
  // available there to switch to the next account.
  const dedicatedSession = localStorage.getItem('impersonator_role') === 'Impersonator';
  if (!rootToken || (user.is_impersonating && !dedicatedSession)) return null;
  return <AccountCascade key={user.id} user={user} rootToken={rootToken} />;
}

function AccountCascade({ user, rootToken }) {
  const [accounts, setAccounts] = useState([]);
  const [selection, setSelection] = useState(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [retry, setRetry] = useState(0);
  const switchingRef = useRef(false);
  useEffect(() => {
    let current = true;
    let timer = null;
    api.get('/impersonate/candidates', { headers: { Authorization: `Bearer ${rootToken}` } })
      .then(({ data }) => { if (current) setAccounts(data); })
      // No error banner: a failed load (usually a brief rate limit) is retried quietly a couple of times.
      .catch(() => { if (current && retry < 2) timer = setTimeout(() => { if (current) setRetry((value) => value + 1); }, 4000); })
      .finally(() => { if (current) setLoading(false); });
    return () => { current = false; if (timer) clearTimeout(timer); };
  }, [rootToken, retry]);
  // One municipality: only the barangay is chosen (no province or city step).
  const barangayAccounts = accounts.filter((account) => account.barangay_id != null);
  const currentAccount = barangayAccounts.find((account) => String(account.id) === String(user.id));
  const selected = selection ?? (currentAccount ? {
    barangay: keyOf(currentAccount.barangay_id), user: String(currentAccount.id),
  } : { barangay: '', user: '' });
  const barangayRows = barangayAccounts.filter((account) => keyOf(account.barangay_id) === selected.barangay);
  const selectedUserId = barangayRows.some((account) => String(account.id) === selected.user)
    ? selected.user
    : String(barangayRows.find((account) => account.is_active && supportedRoles.includes(account.role))?.id ?? '');
  const switchUser = async (id) => {
    const account = barangayRows.find((row) => String(row.id) === id);
    if (!account || !account.is_active || !supportedRoles.includes(account.role) || switchingRef.current) return;
    if (id === String(user.id)) { setSelection({ ...selected, user: id }); return; }
    switchingRef.current = true; setBusy(true); setError('');
    try {
      const { data } = await api.post(`/impersonate/${id}`, { reason: `Account workspace access: ${account.name} (${account.role}) in ${[account.barangay_name, account.city_name, account.province_name].filter(Boolean).join(', ')}`.slice(0, 500) }, { headers: { Authorization: `Bearer ${rootToken}` } });
      if (user.is_impersonating) {
        try { await api.post('/logout'); } catch (err) {
          if (![401, 419].includes(err.response?.status)) {
            await api.post('/logout', null, { headers: { Authorization: `Bearer ${data.access_token}` } }).catch(() => {});
            throw err;
          }
        }
      }
      openImpersonatedWorkspace({ name: localStorage.getItem('impersonator_name') || user.name, role: localStorage.getItem('impersonator_role') || user.role }, data);
    } catch (err) {
      setError(err.response?.data?.message || 'Could not open this account. Please try again.');
      switchingRef.current = false; setBusy(false);
    }
  };
  return <div className="account-cascade" aria-label="Impersonation account controls">
<div className="account-selector">    <SearchableSelect aria-label="Impersonation barangay" placeholder={loading ? 'Loading barangays...' : 'Barangay'} value={selected.barangay} disabled={loading || busy} onChange={(event) => setSelection({ barangay: event.target.value, user: '' })}>
      {options(barangayAccounts, 'barangay_id', 'barangay_name', 'No barangay').map(([id, name]) => <option key={id} value={id}>{name}</option>)}
    </SearchableSelect></div>
    <div className="account-user-control">
<div className="account-selector">      <SearchableSelect aria-label="Impersonation user" placeholder={loading ? 'Loading accounts...' : !selected.barangay ? 'Select a barangay first' : !barangayRows.length ? 'No accounts available' : 'Select an account'} menuClassName="account-user-menu" value={selectedUserId} disabled={loading || busy || !selected.barangay || !barangayRows.length} onChange={(event) => setSelection({ ...selected, user: event.target.value })}>
        {barangayRows.map((account) => <option key={account.id} value={account.id} disabled={!account.is_active || !supportedRoles.includes(account.role)}>{account.name}</option>)}
      </SearchableSelect></div>
      <button className="account-impersonate-button" type="button" aria-busy={busy} disabled={loading || busy || !selectedUserId || selectedUserId === String(user.id)} onClick={() => switchUser(selectedUserId)}><span aria-live="polite">{busy ? 'Logging in...' : 'Impersonate'}</span></button>
    </div>
    {error && <span className="account-cascade-error" role="alert">{error}</span>}
  </div>;
}
