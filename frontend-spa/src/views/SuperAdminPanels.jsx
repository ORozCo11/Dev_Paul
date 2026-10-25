import SearchableSelect from '../components/SearchableSelect';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import api from '../api/axios';
import Icon from '../components/Icon';
import { AdminActionDrawer, AdminEmptyState, AdminFilterField, AdminIdentity, AdminManagementCard, LocalSearchInput, PaginatedTable, StatusBadge } from '../components/SuperAdminUI';
import { formatDate } from '../utils/adminDate';
import './RegistrationCodes.css';

const ROLES = ['Admin', 'Custodian', 'Maintenance Personnel'];
const effectiveRoles = (account) => [...new Set([...(account.roles ?? []), account.role].filter(Boolean))];
const location = (record) => [record.barangay_name, record.city_name, record.province_name].filter(Boolean).join(', ') || 'Unassigned';
const firstAdmin = (account, barangays) => account.role === 'Admin' && !barangays.find((b) => b.id === account.barangay_id)?.has_active_admin;

const QUEUE_TYPES = [
  ['approval', 'Account approvals'],
  ['recovery', 'Administrator recovery'],
  ['boundary', 'Boundary review'],
];
const QUEUE_SORTS = [
  ['priority', 'Priority'],
  ['barangay', 'Barangay (A–Z)'],
  ['newest', 'Newest first'],
  ['oldest', 'Oldest first'],
];
const QUEUE_PAGE_SIZE = 8;
const percent = (part, whole) => whole ? Math.round((part / whole) * 100) : 0;
const uniqueSorted = (values) => [...new Set(values.filter(Boolean))].sort((a, b) => a.localeCompare(b));

// Every queue entry comes straight from the loaded records. A date is shown
// only when the record carries one, under the label that matches its meaning.
function buildQueue(barangays, approvals) {
  return [
    ...approvals.map((account) => {
      const initial = firstAdmin(account, barangays);
      return { key: `approval-${account.id}`, type: 'approval', priority: initial ? 0 : 1, barangay: account.barangay_name || 'Unassigned barangay', city: account.city_name, province: account.province_name, request: initial ? 'Initial Administrator registration' : 'Staff account registration', detail: `${account.name} · applying as ${account.role}`, status: 'Pending Approval', date: account.created_at, dateLabel: 'Submitted', action: 'Review application', path: `/superadmin/pending?application=${account.id}`, search: [account.name, account.email] };
    }),
    ...barangays.filter((b) => !b.has_active_admin).map((b) => ({ key: `recovery-${b.id}`, type: 'recovery', priority: 2, barangay: b.name, city: b.city_name, province: b.province_name, request: 'Administrator recovery', detail: b.staff_count ? `${b.staff_count} registered staff · no active Administrator` : 'No registered staff · share the registration code to onboard', status: 'No Active Admin', action: 'Assign administrator', path: `/superadmin/barangays/${b.id}`, search: [] })),
    ...barangays.filter((b) => b.boundary_status === 'needs_review').map((b) => ({ key: `boundary-${b.id}`, type: 'boundary', priority: 3, barangay: b.name, city: b.city_name, province: b.province_name, request: 'Boundary review', detail: b.pending_boundary ? 'A candidate outline is waiting for review' : 'The current outline is an unverified reference', status: 'Needs Review', date: b.updated_at, dateLabel: 'Last updated', action: 'Review boundary', path: `/superadmin/barangays/${b.id}`, search: [] })),
  ];
}

const byDate = (direction) => (a, b) => {
  if (!a.date && !b.date) return 0;
  if (!a.date) return 1;
  if (!b.date) return -1;
  return direction * (new Date(a.date) - new Date(b.date));
};
const QUEUE_SORTERS = {
  priority: (a, b) => a.priority - b.priority || byDate(1)(a, b) || a.barangay.localeCompare(b.barangay),
  barangay: (a, b) => a.barangay.localeCompare(b.barangay) || a.priority - b.priority,
  newest: (a, b) => byDate(-1)(a, b) || a.priority - b.priority,
  oldest: (a, b) => byDate(1)(a, b) || a.priority - b.priority,
};

function ActionQueue({ items, onOpen }) {
  const [search, setSearch] = useState('');
  const [type, setType] = useState('');
  const [sort, setSort] = useState('priority');
  const [province, setProvince] = useState('');
  const [city, setCity] = useState('');
  const [barangay, setBarangay] = useState('');
  const [page, setPage] = useState(1);
  const update = (setter) => (value) => { setter(value); setPage(1); };
  const provinces = uniqueSorted(items.map((item) => item.province));
  const cities = uniqueSorted(items.filter((item) => item.province === province).map((item) => item.city));
  const barangayNames = uniqueSorted(items.filter((item) => item.province === province && item.city === city).map((item) => item.barangay));
  const term = search.trim().toLowerCase();
  const visible = items
    .filter((item) => (!type || item.type === type)
      && (!province || item.province === province)
      && (!city || item.city === city)
      && (!barangay || item.barangay === barangay)
      && (!term || [item.barangay, item.city, item.province, item.request, ...item.search].join(' ').toLowerCase().includes(term)))
    .sort(QUEUE_SORTERS[sort]);
  const totalPages = Math.max(1, Math.ceil(visible.length / QUEUE_PAGE_SIZE));
  const current = Math.min(page, totalPages);
  const start = (current - 1) * QUEUE_PAGE_SIZE;
  const rows = visible.slice(start, start + QUEUE_PAGE_SIZE);
  const filtered = Boolean(term || type || province);
  const clear = () => { setSearch(''); setType(''); setProvince(''); setCity(''); setBarangay(''); setPage(1); };
  return <section className="sa-management-card sa-queue" aria-labelledby="sa-queue-title">
    <header className="sa-queue-head">
      <div><h2 id="sa-queue-title">Administrative Action Queue</h2><p>Requests waiting on a Super Admin decision, most urgent first.</p></div>
      <span className="sa-count">{items.length} open</span>
    </header>
    {items.length > 0 && <div className="sa-queue-controls">
      <div className="sa-queue-row">
        <LocalSearchInput value={search} onChange={update(setSearch)} placeholder="Search barangay, applicant, or email..." />
        <AdminFilterField label="Request type"><SearchableSelect value={type} onChange={(event) => update(setType)(event.target.value)}><option value="">All requests ({items.length})</option>{QUEUE_TYPES.map(([key, label]) => <option key={key} value={key}>{label} ({items.filter((item) => item.type === key).length})</option>)}</SearchableSelect></AdminFilterField>
        <AdminFilterField label="Sort by"><SearchableSelect value={sort} onChange={(event) => update(setSort)(event.target.value)}>{QUEUE_SORTS.map(([key, label]) => <option key={key} value={key}>{label}</option>)}</SearchableSelect></AdminFilterField>
      </div>
      <fieldset className="sa-location-filter">
        <legend><Icon name="pin" size={14} />Location</legend>
        <AdminFilterField label="Province"><SearchableSelect value={province} onChange={(event) => { update(setProvince)(event.target.value); setCity(''); setBarangay(''); }}><option value="">All provinces</option>{provinces.map((name) => <option key={name}>{name}</option>)}</SearchableSelect></AdminFilterField>
        <AdminFilterField label="City / Municipality"><SearchableSelect value={city} disabled={!province} onChange={(event) => { update(setCity)(event.target.value); setBarangay(''); }}><option value="">{province ? 'All cities / municipalities' : 'Choose a province first'}</option>{cities.map((name) => <option key={name}>{name}</option>)}</SearchableSelect></AdminFilterField>
        <AdminFilterField label="Barangay"><SearchableSelect value={barangay} disabled={!city} onChange={(event) => update(setBarangay)(event.target.value)}><option value="">{city ? 'All barangays' : 'Choose a city first'}</option>{barangayNames.map((name) => <option key={name}>{name}</option>)}</SearchableSelect></AdminFilterField>
        {filtered && <button type="button" className="ghost-button sa-clear-filters" onClick={clear}>Clear filters</button>}
      </fieldset>
    </div>}
    {!items.length ? <AdminEmptyState icon="checkCircle" title="You're up to date" description="No account applications, administrator gaps, or boundary reviews are waiting." />
      : !visible.length ? <AdminEmptyState title="No matching requests" description="Try another search term or clear the filters."><button type="button" className="ghost-button" onClick={clear}>Clear filters</button></AdminEmptyState>
      : <>
        <ul className="sa-queue-list">{rows.map((item) => <li key={item.key}>
          <div className="sa-queue-item">
            <div className="sa-queue-title"><h3>{item.barangay}</h3><StatusBadge value={item.status} /></div>
            <span className="sa-queue-place">{[item.city, item.province].filter(Boolean).join(', ') || 'Location not specified'}{item.date && <> · {item.dateLabel} <time dateTime={item.date}>{formatDate(item.date)}</time></>}</span>
            <p><strong>{item.request}</strong> · {item.detail}</p>
          </div>
          <button type="button" className="ghost-button sa-queue-action" onClick={() => onOpen(item.path)}>{item.action}<Icon name="chevronRight" size={14} /></button>
        </li>)}</ul>
        <nav className="sa-pagination" aria-label="Action queue pages">
          <span>Showing {start + 1}–{start + rows.length} of {visible.length}</span>
          <div className="sa-page-buttons">
            <button type="button" disabled={current === 1} aria-label="Previous page" onClick={() => setPage(current - 1)}><Icon name="arrowLeft" size={16} /></button>
            <span aria-live="polite">Page {current} of {totalPages}</span>
            <button type="button" disabled={current === totalPages} aria-label="Next page" onClick={() => setPage(current + 1)}><Icon name="chevronRight" size={16} /></button>
          </div>
        </nav>
      </>}
  </section>;
}

function CoverageBar({ label, value, total, tone }) {
  const share = percent(value, total);
  return <div className="sa-coverage-row">
    <div><span>{label}</span><strong>{value} <small>({share}%)</small></strong></div>
    <div className="sa-coverage-track" role="progressbar" aria-label={label} aria-valuemin={0} aria-valuemax={total} aria-valuenow={value} aria-valuetext={`${value} of ${total} barangays (${share}%)`}><span className={`sa-coverage-fill ${tone}`} style={{ width: `${share}%` }} /></div>
  </div>;
}

function PlatformStatus({ barangays, onNavigate }) {
  const total = barangays.length;
  const covered = barangays.filter((b) => b.has_active_admin).length;
  const soleAdmin = barangays.filter((b) => b.sole_active_admin).length;
  const reviewed = barangays.filter((b) => b.boundary_status === 'verified').length;
  const needsReview = barangays.filter((b) => b.boundary_status === 'needs_review').length;
  return <section className="sa-management-card sa-platform-status" aria-labelledby="sa-status-title">
    <header className="sa-queue-head"><div><h2 id="sa-status-title">Platform Status</h2><p>Coverage across {total} registered {total === 1 ? 'barangay' : 'barangays'}.</p></div></header>
    {!total ? <AdminEmptyState icon="pin" title="No barangays registered" description="Add a barangay to begin platform onboarding."><button type="button" className="ghost-button" onClick={() => onNavigate('barangays')}>Go to Barangay Management</button></AdminEmptyState> : <div className="sa-status-body">
      <div className="sa-status-headline"><strong>{percent(covered, total)}%</strong><span>{covered} of {total} barangays have an active Administrator</span></div>
      <CoverageBar label="With an active Administrator" value={covered} total={total} tone="good" />
      <CoverageBar label="Without an active Administrator" value={total - covered} total={total} tone="warn" />
      {soleAdmin > 0 && <p className="sa-status-note"><Icon name="alert" size={13} />{soleAdmin} {soleAdmin === 1 ? 'barangay relies' : 'barangays rely'} on a single active Administrator.</p>}
      <h3>Boundary records</h3>
      <CoverageBar label="Reviewed" value={reviewed} total={total} tone="good" />
      <CoverageBar label="Needs review" value={needsReview} total={total} tone="warn" />
      <CoverageBar label="No boundary found" value={total - reviewed - needsReview} total={total} tone="muted" />
    </div>}
    {total - covered > 0 && <footer className="sa-status-actions"><button type="button" className="primary-button" onClick={() => onNavigate('barangays', 'orphaned')}>Assign administrators ({total - covered})</button></footer>}
  </section>;
}

export function PlatformOverview({ barangays, approvals, onNavigate, onOpen }) {
  const items = useMemo(() => buildQueue(barangays, approvals), [barangays, approvals]);
  const covered = barangays.filter((b) => b.has_active_admin).length;
  const orphaned = barangays.length - covered;
  const boundaries = barangays.filter((b) => b.boundary_status === 'needs_review').length;
  const metrics = [
    { label: 'Registered Barangays', value: barangays.length, kind: 'Total', icon: 'pin', tab: 'barangays', detail: `${covered} covered by an active Administrator`, cta: 'View all barangays' },
    { label: 'Pending Account Approvals', value: approvals.length, kind: approvals.length ? 'Needs action' : 'All clear', icon: 'clipboard', tab: 'pending', detail: approvals.length ? 'Registrations awaiting your decision' : 'Every registration has been reviewed', cta: 'Review approvals' },
    { label: 'Barangays Without an Admin', value: orphaned, kind: orphaned ? 'Needs action' : 'All clear', icon: 'alert', tab: 'barangays', filter: 'orphaned', detail: orphaned ? 'Promote staff or onboard an Administrator' : 'Every barangay has an Administrator', cta: 'Assign administrators' },
    { label: 'Boundaries Needing Review', value: boundaries, kind: boundaries ? 'Needs action' : 'All clear', icon: 'flag', tab: 'barangays', filter: 'boundary_review', detail: boundaries ? 'Outlines to check against LGU sources' : 'No outlines are waiting for review', cta: 'Review boundaries' },
  ];
  return <div className="sa-overview">
    <ul className="sa-kpis" aria-label="Platform summary">{metrics.map((metric) => <li key={metric.label}>
      <button type="button" className={`sa-kpi${metric.kind === 'Needs action' ? ' is-attention' : ''}`} onClick={() => onNavigate(metric.tab, metric.filter)}>
        <span className="sa-kpi-top"><span className="sa-kpi-icon" aria-hidden="true"><Icon name={metric.icon} size={18} /></span><span className={`sa-kpi-kind ${metric.kind === 'Needs action' ? 'attention' : metric.kind === 'All clear' ? 'clear' : 'total'}`}>{metric.kind}</span></span>
        <span className="sa-kpi-label">{metric.label}</span>
        <strong>{metric.value}</strong>
        <small>{metric.detail}</small>
        <span className="sa-kpi-cta">{metric.cta}<Icon name="chevronRight" size={14} /></span>
      </button>
    </li>)}</ul>
    <div className="sa-overview-columns">
      <ActionQueue items={items} onOpen={onOpen} />
      <PlatformStatus barangays={barangays} onNavigate={onNavigate} />
    </div>
  </div>;
}

export function PendingApprovalsTab({ approvals, barangays, onApprove, onReject, onRequestConfirmation }) {
  const [search, setSearch] = useState('');
  const [barangay, setBarangay] = useState('');
  const [paging, setPaging] = useState({ page: 1, size: 10 });
  const [searchParams, setSearchParams] = useSearchParams();
  const applicationId = searchParams.get('application');
  const reviewing = approvals.find((account) => String(account.id) === applicationId);
  const reviewHeadingRef = useRef(null);
  const setReviewing = (account) => {
    const next = new URLSearchParams(searchParams);
    if (account) next.set('application', String(account.id));
    else next.delete('application');
    setSearchParams(next);
  };
  useEffect(() => {
    if (!applicationId) return;
    reviewHeadingRef.current?.focus();
    window.scrollTo({ top: 0, behavior: 'instant' });
  }, [applicationId]);
  const requestApprove = (account) => onRequestConfirmation({ title: 'Approve Registration', message: firstAdmin(account, barangays) ? `${account.name} will become ${account.barangay_name ?? 'this barangay'}'s first Admin — this activates a brand-new barangay account.` : `Approve ${account.name}'s registration for ${account.barangay_name ?? 'their barangay'} as ${account.role}?`, confirmLabel: 'Approve', variant: 'primary', onConfirm: async () => { const success = await onApprove(account); if (success) setReviewing(null); } });
  const requestReject = (account) => onRequestConfirmation({ title: 'Reject Registration', message: `Reject ${account.name}'s registration for ${account.barangay_name ?? 'their barangay'}? Their account is permanently deleted — this cannot be undone.`, confirmLabel: 'Reject & Delete', variant: 'danger', onConfirm: async () => { const success = await onReject(account); if (success) setReviewing(null); } });
  const matching = approvals.filter((account) => (!barangay || String(account.barangay_id) === barangay) && [account.name, account.email, account.role, location(account)].join(' ').toLowerCase().includes(search.trim().toLowerCase()));
  // Keep barangay groups together; groups awaiting their initial Admin lead.
  const priorityGroups = new Set(approvals.filter((account) => firstAdmin(account, barangays)).map((account) => account.barangay_id));
  const visible = matching.sort((a, b) => Number(priorityGroups.has(b.barangay_id)) - Number(priorityGroups.has(a.barangay_id)) || location(a).localeCompare(location(b)) || Number(firstAdmin(b, barangays)) - Number(firstAdmin(a, barangays)) || new Date(b.created_at) - new Date(a.created_at));
  const currentPage = Math.min(paging.page, Math.max(1, Math.ceil(visible.length / paging.size)));
  const pageRows = visible.slice((currentPage - 1) * paging.size, currentPage * paging.size);
  const actions = (account) => <div className="sa-row-actions"><button className="ghost-button" type="button" onClick={() => setReviewing(account)}>Review</button><button className="primary-button" type="button" onClick={() => requestApprove(account)}>Approve</button><button className="ghost-button sa-destructive" type="button" onClick={() => requestReject(account)}>Reject</button></div>;
  const columns = [
    { label: 'Applicant', render: (account) => <AdminIdentity name={account.name} email={account.email} /> },
    { label: 'Barangay', render: (account) => <div className="sa-stacked"><strong>{account.barangay_name || 'Unassigned'}</strong><span>{[account.city_name, account.province_name].filter(Boolean).join(', ')}</span></div> },
    { label: 'Requested Role', key: 'role' },
    { label: 'Date Submitted', className: 'sa-date-cell', render: (account) => formatDate(account.created_at) },
    { label: 'Approval Type', render: (account) => <StatusBadge value={firstAdmin(account, barangays) ? 'Initial Administrator' : 'Staff Registration'} /> },
    { label: 'Actions', render: actions },
  ];
  if (applicationId) return <section className="sa-application-page" aria-labelledby="application-review-title">
    <button type="button" className="ghost-button sa-review-back" onClick={() => setReviewing(null)}><Icon name="arrowLeft" size={16} />Back to Pending Approvals</button>
    <section className="sa-management-card sa-application-card">
      <header className="sa-application-header">
        <div><h2 id="application-review-title" ref={reviewHeadingRef} tabIndex={-1}>Review application</h2><p>Verify the applicant through a trusted barangay office contact or appointment record before approving. A registration code proves possession of an invitation, not official authority.</p></div>
        {reviewing && <StatusBadge value="Pending Approval" />}
      </header>
      {reviewing ? <>
        <div className="sa-application-body"><AdminIdentity name={reviewing.name} email={reviewing.email} /><dl className="sa-detail-list"><dt>Barangay</dt><dd>{location(reviewing)}</dd><dt>Requested role</dt><dd>{reviewing.role}</dd><dt>Phone</dt><dd>{reviewing.phone || 'Not provided'}</dd><dt>Submitted</dt><dd>{formatDate(reviewing.created_at)}</dd><dt>Account state</dt><dd><StatusBadge value="Pending Approval" /></dd><dt>Approval type</dt><dd>{firstAdmin(reviewing, barangays) ? 'Initial Administrator registration' : 'Staff registration'}</dd></dl></div>
        <footer className="sa-application-actions"><p>Approve this registration to grant account access, or reject the application.</p><div className="sa-row-actions"><button className="ghost-button" type="button" onClick={() => setReviewing(null)}>Cancel</button><button className="ghost-button sa-destructive" type="button" onClick={() => requestReject(reviewing)}>Reject application</button><button className="primary-button" type="button" onClick={() => requestApprove(reviewing)}>Approve application</button></div></footer>
      </> : <AdminEmptyState icon="clipboard" title="Application unavailable" description="This application may have already been reviewed. Return to Pending Approvals to see the current list." />}
    </section>
  </section>;
  return <div className="superadmin-tab"><AdminManagementCard summary={<><strong>{approvals.length} pending applications</strong><span>Initial Administrator applications are prioritized. All applicants are listed below.</span></>} toolbar={<><LocalSearchInput value={search} onChange={(value) => { setSearch(value); setPaging((current) => ({ ...current, page: 1 })); }} placeholder="Search applicant, email, or barangay..." /><AdminFilterField label="Barangay"><SearchableSelect value={barangay} onChange={(event) => { setBarangay(event.target.value); setPaging((current) => ({ ...current, page: 1 })); }}><option value="">All Barangays</option>{[...new Map(approvals.map((account) => [String(account.barangay_id), location(account)])).entries()].map(([id, name]) => <option key={id} value={id}>{name}</option>)}</SearchableSelect></AdminFilterField></>}>
    <PaginatedTable rows={pageRows} columns={columns} groupBy={location} pagination={{ ...paging, total: visible.length }} onPageChange={(page) => setPaging((current) => ({ ...current, page }))} onPageSizeChange={(size) => setPaging({ page: 1, size })} emptyIcon="checkCircle" emptyMessage={approvals.length ? 'No matching applications' : 'No pending approvals'} emptyDescription={approvals.length ? 'Try another applicant name or choose a different barangay.' : 'All account applications have been reviewed.'} />
  </AdminManagementCard></div>;
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
    onRequestConfirmation({ title: 'Change Account Role', message: `Change ${editing.name}'s role from ${effectiveRoles(editing).join(', ')} to ${nextRole}? This replaces their current roles and changes account permissions.`, confirmLabel: 'Confirm Role Change', variant: 'primary', onConfirm: async () => { const success = await onChangeRole(editing, nextRole); if (success) setEditing(null); } });
  };
  const visible = users.filter((account) => (!barangay || String(account.barangay_id) === barangay) && (!role || effectiveRoles(account).includes(role)) && (!status || accountStatus(account) === status) && [account.name, account.email, location(account)].join(' ').toLowerCase().includes(search.trim().toLowerCase()));
  const columns = [
    { label: 'User', render: (account) => <AdminIdentity name={account.name} email={account.email} /> },
    { label: 'Barangay', render: (account) => <div className="sa-stacked"><strong>{account.barangay_name || 'Unassigned'}</strong><span>{[account.city_name, account.province_name].filter(Boolean).join(', ')}</span></div> },
    { label: 'Role', render: (account) => effectiveRoles(account).join(', ') },
    { label: 'Account Status', render: (account) => <StatusBadge value={accountStatus(account)} /> },
    { label: 'Actions', render: (account) => <div className="sa-row-actions"><button className="ghost-button" type="button" onClick={() => { setEditing(account); setNextRole(account.role); }}>Edit Role</button><button type="button" className={`ghost-button${account.approved_at && account.is_active ? ' sa-destructive' : ''}`} onClick={() => requestStateChange(account)}>{!account.approved_at ? 'Approve' : account.is_active ? 'Deactivate' : 'Activate'}</button>{!account.approved_at && <button className="ghost-button sa-destructive" type="button" onClick={() => onRequestConfirmation({ title: 'Reject Registration', message: `Permanently delete ${account.name}'s pending registration? This cannot be undone.`, confirmLabel: 'Reject & Delete', variant: 'danger', onConfirm: () => onReject(account) })}>Reject</button>}</div> },
  ];
  return <div className="superadmin-tab"><AdminManagementCard toolbar={<><LocalSearchInput value={search} onChange={setSearch} placeholder="Search user or email..." /><AdminFilterField label="Barangay"><SearchableSelect value={barangay} onChange={(event) => setBarangay(event.target.value)}><option value="">All Barangays</option>{[...new Map(users.map((account) => [String(account.barangay_id), location(account)])).entries()].map(([id, name]) => <option key={id} value={id}>{name}</option>)}</SearchableSelect></AdminFilterField><AdminFilterField label="Role"><SearchableSelect value={role} onChange={(event) => setRole(event.target.value)}><option value="">All Roles</option>{ROLES.map((name) => <option key={name}>{name}</option>)}</SearchableSelect></AdminFilterField><AdminFilterField label="Account status"><SearchableSelect value={status} onChange={(event) => setStatus(event.target.value)}><option value="">All Statuses</option>{['Active', 'Deactivated', 'Pending Approval'].map((name) => <option key={name}>{name}</option>)}</SearchableSelect></AdminFilterField></>}>
    <PaginatedTable rows={visible} columns={columns} emptyMessage="No matching users" emptyDescription="Try another user name or adjust the account filters." />
  </AdminManagementCard>{editing && <AdminActionDrawer title="Edit account role" description="Review this account's access before saving." onClose={() => setEditing(null)}><AdminIdentity name={editing.name} email={editing.email} /><form className="sa-role-form" onSubmit={requestRoleChange}><p>Current roles: <strong>{effectiveRoles(editing).join(', ')}</strong></p><AdminFilterField label="New role"><SearchableSelect value={nextRole} onChange={(event) => setNextRole(event.target.value)}>{ROLES.map((name) => <option key={name}>{name}</option>)}</SearchableSelect></AdminFilterField><p className="muted">Saving replaces the account's current roles. Last-Administrator and account restrictions still apply.</p><div className="sa-row-actions"><button type="button" className="ghost-button" onClick={() => setEditing(null)}>Cancel</button><button type="submit" className="primary-button" disabled={nextRole === editing.role && effectiveRoles(editing).length === 1}>Save Role</button></div></form></AdminActionDrawer>}</div>;
}

const CODE_REVEAL_MS = 60000;
// The API refuses to issue or show a code without a live, settled boundary.
const hasPendingBoundary = (b) => Boolean(b.has_pending_boundary ?? b.pending_boundary);
const codeState = (b) => !b.has_boundary || hasPendingBoundary(b) ? 'boundary' : b.has_registration_code ? 'active' : 'none';
const CODE_STATE_LABELS = { active: 'Code active', none: 'No code yet', boundary: 'Boundary required' };
const sameMoment = (a, b) => a && b && Math.abs(new Date(a) - new Date(b)) < 1000;
const historyFor = (name) => `registration code for ${name}.`;

function CodeHistory({ barangay, version }) {
  const [attempt, setAttempt] = useState(0);
  const [result, setResult] = useState({ key: '', rows: [], error: null });
  const key = `${barangay.id}:${version}:${attempt}`;
  useEffect(() => {
    let current = true;
    const params = new URLSearchParams({ paginate: '1', per_page: '25', scope: 'superadmin', q: historyFor(barangay.name) });
    api.get(`/superadmin/activity-log?${params}`).then(({ data }) => {
      if (current) setResult({ key, rows: data.data.filter((entry) => entry.details?.endsWith(historyFor(barangay.name))), error: null });
    }).catch(() => { if (current) setResult({ key, rows: [], error: 'Code history could not be loaded.' }); });
    return () => { current = false; };
  }, [barangay.name, key]);
  const loading = result.key !== key;
  return <section className="sa-code-history" aria-labelledby="sa-code-history-title">
    <h4 id="sa-code-history-title">Code history</h4>
    {loading ? <p role="status">Loading code history…</p>
      : result.error ? <p role="alert">{result.error} <button type="button" className="sa-text-button" onClick={() => setAttempt((value) => value + 1)}>Retry</button></p>
      : !result.rows.length ? <p>No Super Admin issue or regeneration has been recorded for this barangay.</p>
      : <ol>{result.rows.map((entry) => <li key={entry.log_id}>
        <strong>{entry.details.startsWith('Issued') ? 'Code issued' : 'Code regenerated'}</strong>
        <span>{entry.user?.name ?? 'Deleted account'} · <time dateTime={entry.created_at}>{formatDate(entry.created_at)}</time></span>
      </li>)}</ol>}
    <p className="sa-code-history-note">Regenerations made by the barangay's own Admin appear in that barangay's activity log.</p>
  </section>;
}

function CodePanel({ barangay, onRequestConfirmation, setNotice, onChanged }) {
  const navigate = useNavigate();
  const state = codeState(barangay);
  const [attempt, setAttempt] = useState(0);
  const [issuing, setIssuing] = useState(false);
  const [detail, setDetail] = useState({ key: '', data: null, error: null });
  const [revealed, setRevealed] = useState(false);
  const [copy, setCopy] = useState('');
  const [message, setMessage] = useState(null);
  const [historyVersion, setHistoryVersion] = useState(0);
  const key = `${attempt}`;
  const shouldLoad = state === 'active' || issuing;
  useEffect(() => {
    if (!shouldLoad) return;
    let current = true;
    api.get(`/superadmin/barangays/${barangay.id}/registration-code`).then(({ data }) => {
      if (!current) return;
      setDetail({ key, data, error: null });
      if (issuing) { setIssuing(false); setHistoryVersion((value) => value + 1); setNotice({ type: 'success', text: `A registration code is now active for ${barangay.name}.` }); onChanged(); }
    }).catch((error) => {
      if (!current) return;
      setDetail({ key, data: null, error: error.response?.data?.message ?? 'The registration code could not be loaded.' });
      setIssuing(false);
    });
    return () => { current = false; };
  }, [barangay.id, barangay.name, key, shouldLoad, issuing, onChanged, setNotice]);
  // A revealed code hides itself again after a minute on screen.
  useEffect(() => {
    if (!revealed) return undefined;
    const timer = setTimeout(() => setRevealed(false), CODE_REVEAL_MS);
    return () => clearTimeout(timer);
  }, [revealed, detail.data?.staff_code]);
  useEffect(() => {
    if (!copy) return undefined;
    const timer = setTimeout(() => setCopy(''), 3000);
    return () => clearTimeout(timer);
  }, [copy]);
  const loaded = detail.key === key ? detail : { data: null, error: null };
  const code = loaded.data?.staff_code;
  const copyCode = async () => {
    try { await navigator.clipboard.writeText(code); setCopy('copied'); }
    catch { setCopy('failed'); }
  };
  const regenerate = () => onRequestConfirmation({
    title: 'Regenerate registration code?',
    message: `A new code will be created for ${barangay.name}. The current code stops working immediately, so staff who have not registered yet will need the new code. Accounts that already exist are not affected.`,
    confirmLabel: 'Regenerate code',
    variant: 'primary',
    onConfirm: async () => {
      setMessage(null);
      try {
        const { data } = await api.post(`/superadmin/barangays/${barangay.id}/registration-code/regenerate`, { expected_updated_at: loaded.data?.updated_at });
        setDetail({ key, data, error: null });
        setRevealed(false);
        setCopy('');
        setHistoryVersion((value) => value + 1);
        setNotice({ type: 'success', text: `New registration code is active for ${barangay.name}. The previous code no longer works.` });
      } catch (error) {
        const status = error.response?.status;
        setMessage(status === 429 ? 'Too many regeneration attempts. Wait a minute, then try again.'
          : error.response?.data?.message ?? 'The code could not be regenerated. The current code is unchanged.');
        if (status === 409) setAttempt((value) => value + 1);
      }
    },
  });
  return <section className="sa-code-panel" aria-labelledby="sa-code-panel-title">
    <header>
      <div><h3 id="sa-code-panel-title" tabIndex={-1}>{barangay.name}</h3><p>{placeLabel(barangay)}{barangay.psgc_code && <> · PSGC {barangay.psgc_code}</>}</p></div>
      <StatusBadge value={state === 'active' || code ? 'Active' : state === 'none' ? 'No code yet' : 'Boundary required'} />
    </header>
    {state === 'boundary' ? <AdminEmptyState icon="pin" title="A boundary is needed first" description={hasPendingBoundary(barangay) ? 'A replacement boundary is waiting for review. Confirm or discard it before this barangay can use a registration code.' : 'This barangay has no live boundary outline yet. Add one before issuing a registration code.'}><button type="button" className="ghost-button" onClick={() => navigate(`/superadmin/barangays/${barangay.id}`)}>Open barangay boundary</button></AdminEmptyState>
      : state === 'none' && !code ? <AdminEmptyState icon="key" title="No registration code yet" description="Issue a code so staff can create accounts for this barangay. Share it only with the barangay's staff.">{loaded.error ? <p role="alert" className="sa-code-error">{loaded.error}</p> : null}<button type="button" className="primary-button" disabled={issuing} onClick={() => { setIssuing(true); setAttempt((value) => value + 1); }}>{issuing ? 'Issuing code…' : 'Issue registration code'}</button></AdminEmptyState>
      : loaded.error ? <AdminEmptyState icon="alert" title="Code unavailable" description={loaded.error}><button type="button" className="ghost-button" onClick={() => setAttempt((value) => value + 1)}>Retry</button></AdminEmptyState>
      : !code ? <p className="sa-code-loading" role="status">Loading registration code…</p>
      : <>
        <div className="sa-code-value">
          <span className="sa-code-label" id="sa-code-label">Staff registration code</span>
          <code id="sa-code-text" aria-labelledby="sa-code-label" aria-live="polite">{revealed ? code : <span aria-label="Code hidden">{'•'.repeat(code.length)}</span>}</code>
          <div className="sa-row-actions">
            <button type="button" className="ghost-button" aria-pressed={revealed} aria-controls="sa-code-text" onClick={() => setRevealed((value) => !value)}><Icon name={revealed ? 'eyeOff' : 'eye'} size={16} />{revealed ? 'Hide code' : 'Show code'}</button>
            <button type="button" className="primary-button" onClick={copyCode}><Icon name={copy === 'copied' ? 'checkCircle' : 'clipboard'} size={16} />{copy === 'copied' ? 'Copied' : 'Copy code'}</button>
          </div>
          <p className={`sa-code-feedback${copy === 'failed' ? ' is-error' : ''}`} role="status">{copy === 'copied' ? 'Code copied to the clipboard.' : copy === 'failed' ? 'Copy failed. Show the code and copy it manually.' : ''}</p>
        </div>
        <dl className="sa-detail-list sa-code-meta">
          <dt>Status</dt><dd>Active until regenerated</dd>
          <dt>Issued</dt><dd>{loaded.data.created_at ? formatDate(loaded.data.created_at) : 'Not recorded'}</dd>
          <dt>Last regenerated</dt><dd>{!loaded.data.updated_at ? 'Not recorded' : sameMoment(loaded.data.created_at, loaded.data.updated_at) ? 'Never regenerated' : formatDate(loaded.data.updated_at)}</dd>
        </dl>
        {message && <p className="notice error" role="alert">{message}</p>}
        <div className="sa-code-regenerate">
          <div><strong>Need to replace this code?</strong><p>Regenerate it if it was shared with the wrong people. The old code stops working immediately.</p></div>
          <button type="button" className="ghost-button" onClick={regenerate}><Icon name="undo" size={16} />Regenerate code</button>
        </div>
      </>}
    {(state !== 'boundary') && <CodeHistory barangay={barangay} version={historyVersion} />}
  </section>;
}

const CODE_PAGE_SIZES = [10, 25, 50];
const CODE_FILTER_KEYS = ['region', 'province', 'city', 'barangay'];
const placeLabel = (b) => [b.city_name, b.province_name, b.region_name].filter(Boolean).join(', ') || 'Location not specified';

// Fetches `path` whenever it (or `version`) changes; null skips the request.
function useAdminResource(path, version, fallbackError) {
  const [result, setResult] = useState({ key: '', data: null, error: null });
  const key = path ? `${path}#${version}` : '';
  useEffect(() => {
    if (!path) return;
    let current = true;
    api.get(path).then(({ data }) => { if (current) setResult({ key, data, error: null }); })
      .catch((error) => { if (current) setResult({ key, data: null, error: error.response?.data?.message ?? fallbackError }); });
    return () => { current = false; };
  }, [path, key, fallbackError]);
  const settled = Boolean(key) && result.key === key;
  return { data: settled ? result.data : null, error: settled ? result.error : null, loading: Boolean(key) && !settled };
}

export function RegistrationCodesTab({ initialBarangay, onRequestConfirmation, setNotice, onChanged }) {
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const value = (name) => params.get(name) ?? '';
  const [draft, setDraft] = useState(value('q'));
  const [version, setVersion] = useState(0);
  const [showLocation, setShowLocation] = useState(CODE_FILTER_KEYS.some((name) => params.get(name)));
  const panelRef = useRef(null);
  // Filters, page and selection live in the URL so Back, refresh and
  // shared links all return to the same view.
  const update = useCallback((changes, { keepPage = false, replace = true } = {}) => setParams((current) => {
    const next = new URLSearchParams(current);
    Object.entries(changes).forEach(([name, val]) => { if (val) next.set(name, String(val)); else next.delete(name); });
    if (!keepPage) next.delete('page');
    return next;
  }, { replace }), [setParams]);
  useEffect(() => {
    if (initialBarangay && !params.get('selected')) update({ selected: initialBarangay.id }, { keepPage: true });
  }, [initialBarangay, params, update]);
  useEffect(() => {
    if (draft.trim() === (params.get('q') ?? '')) return undefined;
    const timer = setTimeout(() => update({ q: draft.trim() }), 300);
    return () => clearTimeout(timer);
  }, [draft, params, update]);

  const region = value('region');
  const province = value('province');
  const city = value('city');
  const barangay = value('barangay');
  const page = Number(value('page')) || 1;
  const size = CODE_PAGE_SIZES.includes(Number(value('per_page'))) ? Number(value('per_page')) : 10;
  const selectedId = value('selected');
  const query = (entries) => new URLSearchParams(Object.entries(entries).filter(([, val]) => val)).toString();
  const locations = useAdminResource(`/superadmin/barangays/locations?${query({ region, province_id: province, city_id: city })}`, version, 'Location filters could not be loaded.');
  const results = useAdminResource(`/superadmin/barangays/search?${query({ q: value('q'), region, province_id: province, city_id: city, barangay_id: barangay, page, per_page: size })}`, version, 'Registered barangays could not be loaded.');
  const rows = results.data?.data ?? [];
  const listed = rows.find((b) => String(b.id) === selectedId);
  const lookup = useAdminResource(selectedId && !listed ? `/superadmin/barangays/search?barangay_id=${selectedId}` : null, version, 'This barangay could not be loaded.');
  const selected = listed ?? lookup.data?.data?.[0];
  const filtered = Boolean(value('q') || region || province || city || barangay);
  const options = locations.data ?? { regions: [], provinces: [], cities: [], barangays: [] };
  const clear = () => { setDraft(''); update({ q: '', region: '', province: '', city: '', barangay: '' }); };
  const choose = (b) => {
    update({ selected: b.id }, { keepPage: true, replace: false });
    // On narrow screens the panel sits below the list; bring it into view.
    requestAnimationFrame(() => { panelRef.current?.scrollIntoView({ block: 'start', behavior: 'smooth' }); panelRef.current?.querySelector('h3')?.focus({ preventScroll: true }); });
  };
  const changed = useCallback(() => { setVersion((current) => current + 1); onChanged(); }, [onChanged]);
  const lastPage = results.data?.last_page ?? 1;
  return <div className="superadmin-tab sa-codes-page">
    <section className="sa-management-card sa-code-finder" aria-labelledby="sa-code-finder-title">
      <header className="sa-code-head"><div><h2 id="sa-code-finder-title">Find a registered barangay</h2><p>Search nationwide by barangay, city or municipality, province, or PSGC code.</p></div></header>
      <div className="sa-code-finder-controls">
        <LocalSearchInput value={draft} onChange={setDraft} placeholder="Search barangays nationwide..." />
        <button type="button" className="ghost-button sa-location-toggle" aria-expanded={showLocation} aria-controls="sa-code-location" onClick={() => setShowLocation((open) => !open)}><Icon name="pin" size={15} />Filter by location<Icon name="chevronDown" size={14} /></button>
        {showLocation && <fieldset id="sa-code-location" className="sa-code-location">
          <legend>Location (optional)</legend>
          {locations.error && <p role="alert" className="sa-code-error">{locations.error} <button type="button" className="sa-text-button" onClick={() => setVersion((current) => current + 1)}>Retry</button></p>}
          <AdminFilterField label="Region"><SearchableSelect value={region} disabled={locations.loading && !locations.data} onChange={(event) => update({ region: event.target.value, province: '', city: '', barangay: '' })}><option value="">All regions</option>{options.regions.map((r) => <option key={r.code} value={r.code}>{r.name === r.long_name ? r.name : `${r.name} (${r.long_name})`}</option>)}</SearchableSelect></AdminFilterField>
          <AdminFilterField label="Province"><SearchableSelect value={province} onChange={(event) => update({ province: event.target.value, city: '', barangay: '' })}><option value="">All provinces</option>{options.provinces.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</SearchableSelect></AdminFilterField>
          <AdminFilterField label="City / Municipality"><SearchableSelect value={city} disabled={!province} onChange={(event) => update({ city: event.target.value, barangay: '' })}><option value="">{province ? 'All cities / municipalities' : 'Choose a province first'}</option>{options.cities.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</SearchableSelect></AdminFilterField>
          <AdminFilterField label="Barangay"><SearchableSelect value={barangay} disabled={!city} onChange={(event) => update({ barangay: event.target.value })}><option value="">{city ? 'All barangays' : 'Choose a city first'}</option>{options.barangays.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}</SearchableSelect></AdminFilterField>
        </fieldset>}
        <p className="sa-code-count" role="status">{results.loading ? 'Searching registered barangays…' : results.data ? `${results.data.total} registered ${results.data.total === 1 ? 'barangay' : 'barangays'}${filtered ? ' match' : ''}` : ''}{filtered && <> · <button type="button" className="sa-text-button" onClick={clear}>Clear filters</button></>}</p>
      </div>
      {results.error ? <AdminEmptyState icon="alert" title="Barangays unavailable" description={results.error}><button type="button" className="ghost-button" onClick={() => setVersion((current) => current + 1)}>Retry</button></AdminEmptyState>
        : results.loading && !rows.length ? <p className="sa-code-loading" role="status">Loading registered barangays…</p>
        : !rows.length ? (filtered
          ? <AdminEmptyState title="No matching barangays" description="Try another name or clear the filters. Only barangays registered on the platform are listed."><button type="button" className="ghost-button" onClick={clear}>Clear filters</button></AdminEmptyState>
          : <AdminEmptyState icon="pin" title="No registered barangays yet" description="Add a barangay in Barangay Management before issuing its registration code."><button type="button" className="ghost-button" onClick={() => navigate('/superadmin/barangays')}>Go to Barangay Management</button></AdminEmptyState>)
        : <>
          <ul className="sa-code-list" aria-busy={results.loading}>{rows.map((b) => { const state = codeState(b); return <li key={b.id}>
            <button type="button" aria-current={String(b.id) === selectedId ? 'true' : undefined} onClick={() => choose(b)}>
              <span><strong>{b.name}</strong><small>{placeLabel(b)}</small>{b.psgc_code && <small>PSGC {b.psgc_code}</small>}</span>
              <span className={`sa-code-chip ${state}`}>{CODE_STATE_LABELS[state]}</span>
            </button>
          </li>; })}</ul>
          <nav className="sa-pagination sa-code-pages" aria-label="Barangay result pages">
            <span>Page {Math.min(page, lastPage)} of {lastPage}</span>
            <div className="sa-page-buttons">
              <button type="button" disabled={page <= 1} aria-label="Previous page" onClick={() => update({ page: page - 1 }, { keepPage: true })}><Icon name="arrowLeft" size={16} /></button>
              <button type="button" disabled={page >= lastPage} aria-label="Next page" onClick={() => update({ page: page + 1 }, { keepPage: true })}><Icon name="chevronRight" size={16} /></button>
            </div>
            <label>Per page<SearchableSelect value={size} onChange={(event) => update({ per_page: event.target.value })}>{CODE_PAGE_SIZES.map((option) => <option key={option}>{option}</option>)}</SearchableSelect></label>
          </nav>
        </>}
    </section>
    <div className="sa-code-detail" ref={panelRef}>
      {selected ? <CodePanel key={selected.id} barangay={selected} onRequestConfirmation={onRequestConfirmation} setNotice={setNotice} onChanged={changed} />
        : selectedId && lookup.loading ? <section className="sa-management-card"><p className="sa-code-loading" role="status">Loading barangay…</p></section>
        : selectedId && (lookup.error || lookup.data) ? <section className="sa-management-card"><AdminEmptyState icon="alert" title="Barangay unavailable" description={lookup.error ?? 'This barangay is not registered on the platform.'} /></section>
        : <section className="sa-management-card"><AdminEmptyState icon="key" title="Select a barangay" description="Choose a registered barangay to view, copy, or regenerate its staff registration code." /></section>}
    </div>
  </div>;
}

export function ActivityLogTab() {
  const [filters, setFilters] = useState({ search: '', scope: 'all', page: 1, size: 25, attempt: 0 });
  const [result, setResult] = useState({ key: '', data: null, error: null });
  const [reviewing, setReviewing] = useState(null);
  const { search, scope, page, size, attempt } = filters;
  const path = `/superadmin/activity-log?${new URLSearchParams({ paginate: '1', q: search.trim(), scope, page: String(page), per_page: String(size) })}`;
  const key = `${path}:${attempt}`;
  const loading = result.key !== key;
  const updateFilters = (changes) => setFilters((current) => ({ ...current, page: 1, ...changes }));
  useEffect(() => {
    let current = true;
    const timer = setTimeout(() => {
      api.get(path).then(({ data }) => {
        if (!current) return;
        if (data.current_page > data.last_page) {
          setFilters((previous) => ({ ...previous, page: data.last_page }));
          return;
        }
        setResult({ key, data, error: null });
      }).catch(() => { if (current) setResult({ key, data: null, error: 'Could not load activity records. Please retry.' }); });
    }, 200);
    return () => { current = false; clearTimeout(timer); };
  }, [path, key]);
  const columns = [
    { label: 'Date & Time', className: 'sa-date-cell', render: (entry) => formatDate(entry.created_at) },
    { label: 'Actor', render: (entry) => entry.user?.name ?? 'Deleted account' },
    { label: 'Role', render: (entry) => entry.role ?? entry.user?.role ?? 'Not recorded' },
    { label: 'Action', render: (entry) => <StatusBadge value={entry.action} /> },
    { label: 'Module', key: 'module' },
    { label: 'Details', className: 'sa-log-details', render: (entry) => <><p className="sa-truncate">{entry.details}</p><button className="sa-text-button" type="button" onClick={() => setReviewing(entry)}>View full details</button></> },
  ];
  return <div className="superadmin-tab"><AdminManagementCard summary={<><strong>{loading ? 'Loading activity records…' : result.error ? 'Activity records unavailable' : `${result.data.total} activity records`}</strong><span>Account and platform administration · Times shown in Philippine time</span></>} toolbar={<><div className="sa-segments"><button type="button" aria-pressed={scope === 'all'} onClick={() => updateFilters({ scope: 'all' })}>Full Platform Log</button><button type="button" aria-pressed={scope === 'superadmin'} onClick={() => updateFilters({ scope: 'superadmin' })}>Super Admin Actions Only</button></div><LocalSearchInput value={search} onChange={(value) => updateFilters({ search: value.slice(0, 200) })} placeholder="Search platform activity..." /></>}>
    {loading ? <p role="status">Loading activity records…</p> : result.error ? <p role="alert">{result.error} <button type="button" className="ghost-button" onClick={() => updateFilters({ attempt: attempt + 1, page })}>Retry activity records</button></p> : <PaginatedTable rows={result.data.data} columns={columns} pagination={{ page, size, total: result.data.total }} onPageChange={(value) => updateFilters({ page: value })} onPageSizeChange={(value) => updateFilters({ size: value })} initialPageSize={25} pageSizeOptions={[25, 50, 100]} emptyMessage="No matching activity records" emptyDescription="Try another search or choose the full authorized platform log." />}
  </AdminManagementCard>{reviewing && <AdminActionDrawer title="Activity details" description={`Record ${reviewing.log_id ?? reviewing.id ?? ''}`} onClose={() => setReviewing(null)}><dl className="sa-detail-list"><dt>Date & time</dt><dd>{formatDate(reviewing.created_at)}</dd><dt>Actor</dt><dd>{reviewing.user?.name ?? 'Deleted account'}</dd><dt>Role at time of action</dt><dd>{reviewing.role ?? reviewing.user?.role ?? 'Not recorded'}</dd><dt>Action</dt><dd>{reviewing.action}</dd><dt>Module</dt><dd>{reviewing.module}</dd></dl><h3>Complete details</h3><p className="sa-full-text">{reviewing.details}</p></AdminActionDrawer>}</div>;
}
