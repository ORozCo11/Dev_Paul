import { useEffect, useMemo, useRef, useState } from 'react';
import api from '../api/axios';
import Icon from '../components/Icon';
import { DonutChart } from '../components/charts';
import { AdminActionDrawer, AdminEmptyState, AdminFilterField, AdminIdentity, AdminManagementCard, LocalSearchInput, PaginatedTable, StatusBadge } from '../components/SuperAdminUI';
import { formatDate } from '../utils/adminDate';

const ROLES = ['Admin', 'Custodian', 'Maintenance Personnel'];
const concernLabel = (report) => ({ 'Barangay Inactive': 'Barangay seems inactive', 'Suspected Fake Staff': 'Suspected fake staff', Other: 'Other' })[report.concern_type] ?? report.concern_type ?? 'Platform concern';
const location = (record) => [record.barangay_name, record.city_name, record.province_name].filter(Boolean).join(', ') || 'Unassigned';
const firstAdmin = (account, barangays) => account.role === 'Admin' && !barangays.find((b) => b.id === account.barangay_id)?.has_active_admin;

export function PlatformOverview({ barangays, approvals, concerns, onNavigate }) {
  const [expanded, setExpanded] = useState(false);
  const boundaries = barangays.filter((b) => b.boundary_status === 'needs_review');
  const unresolved = concerns.filter((report) => report.status !== 'Resolved');
  const active = barangays.filter((b) => b.has_active_admin).length;
  const metrics = [
    { label: 'Registered Barangays', value: barangays.length, icon: 'pin', tab: 'barangays', detail: `${active} with an active Administrator` },
    { label: 'Pending Account Approvals', value: approvals.length, icon: 'clipboard', tab: 'pending', detail: 'Registrations awaiting a decision' },
    { label: 'Boundaries Needing Review', value: boundaries.length, icon: 'pin', tab: 'barangays', filter: 'boundary_review', detail: 'Candidate boundaries to verify' },
    { label: 'Unresolved Concerns', value: unresolved.length, icon: 'mail', tab: 'concerns', detail: 'Reports awaiting attention' },
  ];
  const items = [
    ...[...approvals].sort((a, b) => Number(firstAdmin(b, barangays)) - Number(firstAdmin(a, barangays))).map((account) => ({ key: `approval-${account.id}`, title: firstAdmin(account, barangays) ? 'Review initial Administrator registration' : 'Review account registration', detail: `${account.name} · ${location(account)}`, status: 'Pending Approval', date: account.created_at, tab: 'pending' })),
    ...boundaries.map((b) => ({ key: `boundary-${b.id}`, title: 'Review candidate boundary', detail: `${b.name}, ${b.city_name ?? ''}`, status: 'Needs Review', date: b.updated_at, tab: 'barangays', filter: 'boundary_review' })),
    ...unresolved.map((report) => ({ key: `concern-${report.id}`, title: concernLabel(report), detail: report.barangay_name || report.reporter_name || 'Platform concern', status: report.status, date: report.created_at, tab: 'concerns' })),
    ...barangays.filter((b) => !b.has_active_admin).map((b) => ({ key: `recovery-${b.id}`, title: 'Recover Administrator coverage', detail: `${b.name}, ${b.city_name ?? ''}`, status: 'No Active Admin', tab: 'barangays', filter: 'orphaned' })),
  ];
  const segments = [{ label: 'Active Administrator', value: active, color: '#16a34a' }, { label: 'Without active Administrator', value: barangays.length - active, color: '#d97706' }];
  return <div className="focus-dashboard sa-overview">
    <div className="focus-kpis">{metrics.map((metric) => <button key={metric.label} type="button" onClick={() => onNavigate(metric.tab, metric.filter)}><span className="focus-kpi-icon"><Icon name={metric.icon} size={20} /></span><span>{metric.label}<strong>{metric.value}</strong><small>{metric.detail}</small></span><Icon name="chevronRight" size={15} /></button>)}</div>
    <div className="focus-columns">
      <section className="focus-card sa-queue"><header><div><h3>Administrative Action Queue</h3><p>Review the requests that need your attention.</p></div><span className="sa-count">{items.length} pending</span></header>
        {!items.length ? <AdminEmptyState icon="checkCircle" title="You're up to date" description="No administrative actions are waiting for review." /> : <ul className="focus-tasks">{(expanded ? items : items.slice(0, 5)).map((item) => <li key={item.key}><div><strong>{item.title}</strong><span>{item.detail}</span><div className="sa-queue-meta"><StatusBadge value={item.status} /><time>{item.date ? formatDate(item.date) : 'Date not recorded'}</time></div></div><button type="button" onClick={() => onNavigate(item.tab, item.filter)}>Review <Icon name="chevronRight" size={14} /></button></li>)}</ul>}
        {items.length > 5 && <button type="button" className="focus-more" onClick={() => setExpanded(!expanded)}>{expanded ? 'Show fewer actions' : `View all ${items.length} actions`}</button>}
      </section>
      <section className="focus-card sa-platform-status"><header><div><h3>Platform Status</h3><p>Administrator coverage across registered barangays.</p></div></header>{barangays.length ? <div className="focus-donut"><DonutChart segments={segments} centerLabel={barangays.length} centerSubLabel="barangays" /><ul>{segments.map((segment) => <li key={segment.label}><i style={{ background: segment.color }} />{segment.label}<strong>{segment.value}</strong></li>)}</ul></div> : <AdminEmptyState icon="pin" title="No barangays registered" description="Add a barangay to begin platform onboarding." />}<button type="button" className="focus-more" onClick={() => onNavigate('barangays')}>Manage barangays <Icon name="chevronRight" size={14} /></button></section>
    </div>
  </div>;
}

export function PendingApprovalsTab({ approvals, barangays, onApprove, onReject, onRequestConfirmation }) {
  const [search, setSearch] = useState('');
  const [barangay, setBarangay] = useState('');
  const [reviewing, setReviewing] = useState(null);
  const requestApprove = (account) => onRequestConfirmation({ title: 'Approve Registration', message: firstAdmin(account, barangays) ? `${account.name} will become ${account.barangay_name ?? 'this barangay'}'s first Admin — this activates a brand-new barangay account.` : `Approve ${account.name}'s registration for ${account.barangay_name ?? 'their barangay'} as ${account.role}?`, confirmLabel: 'Approve', variant: 'primary', onConfirm: async () => { const success = await onApprove(account); if (success) setReviewing(null); } });
  const requestReject = (account) => onRequestConfirmation({ title: 'Reject Registration', message: `Reject ${account.name}'s registration for ${account.barangay_name ?? 'their barangay'}? Their account is permanently deleted — this cannot be undone.`, confirmLabel: 'Reject & Delete', variant: 'danger', onConfirm: async () => { const success = await onReject(account); if (success) setReviewing(null); } });
  const matching = approvals.filter((account) => (!barangay || String(account.barangay_id) === barangay) && [account.name, account.email, account.role, location(account)].join(' ').toLowerCase().includes(search.trim().toLowerCase()));
  // Keep barangay groups together; groups awaiting their initial Admin lead.
  const priorityGroups = new Set(approvals.filter((account) => firstAdmin(account, barangays)).map((account) => account.barangay_id));
  const visible = matching.sort((a, b) => Number(priorityGroups.has(b.barangay_id)) - Number(priorityGroups.has(a.barangay_id)) || location(a).localeCompare(location(b)) || Number(firstAdmin(b, barangays)) - Number(firstAdmin(a, barangays)) || new Date(b.created_at) - new Date(a.created_at));
  const actions = (account) => <div className="sa-row-actions"><button className="ghost-button" type="button" onClick={() => setReviewing(account)}>Review</button><button className="primary-button" type="button" onClick={() => requestApprove(account)}>Approve</button><button className="ghost-button sa-destructive" type="button" onClick={() => requestReject(account)}>Reject</button></div>;
  const columns = [
    { label: 'Applicant', render: (account) => <AdminIdentity name={account.name} email={account.email} /> },
    { label: 'Barangay', render: (account) => <div className="sa-stacked"><strong>{account.barangay_name || 'Unassigned'}</strong><span>{[account.city_name, account.province_name].filter(Boolean).join(', ')}</span></div> },
    { label: 'Requested Role', key: 'role' },
    { label: 'Date Submitted', className: 'sa-date-cell', render: (account) => formatDate(account.created_at) },
    { label: 'Approval Type', render: (account) => <StatusBadge value={firstAdmin(account, barangays) ? 'Initial Administrator' : 'Staff Registration'} /> },
    { label: 'Actions', render: actions },
  ];
  return <div className="superadmin-tab"><AdminManagementCard summary={<><strong>{approvals.length} pending applications</strong><span>Initial Administrator applications are prioritized. All applicants are listed below.</span></>} toolbar={<><LocalSearchInput value={search} onChange={setSearch} placeholder="Search applicant, email, or barangay..." /><AdminFilterField label="Barangay"><select value={barangay} onChange={(event) => setBarangay(event.target.value)}><option value="">All Barangays</option>{[...new Map(approvals.map((account) => [String(account.barangay_id), location(account)])).entries()].map(([id, name]) => <option key={id} value={id}>{name}</option>)}</select></AdminFilterField></>}>
    <PaginatedTable rows={visible} columns={columns} groupBy={location} emptyIcon="checkCircle" emptyMessage={approvals.length ? 'No matching applications' : 'No pending approvals'} emptyDescription={approvals.length ? 'Try another applicant name or choose a different barangay.' : 'All account applications have been reviewed.'} />
  </AdminManagementCard>{reviewing && <AdminActionDrawer title="Review application" description="Check the applicant details before making a decision." onClose={() => setReviewing(null)} footer={actions(reviewing)}><AdminIdentity name={reviewing.name} email={reviewing.email} /><dl className="sa-detail-list"><dt>Barangay</dt><dd>{location(reviewing)}</dd><dt>Requested role</dt><dd>{reviewing.role}</dd><dt>Phone</dt><dd>{reviewing.phone || 'Not provided'}</dd><dt>Submitted</dt><dd>{formatDate(reviewing.created_at)}</dd><dt>Account state</dt><dd><StatusBadge value="Pending Approval" /></dd><dt>Approval type</dt><dd>{firstAdmin(reviewing, barangays) ? 'Initial Administrator registration' : 'Staff registration'}</dd></dl></AdminActionDrawer>}</div>;
}

export function UsersTab({ users, onChangeRole, onToggleActive, onApprove, onReject, onRequestConfirmation }) {
  const [search, setSearch] = useState('');
  const [barangay, setBarangay] = useState('');
  const [role, setRole] = useState('');
  const [status, setStatus] = useState('');
  const [editing, setEditing] = useState(null);
  const [nextRole, setNextRole] = useState('');
  const accountStatus = (account) => !account.approved_at ? 'Pending Approval' : account.is_active ? 'Active' : 'Deactivated';
  const requestStateChange = (account) => {
    const pending = !account.approved_at;
    const activate = pending || !account.is_active;
    onRequestConfirmation({ title: pending ? 'Approve Registration' : activate ? 'Activate Account' : 'Deactivate Account', message: `${pending ? 'Approve' : activate ? 'Activate' : 'Deactivate'} ${account.name}'s account for ${account.barangay_name ?? 'their barangay'}?${activate ? '' : ' They will lose access and their active sessions will end.'}`, confirmLabel: pending ? 'Approve' : activate ? 'Activate' : 'Deactivate', variant: activate ? 'primary' : 'danger', onConfirm: () => pending ? onApprove(account) : onToggleActive(account) });
  };
  const requestRoleChange = (event) => {
    event.preventDefault();
    onRequestConfirmation({ title: 'Change Account Role', message: `Change ${editing.name}'s role from ${(editing.roles ?? [editing.role]).join(', ')} to ${nextRole}? This replaces their current roles and changes account permissions.`, confirmLabel: 'Confirm Role Change', variant: 'primary', onConfirm: async () => { const success = await onChangeRole(editing, nextRole); if (success) setEditing(null); } });
  };
  const visible = users.filter((account) => (!barangay || String(account.barangay_id) === barangay) && (!role || (account.roles ?? [account.role]).includes(role)) && (!status || accountStatus(account) === status) && [account.name, account.email, location(account)].join(' ').toLowerCase().includes(search.trim().toLowerCase()));
  const columns = [
    { label: 'User', render: (account) => <AdminIdentity name={account.name} email={account.email} /> },
    { label: 'Barangay', render: (account) => <div className="sa-stacked"><strong>{account.barangay_name || 'Unassigned'}</strong><span>{[account.city_name, account.province_name].filter(Boolean).join(', ')}</span></div> },
    { label: 'Role', render: (account) => (account.roles?.length ? account.roles : [account.role]).join(', ') },
    { label: 'Account Status', render: (account) => <StatusBadge value={accountStatus(account)} /> },
    { label: 'Actions', render: (account) => <div className="sa-row-actions"><button className="ghost-button" type="button" onClick={() => { setEditing(account); setNextRole(account.role); }}>Edit Role</button><button type="button" className={`ghost-button${account.approved_at && account.is_active ? ' sa-destructive' : ''}`} onClick={() => requestStateChange(account)}>{!account.approved_at ? 'Approve' : account.is_active ? 'Deactivate' : 'Activate'}</button>{!account.approved_at && <button className="ghost-button sa-destructive" type="button" onClick={() => onRequestConfirmation({ title: 'Reject Registration', message: `Permanently delete ${account.name}'s pending registration? This cannot be undone.`, confirmLabel: 'Reject & Delete', variant: 'danger', onConfirm: () => onReject(account) })}>Reject</button>}</div> },
  ];
  return <div className="superadmin-tab"><AdminManagementCard toolbar={<><LocalSearchInput value={search} onChange={setSearch} placeholder="Search user or email..." /><AdminFilterField label="Barangay"><select value={barangay} onChange={(event) => setBarangay(event.target.value)}><option value="">All Barangays</option>{[...new Map(users.map((account) => [String(account.barangay_id), location(account)])).entries()].map(([id, name]) => <option key={id} value={id}>{name}</option>)}</select></AdminFilterField><AdminFilterField label="Role"><select value={role} onChange={(event) => setRole(event.target.value)}><option value="">All Roles</option>{ROLES.map((name) => <option key={name}>{name}</option>)}</select></AdminFilterField><AdminFilterField label="Account status"><select value={status} onChange={(event) => setStatus(event.target.value)}><option value="">All Statuses</option>{['Active', 'Deactivated', 'Pending Approval'].map((name) => <option key={name}>{name}</option>)}</select></AdminFilterField></>}>
    <PaginatedTable rows={visible} columns={columns} emptyMessage="No matching users" emptyDescription="Try another user name or adjust the account filters." />
  </AdminManagementCard>{editing && <AdminActionDrawer title="Edit account role" description="Review this account's access before saving." onClose={() => setEditing(null)}><AdminIdentity name={editing.name} email={editing.email} /><form className="sa-role-form" onSubmit={requestRoleChange}><p>Current roles: <strong>{(editing.roles ?? [editing.role]).join(', ')}</strong></p><AdminFilterField label="New role"><select value={nextRole} onChange={(event) => setNextRole(event.target.value)}>{ROLES.map((name) => <option key={name}>{name}</option>)}</select></AdminFilterField><p className="muted">Saving replaces the account's current roles. Last-Administrator and account restrictions still apply.</p><div className="sa-row-actions"><button type="button" className="ghost-button" onClick={() => setEditing(null)}>Cancel</button><button type="submit" className="primary-button" disabled={nextRole === editing.role}>Save Role</button></div></form></AdminActionDrawer>}</div>;
}

export function RegistrationCodesTab({ barangays, initialBarangay, onRequestConfirmation, setNotice }) {
  const [provinces, setProvinces] = useState([]);
  const [cities, setCities] = useState([]);
  const [provinceId, setProvinceId] = useState('');
  const [cityId, setCityId] = useState('');
  const [barangayId, setBarangayId] = useState(initialBarangay ? String(initialBarangay.id) : '');
  const [result, setResult] = useState({ code: null, updated: null, loading: false, error: null });
  const [retry, setRetry] = useState(0);
  const [barangayLookup, setBarangayLookup] = useState({ cityId: '', rows: [], error: null });
  const [barangayRetry, setBarangayRetry] = useState(0);
  const selectedRef = useRef(barangayId);
  useEffect(() => { selectedRef.current = barangayId; }, [barangayId]);
  useEffect(() => {
    let current = true;
    api.get('/provinces').then(({ data }) => { if (!current) return; setProvinces(data); if (initialBarangay) setProvinceId(String(data.find((p) => p.name === initialBarangay.province_name)?.id ?? '')); }).catch(() => { if (current) setNotice({ type: 'error', text: 'Could not load provinces. Please reopen Registration Codes to retry.' }); });
    return () => { current = false; };
  }, [initialBarangay, setNotice]);
  useEffect(() => {
    if (!provinceId) return;
    let current = true;
    api.get('/cities', { params: { province_id: provinceId } }).then(({ data }) => { if (!current) return; setCities(data); if (initialBarangay?.province_name === provinces.find((p) => String(p.id) === provinceId)?.name) setCityId(String(data.find((city) => city.name === initialBarangay.city_name)?.id ?? '')); }).catch(() => { if (current) setNotice({ type: 'error', text: 'Could not load cities. Select the province again to retry.' }); });
    return () => { current = false; };
  }, [provinceId, initialBarangay, provinces, setNotice]);
  useEffect(() => {
    if (!barangayId) return;
    let current = true;
    api.get(`/superadmin/barangays/${barangayId}/registration-code`).then(({ data }) => { if (current) setResult({ code: data.staff_code, updated: data.updated_at, loading: false, error: null }); }).catch((error) => { if (current) setResult({ code: null, updated: null, loading: false, error: error.response?.data?.message ?? 'Could not load the registration code.' }); });
    return () => { current = false; };
  }, [barangayId, retry]);
  useEffect(() => {
    if (!cityId) return;
    let current = true;
    api.get('/barangays', { params: { city_id: cityId } }).then(({ data }) => {
      if (current) setBarangayLookup({ cityId, rows: data, error: null });
    }).catch(() => {
      if (current) setBarangayLookup({ cityId, rows: [], error: 'Could not load barangays. Please retry.' });
    });
    return () => { current = false; };
  }, [cityId, barangayRetry]);
  const selectedProvince = provinces.find((p) => String(p.id) === provinceId);
  const selectedCity = cities.find((city) => String(city.id) === cityId);
  const barangaysLoading = Boolean(cityId) && barangayLookup.cityId !== cityId;
  const barangaysError = barangayLookup.cityId === cityId ? barangayLookup.error : null;
  const options = useMemo(() => {
    if (!selectedCity || barangayLookup.cityId !== cityId) return [];
    const managed = barangays.filter((b) => b.city_name === selectedCity.name && b.province_name === selectedProvince?.name);
    return [...new Map([...managed, ...barangayLookup.rows].map((b) => [String(b.id), b])).values()].sort((a, b) => a.name.localeCompare(b.name));
  }, [barangays, selectedCity, selectedProvince, barangayLookup, cityId]);
  const selected = options.find((b) => String(b.id) === barangayId) ?? barangays.find((b) => String(b.id) === barangayId);
  const changeBarangay = (id) => { selectedRef.current = id; setBarangayId(id); setResult({ code: null, updated: null, loading: Boolean(id), error: null }); };
  const regenerate = () => {
    const id = barangayId;
    onRequestConfirmation({ title: 'Regenerate Registration Code', message: `Regenerate the code for ${selected?.name}? The old code stops working immediately.`, confirmLabel: 'Regenerate', variant: 'danger', onConfirm: async () => {
      if (selectedRef.current === id) setResult({ code: null, updated: null, loading: true, error: null });
      try { const { data } = await api.post(`/superadmin/barangays/${id}/registration-code/regenerate`); if (selectedRef.current === id) setResult({ code: data.staff_code, updated: data.updated_at, loading: false, error: null }); setNotice({ type: 'success', text: 'Registration code regenerated.' }); }
      catch (error) { if (selectedRef.current === id) setResult({ code: null, updated: null, loading: false, error: error.response?.data?.message ?? 'Could not regenerate that code.' }); throw error; }
    } });
  };
  const copyCode = async () => { try { await navigator.clipboard.writeText(result.code); setNotice({ type: 'success', text: 'Registration code copied.' }); } catch { setNotice({ type: 'error', text: 'Could not copy automatically. Select the code and copy it manually.' }); } };
  return <div className="superadmin-tab sa-codes"><AdminManagementCard filtersCollapsible={false} toolbar={<><AdminFilterField label="Province"><select value={provinceId} onChange={(event) => { setProvinceId(event.target.value); setCityId(''); setCities([]); changeBarangay(''); }}><option value="">Select a province</option>{provinces.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</select></AdminFilterField><AdminFilterField label="City / Municipality"><select disabled={!provinceId} value={cityId} onChange={(event) => { setCityId(event.target.value); changeBarangay(''); }}><option value="">{provinceId ? 'Select a city' : 'Select a province first'}</option>{cities.map((city) => <option key={city.id} value={city.id}>{city.name}</option>)}</select></AdminFilterField><AdminFilterField label="Barangay"><select disabled={!cityId || barangaysLoading || Boolean(barangaysError) || !options.length} value={barangayId} onChange={(event) => changeBarangay(event.target.value)}><option value="">{barangaysLoading ? 'Loading barangays...' : barangaysError ? 'Barangays unavailable' : cityId ? (options.length ? 'Select a barangay' : 'No barangays available') : 'Select a city first'}</option>{options.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}</select></AdminFilterField></>}>
    {barangaysError && <p className="notice error" role="alert">{barangaysError} <button type="button" className="ghost-button" onClick={() => { setBarangayLookup({ cityId: '', rows: [], error: null }); setBarangayRetry((value) => value + 1); }}>Retry barangays</button></p>}
    {!barangayId ? <AdminEmptyState icon="key" title="Select a barangay" description="Choose a province, city, and barangay to view or provision its staff registration code." /> : result.error ? <AdminEmptyState icon="alert" title="Code unavailable" description={result.error}><button className="ghost-button" type="button" onClick={() => { setResult({ code: null, updated: null, loading: true, error: null }); setRetry(retry + 1); }}>Retry lookup</button></AdminEmptyState> : result.loading || !result.code ? <AdminEmptyState icon="key" title="Looking up registration code" description="Checking authorized access for this barangay." /> : <div className="sa-code-details"><div><h3>Registration Code Details</h3><p><strong>{selected?.name}</strong> · {[selectedCity?.name ?? selected?.city_name, selectedProvince?.name ?? selected?.province_name].filter(Boolean).join(', ')}</p></div><code>{result.code}</code>{result.updated && <p>Last updated {formatDate(result.updated)}</p>}<div className="sa-row-actions"><button className="primary-button" type="button" onClick={copyCode}><Icon name="clipboard" size={16} />Copy Code</button><button className="ghost-button sa-destructive" type="button" onClick={regenerate}>Regenerate Code</button></div></div>}
  </AdminManagementCard></div>;
}

export function ConcernReportsTab({ reports, onResolve, onReopen, onDelete, onRequestConfirmation }) {
  const [search, setSearch] = useState('');
  const [scope, setScope] = useState('open');
  const [reviewing, setReviewing] = useState(null);
  const visible = reports.filter((report) => (scope !== 'open' || report.status !== 'Resolved') && [report.id, report.description, report.barangay_name, report.reporter_name, concernLabel(report)].join(' ').toLowerCase().includes(search.trim().toLowerCase()));
  const actions = (report) => <div className="sa-row-actions"><button className="primary-button" type="button" onClick={async () => { const success = await (report.status === 'Resolved' ? onReopen(report) : onResolve(report)); if (success) setReviewing(null); }}>{report.status === 'Resolved' ? 'Reopen' : 'Mark Resolved'}</button><button className="ghost-button sa-destructive" type="button" onClick={() => onRequestConfirmation({ title: 'Delete Concern Report', message: 'Delete this concern report permanently? This cannot be undone.', confirmLabel: 'Delete', variant: 'danger', onConfirm: async () => { const success = await onDelete(report); if (success) setReviewing(null); } })}>Delete</button></div>;
  const columns = [
    { label: 'Concern', render: (report) => <div className="sa-stacked"><strong>CR-{report.id}</strong><span>{concernLabel(report)}</span></div> },
    { label: 'Barangay', render: (report) => report.barangay_name || 'Not specified' },
    { label: 'Date Submitted', className: 'sa-date-cell', render: (report) => formatDate(report.created_at) },
    { label: 'Status', render: (report) => <StatusBadge value={report.status} /> },
    { label: 'Description', className: 'sa-description-cell', render: (report) => <p className="sa-truncate">{report.description}</p> },
    { label: 'Actions', render: (report) => <button className="ghost-button" type="button" onClick={() => setReviewing(report)}>Review</button> },
  ];
  return <div className="superadmin-tab"><AdminManagementCard summary={<><strong>{visible.length} {scope === 'open' ? 'open concerns' : 'reports'}</strong><span>Review submissions and record the outcome.</span></>} toolbar={<><div className="sa-segments"><button type="button" aria-pressed={scope === 'open'} onClick={() => setScope('open')}>Open <span>{reports.filter((report) => report.status !== 'Resolved').length}</span></button><button type="button" aria-pressed={scope === 'all'} onClick={() => setScope('all')}>All Reports <span>{reports.length}</span></button></div><LocalSearchInput value={search} onChange={setSearch} placeholder="Search concerns or reporters..." /></>}>
    <PaginatedTable rows={visible} columns={columns} emptyIcon={search ? 'search' : 'checkCircle'} emptyMessage={search ? 'No matching concerns' : scope === 'open' ? 'No Open Concerns' : 'No concern reports yet'} emptyDescription={search ? 'Try another reference, barangay, or reporter name.' : scope === 'open' ? 'All reported platform concerns have been addressed.' : 'Submitted platform concerns will appear here.'} />
  </AdminManagementCard>{reviewing && <AdminActionDrawer title={`Concern CR-${reviewing.id}`} description={concernLabel(reviewing)} onClose={() => setReviewing(null)} footer={actions(reviewing)}><dl className="sa-detail-list"><dt>Barangay</dt><dd>{reviewing.barangay_name || 'Not specified'}</dd><dt>Reported by</dt><dd>{reviewing.reporter_name || 'Anonymous'}</dd><dt>Contact</dt><dd>{reviewing.reporter_contact || 'Not provided'}</dd><dt>Submitted</dt><dd>{formatDate(reviewing.created_at)}</dd><dt>Status</dt><dd><StatusBadge value={reviewing.status} /></dd>{reviewing.resolved_at && <><dt>Resolved</dt><dd>{formatDate(reviewing.resolved_at)}{reviewing.resolved_by?.name ? ` by ${reviewing.resolved_by.name}` : ''}</dd></>}</dl><h3>Full description</h3><p className="sa-full-text">{reviewing.description}</p></AdminActionDrawer>}</div>;
}

export function ActivityLogTab({ entries }) {
  const [search, setSearch] = useState('');
  const [scope, setScope] = useState('all');
  const [reviewing, setReviewing] = useState(null);
  const visible = entries.filter((entry) => (scope !== 'mine' || entry.module === 'Super Admin') && [entry.user?.name, entry.role, entry.action, entry.module, entry.details].join(' ').toLowerCase().includes(search.trim().toLowerCase()));
  const columns = [
    { label: 'Date & Time', className: 'sa-date-cell', render: (entry) => formatDate(entry.created_at) },
    { label: 'Actor', render: (entry) => entry.user?.name ?? 'Deleted account' },
    { label: 'Role', render: (entry) => entry.role ?? entry.user?.role ?? 'Not recorded' },
    { label: 'Action', render: (entry) => <StatusBadge value={entry.action} /> },
    { label: 'Module', key: 'module' },
    { label: 'Details', className: 'sa-log-details', render: (entry) => <><p className="sa-truncate">{entry.details}</p><button className="sa-text-button" type="button" onClick={() => setReviewing(entry)}>View full details</button></> },
  ];
  return <div className="superadmin-tab"><AdminManagementCard summary={<><strong>{visible.length} activity records</strong><span>Account and platform administration · Times shown in Philippine time</span></>} toolbar={<><div className="sa-segments"><button type="button" aria-pressed={scope === 'all'} onClick={() => setScope('all')}>Full Platform Log</button><button type="button" aria-pressed={scope === 'mine'} onClick={() => setScope('mine')}>Super Admin Actions Only</button></div><LocalSearchInput value={search} onChange={setSearch} placeholder="Search platform activity..." /></>}>
    <PaginatedTable rows={visible} columns={columns} initialPageSize={25} pageSizeOptions={[25, 50, 100]} emptyMessage="No matching activity records" emptyDescription="Try another search or choose the full authorized platform log." />
  </AdminManagementCard>{reviewing && <AdminActionDrawer title="Activity details" description={`Record ${reviewing.log_id ?? reviewing.id ?? ''}`} onClose={() => setReviewing(null)}><dl className="sa-detail-list"><dt>Date & time</dt><dd>{formatDate(reviewing.created_at)}</dd><dt>Actor</dt><dd>{reviewing.user?.name ?? 'Deleted account'}</dd><dt>Role at time of action</dt><dd>{reviewing.role ?? reviewing.user?.role ?? 'Not recorded'}</dd><dt>Action</dt><dd>{reviewing.action}</dd><dt>Module</dt><dd>{reviewing.module}</dd></dl><h3>Complete details</h3><p className="sa-full-text">{reviewing.details}</p></AdminActionDrawer>}</div>;
}
