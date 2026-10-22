import { useEffect, useRef, useState } from 'react';
import api from '../api/axios';
import { openImpersonatedWorkspace } from '../utils/impersonation';
import './ImpersonationPicker.css';

const supportedRoles = ['Admin', 'Custodian', 'Maintenance Personnel', 'Super Admin'];
const keyOf = (value) => value == null ? 'platform' : String(value);
const options = (rows, id, label, fallback) => [...new Map(rows.map((row) => [keyOf(row[id]), row[label] || fallback])).entries()].sort((a, b) => a[1].localeCompare(b[1]));

export default function ImpersonationPicker({ user }) {
  const rootToken = user.role === 'Impersonator'
    ? localStorage.getItem('token') || sessionStorage.getItem('token')
    : localStorage.getItem('impersonator_role') === 'Impersonator' ? localStorage.getItem('impersonator_token') : null;
  if (!rootToken) return null;
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
    api.get('/impersonate/candidates', { headers: { Authorization: `Bearer ${rootToken}` } })
      .then(({ data }) => { if (current) setAccounts(data); })
      .catch(() => { if (current) setError('Account controls could not be loaded. Please retry.'); })
      .finally(() => { if (current) setLoading(false); });
    return () => { current = false; };
  }, [rootToken, retry]);
  const currentAccount = accounts.find((account) => String(account.id) === String(user.id));
  const selected = selection ?? (currentAccount ? {
    province: keyOf(currentAccount.province_id), city: keyOf(currentAccount.city_id),
    barangay: keyOf(currentAccount.barangay_id), user: String(currentAccount.id),
  } : { province: '', city: '', barangay: '', user: '' });
  const provinceRows = accounts.filter((account) => keyOf(account.province_id) === selected.province);
  const cityRows = provinceRows.filter((account) => keyOf(account.city_id) === selected.city);
  const barangayRows = cityRows.filter((account) => keyOf(account.barangay_id) === selected.barangay);
  const switchUser = async (id) => {
    const account = barangayRows.find((row) => String(row.id) === id);
    if (!account || !account.is_active || !supportedRoles.includes(account.role) || switchingRef.current) return;
    if (id === String(user.id)) { setSelection({ ...selected, user: id }); return; }
    switchingRef.current = true; setBusy(true); setError('');
    try {
      const { data } = await api.post(`/impersonate/${id}`, { reason: 'Account selected from the workspace dropdowns' }, { headers: { Authorization: `Bearer ${rootToken}` } });
      if (user.role !== 'Impersonator') {
        try { await api.post('/logout'); } catch (err) {
          if (![401, 419].includes(err.response?.status)) {
            await api.post('/logout', null, { headers: { Authorization: `Bearer ${data.access_token}` } }).catch(() => {});
            throw err;
          }
        }
      }
      openImpersonatedWorkspace({ name: localStorage.getItem('impersonator_name') || user.name, role: 'Impersonator' }, data);
    } catch (err) {
      setError(err.response?.data?.message || 'Could not open this account. Select the user to try again.');
      switchingRef.current = false; setBusy(false);
    }
  };
  return <div className="account-cascade" aria-label="Impersonation account controls">
    <select aria-label="Impersonation province" value={selected.province} disabled={loading || busy} onChange={(event) => setSelection({ province: event.target.value, city: '', barangay: '', user: '' })}>
      <option value="">{loading ? 'Loading provinces...' : 'Province'}</option>{options(accounts, 'province_id', 'province_name', 'Platform accounts').map(([id, name]) => <option key={id} value={id}>{name}</option>)}
    </select>
    <select aria-label="Impersonation city or municipality" value={selected.city} disabled={loading || busy || !selected.province} onChange={(event) => setSelection({ ...selected, city: event.target.value, barangay: '', user: '' })}>
      <option value="">City / Municipality</option>{options(provinceRows, 'city_id', 'city_name', 'No city / municipality').map(([id, name]) => <option key={id} value={id}>{name}</option>)}
    </select>
    <select aria-label="Impersonation barangay" value={selected.barangay} disabled={loading || busy || !selected.city} onChange={(event) => setSelection({ ...selected, barangay: event.target.value, user: '' })}>
      <option value="">Barangay</option>{options(cityRows, 'barangay_id', 'barangay_name', 'No barangay').map(([id, name]) => <option key={id} value={id}>{name}</option>)}
    </select>
    <select aria-label="Impersonation user" value={selected.user} disabled={loading || busy || !selected.barangay} onChange={(event) => switchUser(event.target.value)}>
      <option value="">{busy ? 'Logging in...' : 'User'}</option>{barangayRows.map((account) => <option key={account.id} value={account.id} disabled={!account.is_active || !supportedRoles.includes(account.role)}>{account.name} - {account.role}{account.is_active ? '' : ' (inactive)'}</option>)}
    </select>
    {busy && <span className="account-cascade-status" role="status">Logging in...</span>}
    {error && <span className="account-cascade-error" role="alert">{error} {!accounts.length && <button type="button" onClick={() => { setLoading(true); setError(''); setRetry((value) => value + 1); }}>Retry</button>}</span>}
  </div>;
}
