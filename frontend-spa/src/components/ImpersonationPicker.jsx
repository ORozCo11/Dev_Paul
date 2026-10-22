import { useEffect, useState } from 'react';
import api from '../api/axios';
import { AdminActionDrawer } from './SuperAdminUI';

const destinations = { Admin: '/admin', Custodian: '/custodian', 'Maintenance Personnel': '/maintenance' };

export default function ImpersonationPicker({ user }) {
  const [candidates, setCandidates] = useState([]);
  const [error, setError] = useState('');
  const [province, setProvince] = useState('');
  const [city, setCity] = useState('');
  const [barangay, setBarangay] = useState('');
  const [targetId, setTargetId] = useState('');
  const [reviewing, setReviewing] = useState(false);
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    let current = true;
    api.get('/impersonate/candidates').then(({ data }) => {
      if (current) setCandidates(data.filter((account) => account.is_active && account.barangay_id && account.role !== 'Super Admin' && !account.roles?.includes('Super Admin')));
    }).catch(() => { if (current) setError('Account switching is unavailable. Reload to try again.'); });
    return () => { current = false; };
  }, []);
  const options = (rows, key, label) => [...new Map(rows.filter((row) => row[key]).map((row) => [String(row[key]), row[label]])).entries()].sort((a, b) => a[1].localeCompare(b[1]));
  const provinceUsers = candidates.filter((row) => String(row.province_id) === province);
  const cityUsers = provinceUsers.filter((row) => String(row.city_id) === city);
  const users = cityUsers.filter((row) => String(row.barangay_id) === barangay);
  const target = users.find((row) => String(row.id) === targetId);
  const start = async (event) => {
    event.preventDefault();
    if (!target || busy) return;
    setBusy(true);
    setError('');
    try {
      const { data } = await api.post(`/impersonate/${target.id}`, { reason: reason.trim() });
      const ownToken = localStorage.getItem('token') || sessionStorage.getItem('token');
      if (ownToken && !localStorage.getItem('impersonator_token')) {
        localStorage.setItem('impersonator_storage', localStorage.getItem('token') ? 'local' : 'session');
        localStorage.setItem('impersonator_token', ownToken);
        localStorage.setItem('impersonator_name', user.name);
        localStorage.setItem('impersonator_role', user.role);
      }
      localStorage.setItem('token', data.access_token);
      sessionStorage.removeItem('token');
      window.location.assign(destinations[data.user.role] ?? '/login');
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Could not impersonate this account.');
      setBusy(false);
    }
  };
  return <div className="sa-impersonation-picker">
    <select aria-label="Impersonation province" value={province} onChange={(event) => { setProvince(event.target.value); setCity(''); setBarangay(''); setTargetId(''); }}><option value="">Province</option>{options(candidates, 'province_id', 'province_name').map(([id, name]) => <option key={id} value={id}>{name}</option>)}</select>
    <select aria-label="Impersonation city or municipality" value={city} disabled={!province} onChange={(event) => { setCity(event.target.value); setBarangay(''); setTargetId(''); }}><option value="">City / Municipality</option>{options(provinceUsers, 'city_id', 'city_name').map(([id, name]) => <option key={id} value={id}>{name}</option>)}</select>
    <select aria-label="Impersonation barangay" value={barangay} disabled={!city} onChange={(event) => { setBarangay(event.target.value); setTargetId(''); }}><option value="">Barangay</option>{options(cityUsers, 'barangay_id', 'barangay_name').map(([id, name]) => <option key={id} value={id}>{name}</option>)}</select>
    <select aria-label="Impersonation user" value={targetId} disabled={!barangay} onChange={(event) => setTargetId(event.target.value)}><option value="">User</option>{users.map((row) => <option key={row.id} value={row.id}>{row.name} · {row.role}</option>)}</select>
    <button type="button" className="ghost-button" disabled={!target} onClick={() => { setReviewing(true); setError(''); }}>Impersonate</button>
    {error && !reviewing && <span role="alert">{error}</span>}
    {reviewing && target && <AdminActionDrawer title={`Impersonate ${target.name}`} description={`${target.barangay_name}, ${target.city_name}, ${target.province_name}`} onClose={() => { if (!busy) setReviewing(false); }}>
      <form onSubmit={start} className="smart-form"><p>{import.meta.env.DEV ? 'Switch accounts to test this user’s workspace.' : 'This opens a read-only support session. You can return to your own account using the session banner.'}</p><label>Reason<textarea required minLength={5} maxLength={500} value={reason} onChange={(event) => setReason(event.target.value)} /></label>{error && <p role="alert">{error}</p>}<button type="submit" className="primary-button" disabled={busy || reason.trim().length < 5}>{busy ? 'Switching…' : 'Start Session'}</button></form>
    </AdminActionDrawer>}
  </div>;
}
