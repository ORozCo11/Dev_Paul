import { useContext, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { AuthContext } from '../context/AuthContextObject';
import api from '../api/axios';
import VMSLogo from '../components/VMSLogo';
import Icon from '../components/Icon';
import LoadingOverlay from '../components/LoadingOverlay';
import { openImpersonatedWorkspace } from '../utils/impersonation';
import './ImpersonationHub.css';

const routes = { Admin: '/admin', Custodian: '/custodian', 'Maintenance Personnel': '/maintenance', 'Super Admin': '/superadmin' };
const matches = (value, selected) => !selected || (selected === 'none' ? !value : String(value) === selected);
const options = (rows, id, name) => [...new Map(rows.filter((row) => row[id]).map((row) => [String(row[id]), row[name]])).entries()].sort((a, b) => a[1].localeCompare(b[1]));

export default function ImpersonationHub() {
  const location = useLocation();
  return new URLSearchParams(location.search).get('choose') === '1'
    ? <AccountChooser /> : <DefaultWorkspace />;
}

function DefaultWorkspace() {
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
  return <main className="auth-page"><section className="auth-card"><h1>Unable to open the default workspace</h1><p role="alert">{error}</p><button type="button" className="primary-button" onClick={() => window.location.reload()}>Retry</button><Link className="ghost-button" to="/impersonation?choose=1">Choose another account</Link></section></main>;
}

function AccountChooser() {
  const { user, logout } = useContext(AuthContext);
  const navigate = useNavigate();
  const [accounts, setAccounts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [retry, setRetry] = useState(0);
  const [search, setSearch] = useState('');
  const [province, setProvince] = useState('');
  const [city, setCity] = useState('');
  const [barangay, setBarangay] = useState('');
  const [role, setRole] = useState('');
  const [page, setPage] = useState(1);
  const [openingId, setOpeningId] = useState(null);
  const switchingRef = useRef(false);
  useEffect(() => {
    let current = true;
    api.get('/impersonate/candidates').then(({ data }) => { if (current) setAccounts(data); })
      .catch(() => { if (current) setError('Accounts could not be loaded. Please retry.'); })
      .finally(() => { if (current) setLoading(false); });
    return () => { current = false; };
  }, [retry]);
  const provinceRows = accounts.filter((row) => matches(row.province_id, province));
  const cityRows = provinceRows.filter((row) => matches(row.city_id, city));
  const filtered = useMemo(() => accounts.filter((row) => matches(row.province_id, province) && matches(row.city_id, city) && matches(row.barangay_id, barangay) && (!role || row.role === role)
    && [row.name, row.email, row.role, row.barangay_name, row.city_name, row.province_name].some((value) => String(value ?? '').toLowerCase().includes(search.trim().toLowerCase()))), [accounts, province, city, barangay, role, search]);
  const barangayCount = new Set(accounts.map((row) => row.barangay_id).filter(Boolean)).size;
  const pages = Math.max(1, Math.ceil(filtered.length / 10));
  const currentPage = Math.min(page, pages);
  const start = async (account) => {
    if (switchingRef.current) return;
    switchingRef.current = true;
    setOpeningId(account.id); setError('');
    try {
      const { data } = await api.post(`/impersonate/${account.id}`, { reason: 'Account selected from the dedicated account switcher' });
      openImpersonatedWorkspace(user, data);
    } catch (err) {
      setError(err.response?.data?.message || 'Could not open this account.');
      setOpeningId(null); switchingRef.current = false;
    }
  };
  return <div className="impersonation-hub">
    <header className="switcher-header"><a href="/impersonation" className="switcher-brand"><VMSLogo variant="blue" size={34} /><span>vms <small>Account switcher</small></span></a><div><span>{user.email}</span><button type="button" className="ghost-button" disabled={openingId !== null} onClick={async () => { await logout(); navigate('/login', { replace: true }); }}>Sign out</button></div></header>
    <main>
      <div className="switcher-heading"><span className="switcher-eyebrow">ACCOUNT ACCESS</span><h1>Choose an account</h1><p>Find a user in any barangay and open their workspace. Use Switch account to choose a different workspace.</p></div>
      <section className="switcher-panel" aria-label="Account switcher">
        <div className="switcher-panel-heading"><h2>All accounts <span>{accounts.length}</span></h2><span>{barangayCount} barangay{barangayCount === 1 ? '' : 's'}</span></div>
        <div className="switcher-filters">
          <label className="switcher-search">Search accounts<input type="search" value={search} placeholder="Name, email, role, or barangay" onChange={(event) => { setSearch(event.target.value); setPage(1); }} /></label>
          <label>Province<select value={province} onChange={(event) => { setProvince(event.target.value); setCity(''); setBarangay(''); setPage(1); }}><option value="">All provinces</option><option value="none">No assigned province</option>{options(accounts, 'province_id', 'province_name').map(([id, name]) => <option key={id} value={id}>{name}</option>)}</select></label>
          <label>City / Municipality<select value={city} onChange={(event) => { setCity(event.target.value); setBarangay(''); setPage(1); }}><option value="">All cities / municipalities</option>{options(provinceRows, 'city_id', 'city_name').map(([id, name]) => <option key={id} value={id}>{name}</option>)}</select></label>
          <label>Barangay<select value={barangay} onChange={(event) => { setBarangay(event.target.value); setPage(1); }}><option value="">All barangays</option>{options(cityRows, 'barangay_id', 'barangay_name').map(([id, name]) => <option key={id} value={id}>{name}</option>)}</select></label>
          <label>Role<select value={role} onChange={(event) => { setRole(event.target.value); setPage(1); }}><option value="">All roles</option>{[...new Set(accounts.map((row) => row.role))].sort().map((value) => <option key={value}>{value}</option>)}</select></label>
        </div>
        {loading && <p className="switcher-message" role="status">Loading accounts…</p>}
        {error && <div className="notice error" role="alert">{error} <button type="button" className="ghost-button" onClick={() => { setLoading(true); setError(''); setRetry((value) => value + 1); }}>Retry</button></div>}
        {!loading && <><div className="switcher-table"><table><thead><tr><th>Account</th><th>Role</th><th>Barangay</th><th>City / Province</th><th>Status</th><th>Action</th></tr></thead><tbody>
          {filtered.slice((currentPage - 1) * 10, currentPage * 10).map((account) => <tr key={account.id}><td><strong>{account.name}</strong><small>{account.email}</small></td><td data-label="Role">{account.role}</td><td data-label="Barangay">{account.barangay_name || 'No assigned barangay'}</td><td data-label="City / Province">{[account.city_name, account.province_name].filter(Boolean).join(', ') || 'Platform account'}</td><td data-label="Status"><span className={`switcher-status ${account.is_active ? 'is-active' : ''}`}>{account.is_active ? 'Active' : 'Inactive'}</span></td><td><button className="primary-button" type="button" disabled={openingId !== null || !account.is_active || !routes[account.role]} onClick={() => start(account)}><Icon name="chevronRight" size={15} /> {openingId === account.id ? 'Opening...' : 'Impersonate'}</button></td></tr>)}
          {!filtered.length && <tr><td colSpan={6} className="switcher-message">No accounts match these filters.</td></tr>}
        </tbody></table></div><nav className="switcher-pagination" aria-label="Account pages"><span>{filtered.length} matching accounts</span><div><button className="ghost-button" type="button" disabled={currentPage <= 1} onClick={() => setPage(currentPage - 1)}>Previous</button><span>Page {currentPage} of {pages}</span><button className="ghost-button" type="button" disabled={currentPage >= pages} onClick={() => setPage(currentPage + 1)}>Next</button></div></nav></>}
      </section>
    </main>

  </div>;
}
