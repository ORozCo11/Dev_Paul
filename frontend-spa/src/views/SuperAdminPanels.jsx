import SearchableSelect from '../components/SearchableSelect';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import api from '../api/axios';
import Icon from '../components/Icon';
import { AdminActionDrawer, AdminEmptyState, AdminFilterField, AdminIdentity, AdminManagementCard, LocalSearchInput, PaginatedTable, StatusBadge } from '../components/SuperAdminUI';
import { formatDate } from '../utils/adminDate';

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
        <div><h2 id="application-review-title" ref={reviewHeadingRef} tabIndex={-1}>Review application</h2><p>Check the applicant details before making a decision.</p></div>
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

export function RegistrationCodesTab({ barangays, initialBarangay, onRequestConfirmation, setNotice }) {
  const [provinces, setProvinces] = useState([]);
  const [cityLookup, setCityLookup] = useState({ key: '', rows: [], error: null });
  const [cityRetry, setCityRetry] = useState(0);
  const [provinceId, setProvinceId] = useState('');
  const [cityId, setCityId] = useState('');
  const [barangayId, setBarangayId] = useState(initialBarangay ? String(initialBarangay.id) : '');
  const [result, setResult] = useState({ code: null, updated: null, loading: false, error: null });
  const [retry, setRetry] = useState(0);
  const [barangayLookup, setBarangayLookup] = useState({ cityId: '', rows: [], error: null });
  const [barangayRetry, setBarangayRetry] = useState(0);
  const cityLookupKey = `${provinceId}:${cityRetry}`;
  const cities = cityLookup.key === cityLookupKey ? cityLookup.rows : [];
  const citiesLoading = Boolean(provinceId) && cityLookup.key !== cityLookupKey;
  const citiesError = cityLookup.key === cityLookupKey ? cityLookup.error : null;
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
    api.get('/cities', { params: { province_id: provinceId } }).then(({ data }) => { if (!current) return; setCityLookup({ key: cityLookupKey, rows: data, error: null }); if (initialBarangay?.province_name === provinces.find((p) => String(p.id) === provinceId)?.name) setCityId(String(data.find((city) => city.name === initialBarangay.city_name)?.id ?? '')); }).catch(() => { if (current) setCityLookup({ key: cityLookupKey, rows: [], error: 'Could not load cities. Please retry.' }); });
    return () => { current = false; };
  }, [provinceId, initialBarangay, provinces, cityLookupKey]);
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
  return <div className="superadmin-tab sa-codes"><AdminManagementCard toolbar={<><AdminFilterField label="Province"><SearchableSelect value={provinceId} onChange={(event) => { setProvinceId(event.target.value); setCityId(''); changeBarangay(''); }}><option value="">Select a province</option>{provinces.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</SearchableSelect></AdminFilterField><AdminFilterField label="City / Municipality"><SearchableSelect disabled={!provinceId || citiesLoading || Boolean(citiesError)} value={cityId} onChange={(event) => { setCityId(event.target.value); changeBarangay(''); }}><option value="">{citiesLoading ? 'Loading cities...' : citiesError ? 'Cities unavailable' : provinceId ? 'Select a city' : 'Select a province first'}</option>{cities.map((city) => <option key={city.id} value={city.id}>{city.name}</option>)}</SearchableSelect></AdminFilterField><AdminFilterField label="Barangay"><SearchableSelect disabled={!cityId || barangaysLoading || Boolean(barangaysError) || !options.length} value={barangayId} onChange={(event) => changeBarangay(event.target.value)}><option value="">{barangaysLoading ? 'Loading barangays...' : barangaysError ? 'Barangays unavailable' : cityId ? (options.length ? 'Select a barangay' : 'No barangays available') : 'Select a city first'}</option>{options.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}</SearchableSelect></AdminFilterField></>}>
    {citiesError && <p className="notice error" role="alert">{citiesError} <button type="button" className="ghost-button" onClick={() => setCityRetry((value) => value + 1)}>Retry cities</button></p>}
    {barangaysError && <p className="notice error" role="alert">{barangaysError} <button type="button" className="ghost-button" onClick={() => { setBarangayLookup({ cityId: '', rows: [], error: null }); setBarangayRetry((value) => value + 1); }}>Retry barangays</button></p>}
    {!barangayId ? <AdminEmptyState icon="key" title="Select a barangay" description="Choose a province, city, and barangay to view or provision its staff registration code." /> : result.error ? <AdminEmptyState icon="alert" title="Code unavailable" description={result.error}><button className="ghost-button" type="button" onClick={() => { setResult({ code: null, updated: null, loading: true, error: null }); setRetry(retry + 1); }}>Retry lookup</button></AdminEmptyState> : result.loading || !result.code ? <AdminEmptyState icon="key" title="Looking up registration code" description="Checking authorized access for this barangay." /> : <div className="sa-code-details"><div><h3>Registration Code Details</h3><p><strong>{selected?.name}</strong> · {[selectedCity?.name ?? selected?.city_name, selectedProvince?.name ?? selected?.province_name].filter(Boolean).join(', ')}</p></div><code>{result.code}</code>{result.updated && <p>Last updated {formatDate(result.updated)}</p>}<div className="sa-row-actions"><button className="primary-button" type="button" onClick={copyCode}><Icon name="clipboard" size={16} />Copy Code</button><button className="ghost-button sa-destructive" type="button" onClick={regenerate}>Regenerate Code</button></div></div>}
  </AdminManagementCard></div>;
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
