import SearchableSelect from '../components/SearchableSelect';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../api/axios';
import { AdminActionDrawer, AdminFilterField, AdminManagementCard, LocalSearchInput, PaginatedTable, StatusBadge } from '../components/SuperAdminUI';
import { formatDate } from '../utils/adminDate';

export default function SupportCenterTab({ onRequestConfirmation, setNotice }) {
  const [reports, setReports] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [retry, setRetry] = useState(0);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('Open');
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
    const deleting = action === 'delete';
    onRequestConfirmation({
      title: `${deleting ? 'Delete' : action === 'resolve' ? 'Resolve' : 'Reopen'} concern #${report.id}?`,
      message: deleting ? 'This permanently deletes the report and its contact details.' : `This report will be marked ${action === 'resolve' ? 'resolved' : 'open'}.`,
      confirmLabel: deleting ? 'Delete Report' : action === 'resolve' ? 'Mark Resolved' : 'Reopen Report',
      variant: deleting ? 'danger' : 'primary',
      onConfirm: async () => {
        const path = `/superadmin/concern-reports/${report.id}`;
        if (deleting) {
          await api.delete(path);
          setReports((rows) => rows.filter((row) => row.id !== report.id));
          setSelectedId(null);
        } else {
          const { data } = await api.put(`${path}/${action}`);
          setReports((rows) => rows.map((row) => row.id === report.id ? data : row));
        }
        setNotice({ type: 'success', text: deleting ? 'Concern report deleted.' : action === 'resolve' ? 'Concern report resolved.' : 'Concern report reopened.' });
      },
    });
  };

  const query = search.trim().toLowerCase();
  const visible = reports.filter((report) => (!status || report.status === status)
    && [report.id, report.concern_type, report.province_name, report.city_name, report.barangay_name, report.description, report.reporter_name, report.reporter_contact].some((value) => String(value ?? '').toLowerCase().includes(query)));

  return <div className="superadmin-tab">
    <AdminManagementCard summary={<><strong>{reports.filter((report) => report.status === 'Open').length} open concerns</strong><span>Public submissions for platform review. <Link to="/support">Open public Support Center</Link></span></>}
      toolbar={<><LocalSearchInput value={search} onChange={setSearch} placeholder="Search concern, barangay, or reporter..." /><AdminFilterField label="Status"><SearchableSelect value={status} onChange={(event) => setStatus(event.target.value)}><option value="">All Statuses</option><option>Open</option><option>Resolved</option></SearchableSelect></AdminFilterField><button type="button" className="ghost-button" disabled={loading} onClick={() => { setLoading(true); setRetry((value) => value + 1); }}>Refresh</button></>}>
      {loading ? <p role="status">Loading concern reports…</p> : error ? <p role="alert">Concern reports could not be loaded. Use Refresh to try again.</p> : <PaginatedTable rows={visible} emptyMessage="No matching concerns" emptyDescription="New reports will appear here when submitted through the Support Center." columns={[
        { label: 'Concern', render: (report) => <><strong>#{report.id} · {report.concern_type}</strong><div>{[report.barangay_name, report.city_name, report.province_name].filter(Boolean).join(', ') || 'Location not provided'}</div></> },
        { label: 'Reporter', render: (report) => report.reporter_name || 'Anonymous' },
        { label: 'Submitted', render: (report) => formatDate(report.created_at) },
        { label: 'Status', render: (report) => <StatusBadge value={report.status} /> },
        { label: 'Actions', render: (report) => <button type="button" className="ghost-button" onClick={() => setSelectedId(report.id)}>Review</button> },
      ]} />}
    </AdminManagementCard>
    {selected && <AdminActionDrawer title={`Concern #${selected.id}`} description={selected.concern_type} onClose={() => setSelectedId(null)} footer={<div className="sa-row-actions"><button type="button" className="primary-button" onClick={() => requestAction(selected, selected.status === 'Resolved' ? 'reopen' : 'resolve')}>{selected.status === 'Resolved' ? 'Reopen Report' : 'Mark Resolved'}</button><button type="button" className="ghost-button danger" onClick={() => requestAction(selected, 'delete')}>Delete Report</button></div>}>
      <dl><dt>Status</dt><dd><StatusBadge value={selected.status} /></dd><dt>Location</dt><dd>{[selected.barangay_name, selected.city_name, selected.province_name].filter(Boolean).join(', ') || 'Not provided'}</dd><dt>Submitted</dt><dd>{formatDate(selected.created_at)}</dd><dt>Reporter</dt><dd>{selected.reporter_name || 'Anonymous'}</dd><dt>Email or phone</dt><dd>{selected.reporter_contact || 'Not provided'}</dd></dl>
      <h3>Concern details</h3><p style={{ whiteSpace: 'pre-wrap', overflowWrap: 'anywhere' }}>{selected.description}</p>
      {selected.status === 'Resolved' && <p className="muted">Resolved by {selected.resolved_by?.name || 'a platform administrator'} on {formatDate(selected.resolved_at)}.</p>}
    </AdminActionDrawer>}
  </div>;
}
