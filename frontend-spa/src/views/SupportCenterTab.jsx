import SearchableSelect from '../components/SearchableSelect';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../api/axios';
import { AdminActionDrawer, AdminFilterField, AdminManagementCard, LocalSearchInput, PaginatedTable, StatusBadge } from '../components/SuperAdminUI';
import { formatDate } from '../utils/adminDate';

// Deleted reports are soft-deleted: they come back with deleted_at set and live in Concern History.
const stateOf = (report) => report.deleted_at ? 'Deleted' : report.status;
const location = (report) => [report.barangay_name, report.city_name, report.province_name].filter(Boolean).join(', ');

const ACTIONS = {
  resolve: { title: 'Resolve', confirm: 'Mark Resolved', message: 'This report will be marked resolved and moved to Concern History.', done: 'Concern Resolved', detail: 'is marked resolved' },
  reopen: { title: 'Reopen', confirm: 'Reopen Report', message: 'This report will move back to open concerns.', done: 'Concern Reopened', detail: 'is open again' },
  delete: { title: 'Delete', confirm: 'Delete Report', message: 'This report moves to Concern History. You can restore it to open concerns later.', done: 'Report Deleted', detail: 'moved to Concern History' },
  restore: { title: 'Restore', confirm: 'Restore to Open', message: 'This deleted report will move back to open concerns.', done: 'Concern Restored', detail: 'is back in open concerns' },
};

export default function SupportCenterTab({ onRequestConfirmation, onSuccess }) {
  const [reports, setReports] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [retry, setRetry] = useState(0);
  const [search, setSearch] = useState('');
  const [view, setView] = useState('open');
  const [historyFilter, setHistoryFilter] = useState('');
  const [selectedId, setSelectedId] = useState(null);
  const selected = reports.find((report) => report.id === selectedId);

  useEffect(() => {
    let current = true;
    api.get('/superadmin/concern-reports').then(({ data }) => {
      if (current) { setReports(data); setError(false); }
    }).catch(() => { if (current) setError(true); })
      .finally(() => { if (current) setLoading(false); });
    return () => { current = false; };
  }, [retry]);

  const requestAction = (report, action) => {
    const copy = ACTIONS[action];
    onRequestConfirmation({
      title: `${copy.title} concern #${report.id}?`,
      message: copy.message,
      confirmLabel: copy.confirm,
      variant: action === 'delete' ? 'danger' : 'primary',
      onConfirm: async () => {
        const path = `/superadmin/concern-reports/${report.id}`;
        const { data } = action === 'delete' ? await api.delete(path) : await api.put(`${path}/${action}`);
        setReports((rows) => rows.map((row) => row.id === report.id ? data : row));
        setSelectedId(null);
        onSuccess({ title: copy.done, detail: `Concern #${report.id} ${copy.detail}.` });
      },
    });
  };

  const openReports = reports.filter((report) => stateOf(report) === 'Open');
  const history = reports.filter((report) => stateOf(report) !== 'Open');
  const query = search.trim().toLowerCase();
  const visible = (view === 'open' ? openReports : history.filter((report) => !historyFilter || stateOf(report) === historyFilter))
    .filter((report) => [report.id, report.concern_type, report.province_name, report.city_name, report.barangay_name, report.description, report.reporter_name, report.reporter_contact].some((value) => String(value ?? '').toLowerCase().includes(query)));

  const footer = selected && <div className="sa-row-actions">{stateOf(selected) === 'Deleted'
    ? <button type="button" className="primary-button" onClick={() => requestAction(selected, 'restore')}>Restore to Open</button>
    : <><button type="button" className="primary-button" onClick={() => requestAction(selected, selected.status === 'Resolved' ? 'reopen' : 'resolve')}>{selected.status === 'Resolved' ? 'Reopen Report' : 'Mark Resolved'}</button><button type="button" className="ghost-button danger" onClick={() => requestAction(selected, 'delete')}>Delete Report</button></>}</div>;

  return <div className="superadmin-tab">
    <AdminManagementCard className="sa-support-card"
      summary={<><div className="sa-support-summary"><strong>{openReports.length} open {openReports.length === 1 ? 'concern' : 'concerns'}</strong><span>Public submissions for platform review. <Link to="/support">Open public Support Center</Link></span></div>
        <div className="sa-segments sa-support-views">{[['open', 'Open Concerns', openReports.length], ['history', 'Concern History', history.length]].map(([key, label, count]) => <button key={key} type="button" aria-pressed={view === key} onClick={() => { setView(key); setSelectedId(null); }}>{label}<span>{count}</span></button>)}</div></>}
      toolbar={<><LocalSearchInput value={search} onChange={setSearch} placeholder="Search concern, barangay, or reporter..." />{view === 'history' && <AdminFilterField label="Show"><SearchableSelect value={historyFilter} onChange={(event) => setHistoryFilter(event.target.value)}><option value="">Resolved and Deleted</option><option>Resolved</option><option>Deleted</option></SearchableSelect></AdminFilterField>}</>}>
      {loading ? <p role="status">Loading concern reports…</p> : error ? <p role="alert">Concern reports could not be loaded. <button type="button" className="ghost-button" onClick={() => { setLoading(true); setRetry((value) => value + 1); }}>Try again</button></p> : <PaginatedTable rows={visible}
        emptyMessage={view === 'open' ? 'No open concerns' : 'No concern history yet'}
        emptyDescription={view === 'open' ? 'New reports will appear here when submitted through the Support Center.' : 'Resolved and deleted concerns will appear here.'}
        columns={[
          { label: 'Concern', render: (report) => <><strong>#{report.id} · {report.concern_type}</strong><div>{location(report) || 'Location not provided'}</div></> },
          { label: 'Reporter', render: (report) => report.reporter_name || 'Anonymous' },
          { label: 'Submitted', render: (report) => formatDate(report.created_at) },
          ...(view === 'history' ? [{ label: 'Closed', render: (report) => formatDate(report.deleted_at ?? report.resolved_at) }] : []),
          { label: 'Status', render: (report) => <StatusBadge value={stateOf(report)} /> },
          { label: 'Actions', render: (report) => <button type="button" className="ghost-button" onClick={() => setSelectedId(report.id)}>Review</button> },
        ]} />}
    </AdminManagementCard>
    {selected && <AdminActionDrawer centered className="sa-concern-modal" title={`Concern #${selected.id}`} description={selected.concern_type} onClose={() => setSelectedId(null)} footer={footer}>
      <dl className="sa-detail-list"><dt>Status</dt><dd><StatusBadge value={stateOf(selected)} /></dd><dt>Location</dt><dd>{location(selected) || 'Not provided'}</dd><dt>Submitted</dt><dd>{formatDate(selected.created_at)}</dd><dt>Reporter</dt><dd>{selected.reporter_name || 'Anonymous'}</dd><dt>Email or phone</dt><dd>{selected.reporter_contact || 'Not provided'}</dd></dl>
      <h3 className="sa-concern-heading">Concern details</h3><p className="sa-concern-text">{selected.description}</p>
      {stateOf(selected) === 'Deleted' ? <p className="muted">Deleted on {formatDate(selected.deleted_at)}. Restore it to move it back to open concerns.</p>
        : selected.status === 'Resolved' && <p className="muted">Resolved by {selected.resolved_by?.name || 'a platform administrator'} on {formatDate(selected.resolved_at)}.</p>}
    </AdminActionDrawer>}
  </div>;
}
