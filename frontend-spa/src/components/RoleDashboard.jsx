import SearchableSelect from './SearchableSelect';
import './RoleDashboard.css';
import { useEffect, useState } from 'react';
import api from '../api/axios';
import Icon from './Icon';
import { DonutChart, HorizontalBarChart } from './charts';
import { didRepairWorkOn } from '../utils/ticketVerification';

const roleCopy = {
  Admin: ['Administrator Dashboard', 'Review pending decisions and monitor fleet maintenance.'],
  Custodian: ['Custodian Dashboard', 'Prioritize inspections, readiness checks, and independent repair verification.'],
  'Maintenance Personnel': ['Maintenance Dashboard', 'Your assigned repairs, upcoming maintenance, and completed work.'],
};
const conditionColors = { Good: '#16a34a', 'Needs Inspection': '#d97706', 'Needs Repair': '#dc2626' };
const sameId = (a, b) => a != null && b != null && String(a) === String(b);
const date = (value) => value ? new Date(value).toLocaleDateString('en-PH', { timeZone: 'Asia/Manila', month: 'short', day: 'numeric', year: 'numeric' }) : 'No date recorded';

function DashboardBarCard({ title, description, rows, unit, loading, error, emptyMessage, children }) {
  return <section className="focus-card focus-bar-card" aria-label={title}>
    <header><h3>{title}</h3><span>{unit}</span></header>
    <p className="focus-chart-description">{description}</p>
    {error ? <p className="focus-empty">Chart data could not be loaded. Retry above.</p> : loading ? <p className="focus-empty">Loading chart…</p> : !rows.length ? <p className="focus-empty">{emptyMessage}</p> : <>
      <HorizontalBarChart rows={rows} wrapLabels />
      <ul className="focus-chart-values" aria-label={`${title} counts`}>{rows.map((row) => <li key={row.label}>{row.label}: {row.value}</li>)}</ul>
    </>}
    {children}
  </section>;
}

function TaskList({ title, items, onViewAll, allLabel = 'View all', loading, error }) {
  const [expanded, setExpanded] = useState(false);
  return <section className="focus-card">
    <header><h3>{title}</h3>{onViewAll && <button type="button" onClick={onViewAll}>{allLabel} <Icon name="chevronRight" size={14} /></button>}</header>
    {error ? <p className="focus-empty">Task data is unavailable. Open the module or retry above.</p> : loading ? <p className="focus-empty">Loading assignments…</p> : !items.length ? <p className="focus-empty">Nothing needs your attention here.</p> : <ul className="focus-tasks">
      {(expanded ? items : items.slice(0, 5)).map((item) => <li key={item.key}>
        <span className={`focus-task-mark ${item.urgent ? 'is-urgent' : ''}`}><Icon name={item.icon ?? 'clipboard'} size={17} /></span>
        <div><strong>{item.title}</strong><span>{item.reference}</span><small>{item.status}{item.date ? ` · ${date(item.date)}` : ''}</small></div>
        <button type="button" onClick={item.open}>{item.action ?? 'Review'} <Icon name="chevronRight" size={14} /></button>
      </li>)}
    </ul>}
    {items.length > 5 && <button className="focus-more" type="button" onClick={() => setExpanded(!expanded)}>{expanded ? 'Show fewer' : `Show all ${items.length} items`}</button>}
  </section>;
}

export default function RoleDashboard({ data, user, basePath, onNavigate, onGoToModule, isScheduleOverdue }) {
  const roles = [...new Set([user.role, ...(user.roles ?? [])])].filter((role) => roleCopy[role]);
  const [selectedRole, setSelectedRole] = useState(roles[0]);
  const role = roles.includes(selectedRole) ? selectedRole : roles[0];
  const [feed, setFeed] = useState(null);
  const [error, setError] = useState('');
  const [retry, setRetry] = useState(0);
  useEffect(() => {
    let cancelled = false;
    const requests = [api.get('/tickets'), api.get('/maintenance-schedules'), role === 'Custodian' ? api.get('/issues') : Promise.resolve({ data: [] })];
    Promise.all(requests).then(([tickets, schedules, issues]) => {
      if (!cancelled) { setFeed({ tickets: tickets.data, schedules: schedules.data, issues: issues.data, role }); setError(''); }
    }).catch(() => { if (!cancelled) setError('Assignments could not be loaded. Retry or open the relevant module.'); });
    return () => { cancelled = true; };
    // Not keyed on `data`: the dashboard summary is re-polled every 15s and on
    // window focus, and refetching the full ticket/schedule lists each time
    // flooded the single-threaded dev server and delayed module switches.
  }, [user.id, role, retry]);

  if (!data) return <p className="empty-state">Dashboard data is not available yet.</p>;
  const pending = !feed || feed.role !== role;
  const loading = pending && !error;
  const tickets = pending ? [] : feed.tickets;
  const schedules = pending ? [] : feed.schedules;
  const issues = pending ? [] : feed.issues;
  const admin = role === 'Admin';
  const custodian = role === 'Custodian';
  const mine = tickets.filter((t) => sameId(t.assigned_mechanic_id, user.id) || (t.sub_issues ?? []).some((s) => sameId(s.assigned_mechanic_id, user.id)));
  const mySchedules = schedules.filter((s) => sameId(s.assigned_to, user.id) && s.status === 'Scheduled' && !s.resulting_ticket_id);
  const overdueSchedules = schedules.filter(isScheduleOverdue);
  const myOverdue = mySchedules.filter(isScheduleOverdue);
  const verifications = tickets.filter((t) => t.status === 'For Verification' && sameId(t.assigned_custodian_id, user.id) && !didRepairWorkOn(t, user.id));
  const readiness = data.readiness ?? [];
  const watch = data.readiness_watch ?? [];
  const openIssues = issues.filter((i) => ['Pending', 'Under Review'].includes(i.status) && !i.maintenance_ticket);
  const pendingTickets = tickets.filter((t) => t.status === 'Pending Approval');
  const pendingSchedules = schedules.filter((s) => s.status === 'Pending Approval');
  const metric = (label) => data.metrics?.find((m) => m.label === label)?.value ?? 0;
  const go = (module, filters = []) => onGoToModule(module, filters);
  const ticketTask = (t, title = t.ticket_title) => ({ key: `ticket-${t.ticket_id}`, title, reference: `${t.vehicle?.vehicle_name ?? 'Vehicle'} · Ticket #${t.ticket_id}`, status: t.status, date: t.created_at, urgent: t.priority === 'High', open: () => onNavigate(`${basePath}/tickets/${t.ticket_id}`), action: 'View ticket' });
  const scheduleTask = (s) => ({ key: `schedule-${s.schedule_id}`, title: s.maintenance_type, reference: `${s.vehicle?.vehicle_name ?? 'Vehicle'} · Schedule #${s.schedule_id}`, status: isScheduleOverdue(s) ? 'Overdue' : s.status, date: s.scheduled_date, urgent: isScheduleOverdue(s), icon: 'calendar', open: () => onNavigate(`${basePath}/schedules/${s.schedule_id}`), action: 'View schedule' });
  const activeMine = mine.filter((t) => t.status === 'Active');
  const tasks = admin ? [
    ...pendingTickets.map((t) => ticketTask(t, 'Review ticket proposal')),
    ...pendingSchedules.map((s) => ({ ...scheduleTask(s), title: 'Review schedule booking' })),
    ...tickets.filter((t) => t.status === 'Active' && !(t.sub_issues ?? []).some((s) => s.repair_type === 'external') && !t.assigned_mechanic_id && !(t.sub_issues ?? []).some((s) => s.assigned_mechanic_id)).map((t) => ticketTask(t, 'Assign maintenance personnel')),
    ...overdueSchedules.filter((s) => !s.resulting_ticket_id).map(scheduleTask),
  ] : custodian ? [
    ...verifications.map((t) => ticketTask(t, 'Verify completed repair')),
    ...tickets.filter((t) => t.status === 'Open' && sameId(t.assigned_custodian_id, user.id)).map((t) => ticketTask(t, 'Inspect reported issue')),
    ...watch.map((v) => ({ key: `readiness-${v.vehicle_id}`, title: 'Check vehicle readiness', reference: v.vehicle_name, status: ({ not_ready: 'Failed last check', stale: 'Check expired', unchecked: 'Not checked' })[v.state] ?? v.state, urgent: v.state === 'not_ready', icon: 'vehicle', open: () => onNavigate(`${basePath}/vehicles/${v.vehicle_id}`), action: 'View vehicle' })),
    ...openIssues.map((i) => ({ key: `issue-${i.issue_report_id}`, title: 'Follow up vehicle issue', reference: `${i.vehicle?.vehicle_name ?? 'Vehicle'} · Issue #${i.issue_report_id}`, status: i.status, date: i.created_at, open: () => onNavigate(`${basePath}/issues/${i.issue_report_id}`), action: 'View issue' })),
  ] : [...activeMine.map((t) => ticketTask(t)), ...mySchedules.map(scheduleTask)];
  tasks.sort((a, b) => Number(b.urgent) - Number(a.urgent));
  const kpis = admin ? [
    { label: 'Pending Approvals', value: pending ? '—' : pendingTickets.length + pendingSchedules.length, detail: 'Ticket proposals and schedule bookings', icon: 'clipboard', open: () => go('tickets', ['Pending Approval']) },
    { label: 'Active Maintenance', value: metric('Vehicles Under Maintenance'), detail: 'Vehicles under maintenance', icon: 'wrench', open: () => go('vehicles', ['Under Maintenance']) },
    { label: 'Overdue Maintenance', value: metric('Overdue Maintenance'), detail: 'Scheduled maintenance past due', icon: 'calendar', open: () => go('schedules', ['Overdue']) },
    { label: 'Available Vehicles', value: metric('Available Vehicles'), detail: 'Availability, separate from readiness', icon: 'vehicle', open: () => go('vehicles', ['Available']) },
  ] : custodian ? [
    { label: 'Pending Verification', value: pending ? '—' : verifications.length, icon: 'checkCircle', open: () => go('ticketVerifications', ['Pending']) },
    { label: 'Readiness Checks Needed', value: watch.length, icon: 'vehicle', open: () => go('vehicles') },
    { label: 'Open Issues', value: pending ? '—' : openIssues.length, icon: 'alert', open: () => go('issues') },
  ] : [
    { label: 'My Active Work Orders', value: pending ? '—' : activeMine.length, icon: 'wrench', open: () => go('ticketWorkOrders') },
    { label: 'My Upcoming Maintenance', value: pending ? '—' : mySchedules.length - myOverdue.length, icon: 'calendar', open: () => go('schedules', ['MyAssigned']) },
    { label: 'My Overdue Assignments', value: pending ? '—' : myOverdue.length, detail: 'Assigned schedules past due', icon: 'alert', open: () => go('schedules', ['MyAssigned']) },
  ];
  const conditions = (data.vehicles_by_condition ?? []).map((row) => ({ ...row, color: conditionColors[row.label] ?? '#64748b' }));
  const attentionRows = readiness.filter((row) => row.total != null && row.verified_ready != null && Number.isFinite(Number(row.total)) && Number.isFinite(Number(row.verified_ready))).map((row) => {
    const value = Math.max(0, Number(row.total) - Number(row.verified_ready));
    return { label: row.category, value, color: value === 0 ? '#16a34a' : Number(row.verified_ready) === 0 ? '#dc2626' : '#d97706' };
  });
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
  const datedSchedules = mySchedules.filter((schedule) => /^\d{4}-\d{2}-\d{2}/.test(schedule.scheduled_date ?? '') && !Number.isNaN(new Date(schedule.scheduled_date).getTime()));
  const scheduleRows = datedSchedules.length ? [
    { label: 'Upcoming', value: datedSchedules.filter((schedule) => schedule.scheduled_date.slice(0, 10) > today).length, color: '#d97706' },
    { label: 'Due Today', value: datedSchedules.filter((schedule) => schedule.scheduled_date.slice(0, 10) === today).length, color: '#2563eb' },
    { label: 'Overdue', value: datedSchedules.filter((schedule) => schedule.scheduled_date.slice(0, 10) < today).length, color: '#dc2626' },
  ] : [];
  const workSource = admin ? tickets : mine;
  const workRows = ['Pending Approval', 'Active', 'For Verification', 'Closed', 'Declined', 'Cancelled'].map((label) => ({ label, color: ({'Pending Approval':'#d97706',Active:'#2563eb','For Verification':'#7c3aed',Closed:'#16a34a',Declined:'#dc2626',Cancelled:'#64748b'})[label], value: workSource.filter((t) => t.status === label).length })).filter((r) => r.value > 0);
  const completed = mine.flatMap((t) => (t.sub_issues ?? []).filter((s) => sameId(s.assigned_mechanic_id, user.id) && s.repair_completed_at).map((s) => ({ ...ticketTask(t, s.maintenance_type ?? s.title), key: `repair-${s.sub_issue_id}`, status: s.status, date: s.repair_completed_at }))).sort((a, b) => String(b.date).localeCompare(String(a.date)));
  const conditionChart = <section className="focus-card"><header><h3>Fleet Condition</h3><span>Physical condition</span></header>{conditions.some((c) => c.value > 0) ? <div className="focus-donut"><DonutChart segments={conditions} centerLabel={conditions.reduce((sum, c) => sum + Number(c.value), 0)} centerSubLabel="vehicles" /><ul>{conditions.map((c) => <li key={c.label}><i style={{ background: c.color }} />{c.label}<strong>{c.value}</strong></li>)}</ul></div> : <p className="focus-empty">No vehicle condition data yet.</p>}</section>;
  const workload = <section className="focus-card"><header><h3>{admin ? 'Maintenance Status' : 'My Work Orders by Status'}</h3><span>Tickets</span></header>{error ? <p className="focus-empty">Workload data could not be loaded.</p> : loading ? <p className="focus-empty">Loading workload…</p> : workRows.length ? <HorizontalBarChart rows={workRows} /> : <p className="focus-empty">No work orders to chart yet.</p>}</section>;
  const readinessDetails = <details className="focus-readiness-details"><summary>View readiness totals</summary><div className="focus-table-wrap"><table><thead><tr><th>Vehicle type</th><th>Total units</th><th>Verified ready</th><th>Needs attention</th></tr></thead><tbody>{readiness.map((row) => <tr key={row.category}><td>{row.category}{row.verified_ready === 0 && <small className="focus-warning">No verified-ready coverage</small>}</td><td>{row.total}</td><td>{row.verified_ready ?? '—'}</td><td>{row.verified_ready == null ? '—' : Math.max(0, row.total - row.verified_ready)}</td></tr>)}</tbody></table></div><button type="button" onClick={() => go('vehicles')}>View vehicles <Icon name="chevronRight" size={14} /></button></details>;
  const attentionChart = <DashboardBarCard title="Readiness Attention by Vehicle Type" description="Vehicles without verified-ready status. Red indicates a type with no verified-ready coverage; amber indicates partial coverage." rows={attentionRows} unit="Vehicles" emptyMessage="No readiness counts available yet.">{readiness.length > 0 && readinessDetails}</DashboardBarCard>;
  const scheduleChart = <DashboardBarCard title="My Maintenance Schedule" description="Your scheduled assignments by due date, using Philippine time. Upcoming excludes today." rows={scheduleRows} unit="Schedules" loading={loading} error={error} emptyMessage={mySchedules.length ? 'No dated assignments to chart yet.' : 'No assigned maintenance schedules.'} />;
  return <div className="focus-dashboard role-dashboard">
    <header className="focus-heading focus-branded-heading"><div><h2>{roleCopy[role][0]}</h2><p>{roleCopy[role][1]}</p></div>{roles.length > 1 && <label>Responsibility<SearchableSelect aria-label="Dashboard responsibility" value={role} onChange={(e) => setSelectedRole(e.target.value)}>{roles.map((r) => <option key={r}>{r}</option>)}</SearchableSelect></label>}</header>
    {error && <div role="alert" className="focus-error">{error}<button type="button" onClick={() => setRetry((n) => n + 1)}>Retry</button></div>}
    <div className="focus-kpis" style={{ '--kpi-count': kpis.length }}>{kpis.map((k) => <button type="button" key={k.label} onClick={k.open}><span className="focus-kpi-icon"><Icon name={k.icon} size={19} /></span><span>{k.label}<strong>{k.value}</strong>{k.detail && <small>{k.detail}</small>}</span><Icon name="chevronRight" size={14} /></button>)}</div>
    <div className={admin ? 'focus-columns focus-columns-even' : 'focus-columns'}>
      <div className="focus-panel-stack">
        {admin && conditionChart}
        <TaskList key={role} title={admin ? 'Action Queue' : custodian ? 'My Tasks' : 'My Assigned Work'} items={tasks} loading={loading} error={error} onViewAll={() => go(admin ? 'tickets' : custodian ? 'ticketVerifications' : 'ticketWorkOrders')} />
        {!admin && !custodian && completed.length > 0 && <TaskList title="Recent Completed Work" items={completed.slice(0, 5)} onViewAll={() => go('workTracker')} />}
      </div>
      <div className="focus-panel-stack">
        {custodian ? <>{conditionChart}{attentionChart}</> : workload}
        {!admin && !custodian && scheduleChart}
        {admin && (data.sole_active_admin || readiness.some((row) => row.verified_ready === 0)) && <section className="focus-card">
          <header><h3>Operational Risks</h3><button type="button" onClick={() => go('vehicles')}>View fleet</button></header>
          <ul className="focus-risk-list">
            {data.sole_active_admin && <li><Icon name="alert" size={16} /><span><strong>Only one active Administrator</strong>Arrange a backup Administrator to keep account management covered. <button type="button" onClick={() => go('users')}>Manage users</button></span></li>}
            {readiness.filter((row) => row.verified_ready === 0).map((row) => <li key={row.category}><Icon name="alert" size={16} /><span><strong>{row.category}</strong> No verified-ready coverage. Review available vehicles and maintenance priorities.</span></li>)}
          </ul>
        </section>}
      </div>
    </div>
  </div>;
}
