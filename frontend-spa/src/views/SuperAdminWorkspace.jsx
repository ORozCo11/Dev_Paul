import VMSLogo from '../components/VMSLogo';
import '../components/RoleDashboard.css';
import './SuperAdminWorkspace.css';
import { useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import 'leaflet/dist/leaflet.css';
import { MapContainer, Polygon, TileLayer } from 'react-leaflet';
import api from '../api/axios';
import Icon from '../components/Icon';
import WorkspaceFooter from '../components/WorkspaceFooter';
import { AdminActionDrawer, AdminConfirmDialog, AdminFilterField, AdminManagementCard, LocalSearchInput, PaginatedTable, StatusBadge } from '../components/SuperAdminUI';
import { formatDate } from '../utils/adminDate';
import { ActivityLogTab, ConcernReportsTab, PendingApprovalsTab, PlatformOverview, RegistrationCodesTab, UsersTab } from './SuperAdminPanels';
import { AuthContext } from '../context/AuthContextObject';
import { geoJsonToRings } from '../utils/boundary';

// Kept separate from every other role's landing route — a Super Admin never
// lands on the fleet-oriented Workspace.jsx shell, since RestrictSuperAdminScope
// (backend) blocks that account from all fleet-data endpoints anyway.
const roleRoutes = {
  Admin: '/admin',
  Custodian: '/custodian',
  'Maintenance Personnel': '/maintenance',
};



// Flat platform navigation preserves the existing module keys.
const TABS = [
  ['dashboard', 'Platform Overview'], ['barangays', 'Barangay Management'], ['pending', 'Pending Approvals'],
  ['users', 'Users'], ['codes', 'Registration Codes'], ['concerns', 'Concern Reports'], ['activity', 'Activity Log'],
];

const TAB_DESCRIPTIONS = {
  dashboard: 'Manage barangays, user accounts, and platform administration.',
  barangays: 'Manage registered barangays and their platform configuration.',
  pending: 'Review account requests and keep every barangay properly staffed.',
  users: 'Manage platform users, roles, and account availability by location.',
  codes: 'View and regenerate staff registration codes for registered barangays.',
  concerns: 'Track and resolve reports that need platform-level attention.',
  activity: 'A chronological record of platform and Super Admin activity.',
  settings: 'Manage your Super Admin account settings and security.',
};

// Only the notification types a Super Admin can actually receive need an
// entry here — everything else falls back to the title-sniffing guess below,
// same as the main Workspace's bell.
const NOTIFICATION_STYLE_BY_TYPE = {
  pending_admin_approval: 'warning',
};

function legacyNotificationStyleFromTitle(title) {
  if (/confirmed|approved|completed|verified|done/i.test(title)) return 'success';
  if (/reopened|deferred|rejected|sent back/i.test(title)) return 'warning';
  if (/required|assigned|logged|awaiting|pending/i.test(title)) return 'info';
  if (/failed|error/i.test(title)) return 'error';
  return 'info';
}

function getNotificationStyle(notification) {
  return NOTIFICATION_STYLE_BY_TYPE[notification.type] ?? legacyNotificationStyleFromTitle(notification.title);
}

function UsersIcon() {
  return (
    <svg className="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
    </svg>
  );
}

const TAB_ICONS = {
  barangays: <Icon name="pin" size={18} className="nav-icon" />,
  dashboard: <Icon name="grid" size={18} className="nav-icon" />,
  pending: <Icon name="clipboard" size={18} className="nav-icon" />,
  users: <UsersIcon />,
  codes: <Icon name="key" size={18} className="nav-icon" />,
  concerns: <Icon name="mail" size={18} className="nav-icon" />,
  activity: <Icon name="clipboard" size={18} className="nav-icon" />,
  settings: <Icon name="key" size={18} className="nav-icon" />,
};

function ProfileMenu({ user, open, setOpen, onOpenSettings, onLogout }) {
  const initials = user.name ? user.name.split(' ').map((n) => n[0]).join('').slice(0, 2).toUpperCase() : 'U';
  return (
    <>
      <button className="profile-menu-trigger" type="button" onClick={() => setOpen((v) => !v)} aria-label="Account menu">
        <span className="profile-menu-avatar">{initials}</span>
      </button>
      {open && (
        <div className="profile-menu-dropdown">
          <div className="profile-menu-user">
            <span className="profile-menu-avatar">{initials}</span>
            <div className="profile-menu-user-info">
              <span className="profile-menu-name">{user.name}</span>
              <span className="profile-menu-role">{user.role}</span>
            </div>
          </div>
          <div className="profile-menu-divider" />
          <button className="profile-menu-item" type="button" onClick={() => { onOpenSettings(); setOpen(false); }}>
            <Icon name="key" size={15} /> My Settings
          </button>
          <div className="profile-menu-divider" />
          <button className="profile-menu-item profile-menu-item-danger" type="button" onClick={onLogout}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" style={{ width: 15, height: 15 }}>
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <polyline points="16 17 21 12 16 7" />
              <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
            Logout
          </button>
        </div>
      )}
    </>
  );
}

function BarangayManagementTab({ barangays, users, onPromote, onAddedBarangay, onRetryBoundary, onConfirmBoundary, onDiscardBoundary, initialFilter, showAddForm, onCloseAdd, onRegistrationCode, onRequestConfirmation }) {
  const [activeFilter, setActiveFilter] = useState(initialFilter || 'all');
  const [search, setSearch] = useState('');
  const [provinceFilter, setProvinceFilter] = useState('');
  const [cityFilter, setCityFilter] = useState('');
  const [boundaryFilter, setBoundaryFilter] = useState('');
  const [managingId, setManagingId] = useState(null);
  const [pickedUserId, setPickedUserId] = useState('');
  const [reviewingBoundary, setReviewingBoundary] = useState(null);
  const [retryingId, setRetryingId] = useState(null);
  const orphaned = barangays.filter((b) => !b.has_active_admin);
  const pending = barangays.filter((b) => b.boundary_status === 'needs_review');
  const provinceOptions = [...new Set(barangays.map((b) => b.province_name).filter(Boolean))].sort();
  const cities = [...new Set(barangays.filter((b) => !provinceFilter || b.province_name === provinceFilter).map((b) => b.city_name).filter(Boolean))].sort();
  const boundaryStatus = (b) => b.boundary_status === 'verified' ? 'Verified' : b.boundary_status === 'needs_review' ? 'Needs Review' : 'Not Found';
  const filtered = barangays.filter((b) => (activeFilter === 'all' || activeFilter === 'active' && b.has_active_admin || activeFilter === 'orphaned' && !b.has_active_admin || activeFilter === 'boundary_review' && b.boundary_status === 'needs_review') && (!provinceFilter || b.province_name === provinceFilter) && (!cityFilter || b.city_name === cityFilter) && (!boundaryFilter || boundaryStatus(b) === boundaryFilter) && [b.name, b.city_name, b.province_name].join(' ').toLowerCase().includes(search.trim().toLowerCase()));
  const managing = barangays.find((b) => b.id === managingId);
  const candidates = managing ? users.filter((account) => account.barangay_id === managing.id) : [];
  const retryBoundary = async (b) => { setRetryingId(b.id); try { await onRetryBoundary(b); } finally { setRetryingId(null); } };
  const columns = [
    { label: 'Barangay', render: (b) => <strong>{b.name}</strong> },
    { label: 'City / Province', render: (b) => <div className="sa-stacked"><strong>{b.city_name || 'Not specified'}</strong><span>{b.province_name || 'Not specified'}</span></div> },
    { label: 'Staff Count', key: 'staff_count' },
    { label: 'Administrator Status', render: (b) => <div className="sa-stacked"><StatusBadge value={b.has_active_admin ? 'Active Admin' : 'No Active Admin'} />{b.sole_active_admin && <span className="sa-warning"><Icon name="alert" size={12} />Only one active Admin</span>}</div> },
    { label: 'Boundary Status', render: (b) => <StatusBadge value={boundaryStatus(b)} /> },
    { label: 'Actions', render: (b) => <button type="button" className="ghost-button" onClick={() => { setManagingId(b.id); setPickedUserId(''); }}>View / Manage</button> },
  ];
  return <div className="superadmin-tab">
    {showAddForm && <AddBarangayForm onAdded={(created) => { onAddedBarangay(created); onCloseAdd(); }} onCancel={onCloseAdd} />}
    <AdminManagementCard summary={<div className="sa-segments">{[['all', 'All Barangays', barangays.length], ['active', 'Active Admin', barangays.length - orphaned.length], ['orphaned', 'Orphaned', orphaned.length], ['boundary_review', 'Needs Boundary Review', pending.length]].map(([key, label, count]) => <button key={key} type="button" aria-pressed={activeFilter === key} onClick={() => setActiveFilter(key)}>{label}<span>{count}</span></button>)}</div>} toolbar={<>
      <LocalSearchInput value={search} onChange={setSearch} placeholder="Search barangay, city, or province..." />
      <AdminFilterField label="Province"><select aria-label="Filter by province" value={provinceFilter} onChange={(event) => { setProvinceFilter(event.target.value); setCityFilter(''); }}><option value="">All Provinces</option>{provinceOptions.map((province) => <option key={province}>{province}</option>)}</select></AdminFilterField>
      <AdminFilterField label="City"><select aria-label="Filter by city" value={cityFilter} onChange={(event) => setCityFilter(event.target.value)}><option value="">All Cities</option>{cities.map((city) => <option key={city}>{city}</option>)}</select></AdminFilterField>
      <AdminFilterField label="Boundary status"><select aria-label="Boundary status" value={boundaryFilter} onChange={(event) => setBoundaryFilter(event.target.value)}><option value="">All Boundaries</option>{['Verified', 'Needs Review', 'Not Found'].map((status) => <option key={status}>{status}</option>)}</select></AdminFilterField>
    </>}><PaginatedTable rows={filtered} columns={columns} emptyIcon="pin" emptyMessage="No barangays found" emptyDescription="Try another search or adjust the location and status filters." /></AdminManagementCard>
    {managing && <AdminActionDrawer title={managing.name} description={[managing.city_name, managing.province_name].filter(Boolean).join(', ')} onClose={() => setManagingId(null)}>
      <dl className="sa-detail-list"><dt>Registered staff</dt><dd>{managing.staff_count}</dd><dt>Administrator status</dt><dd><StatusBadge value={managing.has_active_admin ? 'Active Admin' : 'No Active Admin'} /></dd><dt>Boundary status</dt><dd><StatusBadge value={boundaryStatus(managing)} /></dd><dt>Registration code</dt><dd>{managing.has_registration_code ? 'On file' : 'Not generated yet'}</dd></dl>
      <section className="sa-detail-section"><h3>Registration code</h3><p>View or regenerate this barangay's staff registration code.</p><button type="button" className="ghost-button" onClick={() => onRegistrationCode(managing)}><Icon name="key" size={15} />Manage Registration Code</button></section>
      <section className="sa-detail-section"><h3>Boundary configuration</h3>{managing.boundary_status === 'needs_review' ? <><p>A candidate boundary needs verification before it becomes official.</p><button type="button" className="primary-button" onClick={() => { setReviewingBoundary(managing); setManagingId(null); }}>Review Boundary</button></> : managing.boundary_status === 'verified' ? <p>A verified boundary is on file.</p> : <><p>No matching boundary is on file. Retry the supported lookup.</p><button type="button" className="ghost-button" disabled={retryingId === managing.id} onClick={() => retryBoundary(managing)}>{retryingId === managing.id ? 'Checking…' : 'Retry Boundary Lookup'}</button></>}</section>
      {!managing.has_active_admin && <section className="sa-detail-section"><h3>Recover Administrator</h3>{!candidates.length ? <p>No registered staff are available to promote. Share this barangay's registration code to begin onboarding.</p> : <><p>Promote an existing staff member to Admin. An inactive account will also be activated.</p><AdminFilterField label="Staff member"><select value={pickedUserId} onChange={(event) => setPickedUserId(event.target.value)}><option value="">Select a staff member</option>{candidates.map((account) => <option key={account.id} value={account.id}>{account.name} · {account.role}{account.is_active ? '' : ' (inactive)'}</option>)}</select></AdminFilterField><button type="button" className="primary-button" disabled={!pickedUserId} onClick={() => { const target = candidates.find((account) => String(account.id) === pickedUserId); onRequestConfirmation({ title: 'Recover Administrator', message: `Promote ${target.name} to Admin for ${managing.name}${target.is_active ? '' : ' and activate their account'}?`, confirmLabel: 'Promote to Admin', variant: 'primary', onConfirm: async () => { const success = await onPromote(target); if (success) setManagingId(null); } }); }}>Promote to Admin</button></>}</section>}
    </AdminActionDrawer>}
    {reviewingBoundary && <BoundaryReviewModal barangay={reviewingBoundary} onClose={() => setReviewingBoundary(null)} onConfirm={async () => { if (await onConfirmBoundary(reviewingBoundary)) setReviewingBoundary(null); }} onDiscard={async () => { if (await onDiscardBoundary(reviewingBoundary)) setReviewingBoundary(null); }} />}
  </div>;
}

function BoundaryReviewModal({ barangay, onClose, onConfirm, onDiscard }) {
  const [busy, setBusy] = useState(false);
  const rings = useMemo(() => geoJsonToRings(barangay.pending_boundary), [barangay.pending_boundary]);
  const allPoints = rings.flat();
  const center = allPoints.length
    ? [allPoints.reduce((s, p) => s + p[0], 0) / allPoints.length, allPoints.reduce((s, p) => s + p[1], 0) / allPoints.length]
    : [10.3, 123.9];

  const run = async (fn) => {
    setBusy(true);
    try { await fn(); } finally { setBusy(false); }
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-box boundary-review-dialog" role="dialog" aria-modal="true" aria-label="Review barangay boundary" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h3>Review Boundary — {barangay.name}</h3>
          <button className="modal-close-btn" onClick={onClose} type="button" aria-label="Close"><Icon name="close" size={18} /></button>
        </div>
        <div className="modal-body">
          <p className="muted" style={{ margin: '0 0 10px' }}>
            Matched via OpenStreetMap — this is NOT yet the barangay's official boundary. Confirm it only if the shape below actually looks like {barangay.name}.
          </p>
          {rings.length === 0 ? (
            <p className="notice error">This candidate's geometry couldn't be rendered — discard it and try again later.</p>
          ) : (
            <div style={{ height: 280, borderRadius: 8, overflow: 'hidden', border: '1px solid #e2e8f0' }}>
              <MapContainer center={center} zoom={14} style={{ height: '100%', width: '100%' }} scrollWheelZoom={false}>
                <TileLayer attribution="&copy; OpenStreetMap contributors" url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
                {rings.map((ring, i) => (
                  <Polygon key={i} positions={ring} pathOptions={{ color: '#d97706', fillColor: '#fbbf24', fillOpacity: 0.3 }} />
                ))}
              </MapContainer>
            </div>
          )}
          <div className="form-actions" style={{ marginTop: 12 }}>
            <button type="button" className="ghost-button" disabled={busy} onClick={() => run(onDiscard)}>Discard</button>
            <button type="button" className="success-button" disabled={busy || rings.length === 0} onClick={() => run(onConfirm)}>
              {busy ? 'Confirming…' : 'Confirm Boundary'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

// Nationwide location cascade for adding a registered barangay.
function AddBarangayForm({ onAdded, onCancel }) {
  const [provinces, setProvinces] = useState([]);
  const [cities, setCities] = useState([]);
  const [provinceId, setProvinceId] = useState('');
  const [cityId, setCityId] = useState('');
  const [name, setName] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    api.get('/provinces').then((res) => setProvinces(res.data)).catch(() => {});
  }, []);

  useEffect(() => {
    if (!provinceId) return;
    let current = true;
    api.get('/cities', { params: { province_id: provinceId } }).then((res) => { if (current) setCities(res.data); }).catch(() => { if (current) setCities([]); });
    return () => { current = false; };
  }, [provinceId]);

  const submit = async (e) => {
    e.preventDefault();
    if (!cityId || !name.trim()) return;
    setSaving(true);
    setError(null);
    try {
      const res = await api.post('/superadmin/barangays', { city_id: cityId, name: name.trim() });
      onAdded(res.data);
    } catch (err) {
      setError(err.response?.data?.message ?? 'Something went wrong — please try again.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <form className="location-form-panel superadmin-panel-spaced" onSubmit={submit}>
      <h3>Add Barangay</h3>
      <p className="muted superadmin-muted-block">
        Creates the barangay immediately — a registration code can be generated for it right after, from Registration Codes.
      </p>
      {error && <p className="muted" style={{ color: '#dc2626' }}>{error}</p>}
      <div className="superadmin-inline-row">
        <select aria-label="New barangay province" value={provinceId} onChange={(e) => { setProvinceId(e.target.value); setCityId(''); setCities([]); }} required>
          <option value="">Province</option>
          {provinces.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
        </select>
        <select aria-label="New barangay city" value={cityId} onChange={(e) => setCityId(e.target.value)} disabled={!provinceId} required>
          <option value="">City / Municipality</option>
          {cities.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
        <input
          type="text"
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder="Barangay name"
          aria-label="Barangay name"
          disabled={!cityId}
          required
        />
        <button type="button" className="ghost-button" onClick={onCancel}>Cancel</button>
        <button type="submit" className="primary-button" disabled={saving || !cityId || !name.trim()}>
          {saving ? 'Adding…' : 'Add'}
        </button>
      </div>
    </form>
  );
}

function ImpersonateInline({ candidates, onImpersonate }) {
  const [provinceKey, setProvinceKey] = useState('');
  const [cityKey, setCityKey] = useState('');
  const [barangayKey, setBarangayKey] = useState('');
  const [userId, setUserId] = useState('');
  // Phase A5 — a written reason is required before impersonating anyone
  // (support/recovery only, never idle browsing), and appears in both the
  // target barangay's Activity Log and the Super Admin's own account log.
  const [reason, setReason] = useState('');

  const eligible = candidates.filter((c) => c.role !== 'Super Admin');
  const provinces = [...new Set(eligible.map((c) => c.province_name ?? 'Unassigned'))].sort();
  const inProvince = eligible.filter((c) => (c.province_name ?? 'Unassigned') === provinceKey);
  const cities = [...new Set(inProvince.map((c) => c.city_name ?? 'Unassigned'))].sort();
  const inCity = inProvince.filter((c) => (c.city_name ?? 'Unassigned') === cityKey);
  const barangayOptions = [...new Map(inCity.map((c) => [String(c.barangay_id ?? 'unassigned'), c.barangay_name ?? 'Unassigned'])).entries()];
  const staffOptions = inCity.filter((c) => String(c.barangay_id ?? 'unassigned') === barangayKey);

  return (
    <>
      <div className="map-boundary-selector" title="Select Province, City, Barangay, then Staff.">
        <select
          aria-label="Impersonate province"
          value={provinceKey}
          onChange={(e) => { setProvinceKey(e.target.value); setCityKey(''); setBarangayKey(''); setUserId(''); setReason(''); }}
        >
          <option value="">Province</option>
          {provinces.map((p) => <option key={p} value={p}>{p}</option>)}
        </select>
        <select aria-label="Impersonate city" value={cityKey} disabled={!provinceKey} onChange={(e) => { setCityKey(e.target.value); setBarangayKey(''); setUserId(''); setReason(''); }}>
          <option value="">City / Municipality</option>
          {cities.map((city) => <option key={city} value={city}>{city}</option>)}
        </select>
        <select
          aria-label="Impersonate barangay"
          value={barangayKey}
          disabled={!cityKey}
          onChange={(e) => { setBarangayKey(e.target.value); setUserId(''); }}
        >
          <option value="">Barangay</option>
          {barangayOptions.map(([id, name]) => <option key={id} value={id}>{name}</option>)}
        </select>
      </div>
      <div className="dev-impersonate" title="Impersonate a staff account for support or account recovery. Read-only, expires in 30 minutes, and is logged.">
        <select
          aria-label="Impersonate staff"
          value={userId}
          disabled={!barangayKey}
          onChange={(e) => { setUserId(e.target.value); setReason(''); }}
        >
          <option value="">Staff</option>
          {staffOptions.map((u) => (
            <option key={u.id} value={u.id} disabled={!u.is_active}>{u.name} · {u.role}{u.is_active ? '' : ' (inactive)'}</option>
          ))}
        </select>
        {userId && (
          <input
            type="text"
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder="Reason (required)"
            aria-label="Reason for impersonating"
            style={{ width: 180 }}
          />
        )}
        <button
          type="button"
          disabled={!userId || !reason.trim()}
          onClick={() => { onImpersonate(userId, reason.trim()); setReason(''); }}
        >
          Impersonate
        </button>
      </div>
    </>
  );
}

function SettingsTab({ setNotice }) {
  const [oldPassword, setOldPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const submit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.put('/profile/password', {
        old_password: oldPassword,
        new_password: newPassword,
        new_password_confirmation: confirmPassword,
      });
      setNotice({ type: 'success', text: 'Password updated.' });
      setOldPassword(''); setNewPassword(''); setConfirmPassword('');
    } catch (error) {
      setNotice({ type: 'error', text: error.response?.data?.message ?? 'Could not update password.' });
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="superadmin-tab superadmin-settings-tab">
      <div className="panel-header-bar">
        <h3>Change password</h3>
      </div>
      <form className="smart-form" onSubmit={submit} style={{ maxWidth: 420 }}>
        <label>
          <span>Current Password</span>
          <input type="password" value={oldPassword} onChange={(e) => setOldPassword(e.target.value)} required autoComplete="off" />
        </label>
        <label>
          <span>New Password</span>
          <input type="password" value={newPassword} onChange={(e) => setNewPassword(e.target.value)} required minLength={8} autoComplete="new-password" />
        </label>
        <label>
          <span>Confirm New Password</span>
          <input type="password" value={confirmPassword} onChange={(e) => setConfirmPassword(e.target.value)} required minLength={8} autoComplete="new-password" />
        </label>
        <div className="form-actions">
          <button className="primary-button" type="submit" disabled={submitting}>{submitting ? 'Saving…' : 'Update Password'}</button>
        </div>
      </form>
    </div>
  );
}

export default function SuperAdminWorkspace() {
  const { user, logout } = useContext(AuthContext);
  const shellRef = useRef(null);
  useEffect(() => {
    const shell = shellRef.current;
    const header = shell?.querySelector('.topbar');
    if (!header) return;
    const measure = () => shell.style.setProperty('--topbar-height', `${Math.ceil(header.getBoundingClientRect().height)}px`);
    const observer = new ResizeObserver(measure);
    observer.observe(header);
    measure();
    return () => observer.disconnect();
  }, [user?.id]);
  const [activeTab, setActiveTab] = useState('dashboard');
  const [barangayFilter, setBarangayFilter] = useState('all');
  const [codeBarangay, setCodeBarangay] = useState(null);
  const [showAddBarangay, setShowAddBarangay] = useState(false);
  const navigateTab = (tab, filter) => { setActiveTab(tab); setBarangayFilter(filter || 'all'); setShowAddBarangay(false); if (tab === 'codes') setCodeBarangay(null); window.scrollTo({ top: 0, behavior: 'instant' }); };
  // Always starts expanded — a collapsed sidebar should only ever be a
  // deliberate, in-session choice (the toggle button), never the default a
  // returning user lands on.
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false);
  const [theme, setTheme] = useState(() => {
    try { return localStorage.getItem('theme') || 'light'; } catch { return 'light'; }
  });
  const [showProfileMenu, setShowProfileMenu] = useState(false);
  const [notice, setNotice] = useState(null);
  const [confirmDialog, setConfirmDialog] = useState(null);
  const [confirmBusy, setConfirmBusy] = useState(false);
  const handleConfirmDialog = async () => {
    if (!confirmDialog?.onConfirm || confirmBusy) return;
    setConfirmBusy(true);
    try {
      await confirmDialog.onConfirm();
      setConfirmDialog(null);
    } catch (error) {
      setNotice({ type: 'error', text: error.response?.data?.message ?? 'Something went wrong. Please try again.' });
    } finally {
      setConfirmBusy(false);
    }
  };

  const [barangays, setBarangays] = useState([]);
  const [users, setUsers] = useState([]);
  const [pendingApprovals, setPendingApprovals] = useState([]);
  const [candidates, setCandidates] = useState([]);
  const [concernReports, setConcernReports] = useState([]);
  const [activityLog, setActivityLog] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);

  const [notifications, setNotifications] = useState([]);
  const [showNotifications, setShowNotifications] = useState(false);
  const notificationsRef = useRef(null);
  const profileMenuRef = useRef(null);

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem('theme', theme); } catch { /* ignore */ }
  }, [theme]);

  useEffect(() => {
    if (!notice) return;
    const t = setTimeout(() => setNotice(null), 4000);
    return () => clearTimeout(t);
  }, [notice]);

  // `silent` skips the `loading` flip — every tab's content-body unmounts
  // while loading is true (see the ternary below), which wipes any local
  // form state a tab was mid-filling-out (province/city/barangay picks,
  // search text, ...). Fine for the very first mount, where there's nothing
  // to lose yet; wrong for a post-mutation refresh, which should update the
  // data in place without blanking whatever the Super Admin was doing.
  const loadAll = useCallback(async (silent = false) => {
    try {
      const [b, u, p, c, r, a] = await Promise.all([
        api.get('/superadmin/barangays'),
        api.get('/superadmin/users'),
        api.get('/superadmin/pending-approvals'),
        api.get('/impersonate/candidates'),
        api.get('/superadmin/concern-reports'),
        api.get('/superadmin/activity-log'),
      ]);
      setBarangays(b.data);
      setUsers(u.data);
      setPendingApprovals(p.data);
      setCandidates(c.data);
      setConcernReports(r.data);
      setActivityLog(a.data);
      setLoadError(false);
    } catch {
      if (!silent) setLoadError(true);
      setNotice({ type: 'error', text: 'Could not load Super Admin data.' });
    } finally {
      if (!silent) setLoading(false);
    }
  }, []);

  useEffect(() => {
    // loadAll awaits the API batch before updating React state.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    loadAll();
  }, [loadAll]);

  const loadNotifications = useCallback(async () => {
    try {
      const res = await api.get('/notifications');
      setNotifications(res.data);
    } catch { /* ignore — bell just stays empty until the next poll */ }
  }, []);

  const markNotificationAsRead = async (id) => {
    try {
      await api.put(`/notifications/${id}/read`);
      await loadNotifications();
    } catch { /* ignore */ }
  };

  const markAllNotificationsAsRead = async () => {
    try {
      await api.put('/notifications/read-all');
      await loadNotifications();
    } catch { /* ignore */ }
  };

  const deleteNotification = async (id) => {
    try {
      await api.delete(`/notifications/${id}`);
      await loadNotifications();
    } catch { /* ignore */ }
  };

  useEffect(() => {
    api.get('/notifications').then(({ data }) => setNotifications(data)).catch(() => {});
    const interval = setInterval(() => {
      loadNotifications().catch(() => {});
    }, 10000);
    return () => clearInterval(interval);
  }, [loadNotifications]);

  useEffect(() => {
    function handleClickOutside(event) {
      if (notificationsRef.current && !notificationsRef.current.contains(event.target)) {
        setShowNotifications(false);
      }
      if (profileMenuRef.current && !profileMenuRef.current.contains(event.target)) {
        setShowProfileMenu(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const unreadCount = notifications.filter((n) => !n.read_at).length;

  // Computed once here and handed to both DashboardTab and UsersTab so the
  // "Total Users"/"Pending Registrations" counts on the dashboard can never
  // drift from the "All Users" tab's own count badge — both read the same
  // Super-Admin-excluded list instead of each filtering independently.
  const visibleUsers = useMemo(() => users.filter((u) => u.role !== 'Super Admin'), [users]);

  const promoteToAdmin = async (target) => {
    if (!target) return;
    try {
      await api.put(`/superadmin/users/${target.id}/role`, { role: 'Admin' });
      if (!target.is_active) {
        await api.put(`/superadmin/users/${target.id}/activate`);
      }
      setNotice({ type: 'success', text: `${target.name} is now this barangay's Admin.` });
      await loadAll(true);
      return true;
    } catch (error) {
      setNotice({ type: 'error', text: error.response?.data?.message ?? 'Could not promote this user.' });
      return false;
    }
  };

  // AddBarangayForm already POSTs and hands back the created row itself —
  // this just refreshes the shared datasets (same loadAll() convention
  // every other mutation here uses) and surfaces the success notice.
  const addBarangay = async (created) => {
    setNotice({ type: 'success', text: `${created.name} (${created.city_name}) added.` });
    await loadAll(true);
  };

  // Only meaningful for a barangay without a verified boundary yet — re-runs
  // the same OSM lookup storeBarangay() tried on creation, for the (common)
  // case where nothing trustworthy was found the first time. Never
  // overwrites a boundary that's already verified (see
  // refreshBarangayBoundary()). A match found here lands as a candidate
  // awaiting review (boundary_status 'needs_review'), not an immediate save.
  const retryBoundary = async (barangay) => {
    try {
      const res = await api.post(`/superadmin/barangays/${barangay.id}/boundary/refresh`);
      setNotice(res.data.boundary_status === 'needs_review'
        ? { type: 'success', text: `Found a candidate boundary for ${barangay.name} — review it before confirming.` }
        : res.data.boundary_status === 'verified'
          ? { type: 'success', text: `${barangay.name} already has a verified boundary.` }
          : { type: 'error', text: `Still no boundary match for ${barangay.name} — try again later, or add it manually.` });
      await loadAll(true);
    } catch (error) {
      setNotice({ type: 'error', text: error.response?.data?.message ?? 'Could not retry that boundary lookup.' });
    }
  };

  const confirmBoundary = async (barangay) => {
    try {
      await api.post(`/superadmin/barangays/${barangay.id}/boundary/confirm`);
      setNotice({ type: 'success', text: `Boundary confirmed for ${barangay.name}.` });
      await loadAll(true);
      return true;
    } catch (error) {
      setNotice({ type: 'error', text: error.response?.data?.message ?? 'Could not confirm that boundary.' });
      return false;
    }
  };

  const discardBoundary = async (barangay) => {
    try {
      await api.delete(`/superadmin/barangays/${barangay.id}/boundary/pending`);
      setNotice({ type: 'success', text: `Candidate boundary discarded for ${barangay.name}.` });
      await loadAll(true);
      return true;
    } catch (error) {
      setNotice({ type: 'error', text: error.response?.data?.message ?? 'Could not discard that candidate boundary.' });
      return false;
    }
  };

  const changeRole = async (targetUser, role) => {
    if (role === targetUser.role) return;
    try {
      await api.put(`/superadmin/users/${targetUser.id}/role`, { role });
      setNotice({ type: 'success', text: `${targetUser.name}'s role is now ${role}.` });
      await loadAll(true);
      return true;
    } catch (error) {
      setNotice({ type: 'error', text: error.response?.data?.message ?? 'Could not change that role.' });
      return false;
    }
  };

  const toggleActive = async (targetUser) => {
    try {
      await api.put(`/superadmin/users/${targetUser.id}/${targetUser.is_active ? 'deactivate' : 'activate'}`);
      setNotice({ type: 'success', text: `${targetUser.name} is now ${targetUser.is_active ? 'inactive' : 'active'}.` });
      await loadAll(true);
      return true;
    } catch (error) {
      setNotice({ type: 'error', text: error.response?.data?.message ?? 'Could not update that account.' });
      return false;
    }
  };

  const approvePending = async (approval) => {
    try {
      await api.put(`/superadmin/users/${approval.id}/activate`);
      setNotice({ type: 'success', text: `${approval.name} is approved and can now sign in.` });
      await loadAll(true);
      return true;
    } catch (error) {
      setNotice({ type: 'error', text: error.response?.data?.message ?? 'Could not approve this account.' });
      return false;
    }
  };

  const rejectPending = async (approval) => {
    try {
      await api.delete(`/superadmin/users/${approval.id}/reject`);
      setNotice({ type: 'success', text: `${approval.name}'s registration was rejected.` });
      await loadAll(true);
      return true;
    } catch (error) {
      setNotice({ type: 'error', text: error.response?.data?.message ?? 'Could not reject this account.' });
      return false;
    }
  };

  const resolveConcern = async (report) => {
    try {
      await api.put(`/superadmin/concern-reports/${report.id}/resolve`);
      setNotice({ type: 'success', text: 'Concern report marked resolved.' });
      await loadAll(true);
      return true;
    } catch (error) {
      setNotice({ type: 'error', text: error.response?.data?.message ?? 'Could not update that report.' });
      return false;
    }
  };

  const reopenConcern = async (report) => {
    try {
      await api.put(`/superadmin/concern-reports/${report.id}/reopen`);
      setNotice({ type: 'success', text: 'Concern report reopened.' });
      await loadAll(true);
      return true;
    } catch (error) {
      setNotice({ type: 'error', text: error.response?.data?.message ?? 'Could not update that report.' });
      return false;
    }
  };

  const doImpersonate = async (userId, reason) => {
    try {
      const res = await api.post(`/impersonate/${userId}`, { reason });
      // Stash the Super Admin's own token before it's overwritten below —
      // otherwise there was no way back into it short of logging out and
      // back in. Workspace.jsx's topbar reads these same keys to show a
      // "Return to..." control on whichever dashboard the impersonated
      // account lands on.
      const ownToken = localStorage.getItem('token') || sessionStorage.getItem('token');
      if (ownToken && !localStorage.getItem('impersonator_token')) {
        localStorage.setItem('impersonator_storage', localStorage.getItem('token') ? 'local' : 'session');
        localStorage.setItem('impersonator_token', ownToken);
        localStorage.setItem('impersonator_name', user.name);
        localStorage.setItem('impersonator_role', user.role);
      }
      localStorage.setItem('token', res.data.access_token);
      sessionStorage.removeItem('token');
      window.location.assign(roleRoutes[res.data.user?.role] ?? '/admin');
    } catch (error) {
      setNotice({ type: 'error', text: error.response?.data?.message ?? 'Could not impersonate that account.' });
    }
  };

  const deleteConcern = async (report) => {
    try {
      await api.delete(`/superadmin/concern-reports/${report.id}`);
      setNotice({ type: 'success', text: 'Concern report deleted.' });
      await loadAll(true);
      return true;
    } catch (error) {
      setNotice({ type: 'error', text: error.response?.data?.message ?? 'Could not delete that report.' });
      return false;
    }
  };

  const handleLogout = () => {
    setShowProfileMenu(false);
    logout();
  };

  if (!user) return null;

  return (
    // Scopes the modern <select> styling below (superadmin-shell select) to
    // just this portal — the main Workspace.jsx shell uses the same
    // "workspace" class name on its own <main>, so a bare Fragment here
    // couldn't be targeted on its own without also restyling every select
    // in the fleet-facing app.
    <div className="superadmin-shell" ref={shellRef}>
      <header className="topbar">
        <div className="topbar-left">
          <div className="topbar-brand-cluster">
            <button
              aria-label={isSidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
              className="sidebar-toggle-btn"
              onClick={() => setIsSidebarCollapsed((v) => !v)}
              title={isSidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
              type="button"
            >
              <Icon name="menu" size={24} />
            </button>
            <VMSLogo variant="blue" size={24} className="topbar-gear-icon" />
            <span className="vms-wordmark vms-wordmark-sm">vms</span>
            <ImpersonateInline candidates={candidates} onImpersonate={doImpersonate} />
          </div>
        </div>

        <div className="topbar-right">
          <div className="notifications-dropdown-container" ref={notificationsRef}>
            <button
              className="icon-btn notification-btn"
              title="Notifications"
              type="button"
              onClick={() => setShowNotifications((v) => !v)}
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
              </svg>
              {unreadCount > 0 && (
                <span className="notification-indicator">{unreadCount}</span>
              )}
            </button>

            {showNotifications && (
              <div className="notifications-dropdown">
                <div className="notifications-header">
                  <h4>Notifications</h4>
                  {unreadCount > 0 && (
                    <button type="button" onClick={markAllNotificationsAsRead}>Mark all as read</button>
                  )}
                </div>
                <div className="notifications-list">
                  {notifications.length === 0 ? (
                    <div className="notifications-empty">
                      <span style={{ display: 'inline-flex', opacity: 0.6 }}><Icon name="bell" size={26} /></span>
                      <span>No notifications yet.</span>
                    </div>
                  ) : (
                    notifications.map((n) => {
                      const notificationType = getNotificationStyle(n);
                      return (
                        <div
                          key={n.notification_id}
                          className={`notification-item notification-${notificationType} ${!n.read_at ? 'unread' : ''}`}
                          onClick={async () => {
                            await markNotificationAsRead(n.notification_id);
                            if (n.type === 'pending_admin_approval') {
                              navigateTab('pending');
                            }
                            setShowNotifications(false);
                          }}
                        >
                          <div className="notification-content">
                            <span className="notification-title">{n.title}</span>
                            <span className="notification-msg">{n.message}</span>
                            <span className="notification-time">{formatDate(n.created_at)}</span>
                          </div>
                          <div className="notification-actions" onClick={(e) => e.stopPropagation()}>
                            <button
                              className="notification-close-btn"
                              type="button"
                              title="Delete notification"
                              onClick={() => deleteNotification(n.notification_id)}
                            >
                              <Icon name="close" size={14} />
                            </button>
                          </div>
                        </div>
                      );
                    })
                  )}
                </div>
              </div>
            )}
          </div>

          <button
            className="icon-btn theme-toggle-btn"
            type="button"
            title={theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
            aria-label="Toggle light and dark mode"
            onClick={() => setTheme((t) => (t === 'dark' ? 'light' : 'dark'))}
          >
            {theme === 'dark' ? (
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="4" />
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
              </svg>
            ) : (
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
              </svg>
            )}
          </button>
          <div className="profile-menu-container" ref={profileMenuRef}>
            <ProfileMenu
              user={user}
              open={showProfileMenu}
              setOpen={setShowProfileMenu}
              onOpenSettings={() => navigateTab('settings')}
              onLogout={handleLogout}
            />
          </div>
        </div>
      </header>

      <main className={`workspace${isSidebarCollapsed ? ' sidebar-collapsed' : ''}`}>
        <aside className={`sidebar${isSidebarCollapsed ? ' collapsed' : ''}`}>
          <nav className="module-nav" aria-label="Super Admin modules">
            {TABS.map(([key, label]) => <button key={key} type="button" aria-label={label} aria-current={key === activeTab ? 'page' : undefined} className={key === activeTab ? 'active' : ''} onClick={() => navigateTab(key)} title={label}>
              {TAB_ICONS[key]}<span>{label}</span>{key === 'pending' && pendingApprovals.length > 0 && <span className="module-nav-badge">{pendingApprovals.length}</span>}
            </button>)}

          </nav>
        </aside>

        <section className="content-area">
          <div className={`page-heading-row${activeTab === 'dashboard' ? ' focus-branded-heading' : ''}`}>
            <div className="page-heading-text">
              <h1>{TABS.find(([k]) => k === activeTab)?.[1] ?? 'My Settings'}</h1>
              <p>{TAB_DESCRIPTIONS[activeTab] ?? TAB_DESCRIPTIONS.settings}</p>
            </div>
            {activeTab === 'barangays' && <button type="button" className="primary-button" onClick={() => setShowAddBarangay(!showAddBarangay)}><Icon name={showAddBarangay ? 'close' : 'plus'} size={16} />{showAddBarangay ? 'Cancel' : 'Add Barangay'}</button>}
          </div>

          {notice && (
            <div className={`toast-notice ${notice.type}`} role="alert">
              <div className="toast-notice-body">
                <Icon name={notice.type === 'success' ? 'checkCircle' : 'alert'} size={16} />
                <span>{notice.text}</span>
              </div>
              <button type="button" className="toast-notice-close" onClick={() => setNotice(null)} aria-label="Dismiss">
                <Icon name="close" size={13} />
              </button>
            </div>
          )}

          <div className="content-body">
            {loading ? (
              <p className="muted" role="status">Loading platform data…</p>
            ) : loadError ? (
              <div className="sa-management-card"><p className="sa-load-error" role="alert">Platform data could not be loaded. <button type="button" className="ghost-button" onClick={() => { setLoading(true); loadAll(); }}>Retry</button></p></div>
            ) : activeTab === 'dashboard' ? (
              <PlatformOverview barangays={barangays} approvals={pendingApprovals} concerns={concernReports} onNavigate={navigateTab} />
            ) : activeTab === 'barangays' ? (
              <BarangayManagementTab
                key={barangayFilter}
                initialFilter={barangayFilter}
                showAddForm={showAddBarangay}
                onCloseAdd={() => setShowAddBarangay(false)}
                onRegistrationCode={(barangay) => { navigateTab('codes'); setCodeBarangay(barangay); }}
                onRequestConfirmation={setConfirmDialog}
                barangays={barangays}
                users={visibleUsers}
                onPromote={promoteToAdmin}
                onAddedBarangay={addBarangay}
                onRetryBoundary={retryBoundary}
                onConfirmBoundary={confirmBoundary}
                onDiscardBoundary={discardBoundary}
              />
            ) : activeTab === 'pending' ? (
              <PendingApprovalsTab
                approvals={pendingApprovals}
                barangays={barangays}
                onApprove={approvePending}
                onReject={rejectPending}
                onRequestConfirmation={setConfirmDialog}
              />
            ) : activeTab === 'users' ? (
              <UsersTab users={visibleUsers} onChangeRole={changeRole} onToggleActive={toggleActive} onApprove={approvePending} onReject={rejectPending} onRequestConfirmation={setConfirmDialog} />
            ) : activeTab === 'codes' ? (
              <RegistrationCodesTab key={codeBarangay?.id ?? 'codes'} barangays={barangays} initialBarangay={codeBarangay} onRequestConfirmation={setConfirmDialog} setNotice={setNotice} />
            ) : activeTab === 'concerns' ? (
              <ConcernReportsTab reports={concernReports} onResolve={resolveConcern} onReopen={reopenConcern} onDelete={deleteConcern} onRequestConfirmation={setConfirmDialog} />
            ) : activeTab === 'activity' ? (
              <ActivityLogTab entries={activityLog} />
            ) : (
              <SettingsTab setNotice={setNotice} />
            )}
          </div>
        </section>
      </main>

      <WorkspaceFooter />
      <AdminConfirmDialog
        busy={confirmBusy}
        dialog={confirmDialog}
        onCancel={() => setConfirmDialog(null)}
        onConfirm={handleConfirmDialog}
      />
    </div>
  );
}
