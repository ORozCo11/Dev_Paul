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
  const provinceAccounts = accounts.filter((account) => account.province_id != null && account.province_name);
  const currentAccount = provinceAccounts.find((account) => String(account.id) === String(user.id));
  const selected = selection ?? (currentAccount ? {
    province: keyOf(currentAccount.province_id), city: keyOf(currentAccount.city_id),
    barangay: keyOf(currentAccount.barangay_id), user: String(currentAccount.id),
  } : { province: '', city: '', barangay: '', user: '' });
  const provinceRows = provinceAccounts.filter((account) => keyOf(account.province_id) === selected.province);
  const cityRows = provinceRows.filter((account) => keyOf(account.city_id) === selected.city);
  const barangayRows = cityRows.filter((account) => keyOf(account.barangay_id) === selected.barangay);
  const selectedUserId = barangayRows.some((account) => String(account.id) === selected.user)
    ? selected.user
    : String(barangayRows.find((account) => account.is_active && supportedRoles.includes(account.role))?.id ?? '');
  const switchUser = async (id) => {
    const account = barangayRows.find((row) => String(row.id) === id);
    if (!account || !account.is_active || !supportedRoles.includes(account.role) || switchingRef.current) return;
    if (id === String(user.id)) { setSelection({ ...selected, user: id }); return; }
    switchingRef.current = true; setBusy(true); setError('');
    try {
      const { data } = await api.post(`/impersonate/${id}`, { reason: 'Account selected using the Impersonate button' }, { headers: { Authorization: `Bearer ${rootToken}` } });
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
    <SearchableSelect aria-label="Impersonation province" value={selected.province} disabled={loading || busy} onChange={(event) => setSelection({ province: event.target.value, city: '', barangay: '', user: '' })}>
      <option value="">{loading ? 'Loading provinces...' : 'Province'}</option>{options(provinceAccounts, 'province_id', 'province_name', '').map(([id, name]) => <option key={id} value={id}>{name}</option>)}
    </SearchableSelect>
    <SearchableSelect aria-label="Impersonation city or municipality" value={selected.city} disabled={loading || busy || !selected.province} onChange={(event) => setSelection({ ...selected, city: event.target.value, barangay: '', user: '' })}>
      <option value="">City / Municipality</option>{options(provinceRows, 'city_id', 'city_name', 'No city / municipality').map(([id, name]) => <option key={id} value={id}>{name}</option>)}
    </SearchableSelect>
    <SearchableSelect aria-label="Impersonation barangay" value={selected.barangay} disabled={loading || busy || !selected.city} onChange={(event) => setSelection({ ...selected, barangay: event.target.value, user: '' })}>
      <option value="">Barangay</option>{options(cityRows, 'barangay_id', 'barangay_name', 'No barangay').map(([id, name]) => <option key={id} value={id}>{name}</option>)}
    </SearchableSelect>
    <div className="account-user-control">
      <SearchableSelect aria-label="Impersonation user" value={selectedUserId} disabled={loading || busy || !selected.barangay || !barangayRows.length} onChange={(event) => setSelection({ ...selected, user: event.target.value })}>
        {!barangayRows.length && <option value="" disabled>{loading ? 'Loading accounts...' : selected.barangay ? 'No accounts available' : 'Select a barangay first'}</option>}
        {barangayRows.map((account) => <option key={account.id} value={account.id} disabled={!account.is_active || !supportedRoles.includes(account.role)}>{account.name} - {account.role}{account.is_active ? '' : ' (inactive)'}</option>)}
      </SearchableSelect>
      <button className="account-impersonate-button" type="button" disabled={loading || busy || !selectedUserId || selectedUserId === String(user.id)} onClick={() => switchUser(selectedUserId)}>{busy ? 'Logging in...' : 'Impersonate'}</button>
    </div>
    {busy && <span className="account-cascade-status" role="status">Logging in...</span>}
    {error && <span className="account-cascade-error" role="alert">{error} {!accounts.length && <button type="button" onClick={() => { setLoading(true); setError(''); setRetry((value) => value + 1); }}>Retry</button>}</span>}
  </div>;
}
