import { useEffect, useState } from 'react';
import api from '../api/axios';
import './VehicleTripLog.css';

const dateTime = (value) => value ? new Date(value).toLocaleString('en-PH', { timeZone: 'Asia/Manila', dateStyle: 'medium', timeStyle: 'short' }) : '—';

export default function VehicleTripLog({ vehicle, canLog = false, onChanged, onViewTicket }) {
  const [page, setPage] = useState(1);
  const [result, setResult] = useState(null);
  const [revision, setRevision] = useState(0);
  const [loading, setLoading] = useState(true);
  const [loadFailed, setLoadFailed] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [releaseRequested, setReleaseRequested] = useState(false);
  useEffect(() => {
    let cancelled = false;
    api.get(`/vehicles/${vehicle.vehicle_id}/usage`, { params: { page, per_page: 10 } })
      .then(({ data }) => { if (!cancelled) setResult(data); })
      .catch(() => { if (!cancelled) { setLoadFailed(true); setError('Could not load trips. Please retry.'); } })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [vehicle.vehicle_id, page, revision]);

  const reload = () => { setLoading(true); setLoadFailed(false); setError(''); setRevision((value) => value + 1); };
  const changePage = (next) => { setLoading(true); setLoadFailed(false); setError(''); setPage(next); };

  const save = async (event, returning) => {
    event.preventDefault();
    if (busy || loading) return;
    const form = event.currentTarget;
    const payload = Object.fromEntries([...new FormData(form)].filter(([, value]) => value !== ''));
    if (!returning && releaseRequested && result?.can_temporary_release) {
      payload.temporary_release = true;
      payload.ticket_ids = (result.blocking_tickets ?? []).map((ticket) => ticket.ticket_id);
      payload.assessment_ids = (result.release_assessments ?? []).map((assessment) => assessment.id);
      for (const name of ['personally_tested', 'safe_for_intended_use', 'fully_assembled', 'no_safety_critical_fault']) payload[name] = payload[name] === 'on';
      if (payload.expected_return_at) payload.expected_return_at = new Date(`${payload.expected_return_at}+08:00`).toISOString();
    }
    const field = returning ? 'ended_at' : 'started_at';
    if (payload[field]) payload[field] = new Date(`${payload[field]}+08:00`).toISOString();
    setBusy(true); setError('');
    try {
      if (returning) await api.put(`/usage-logs/${result.open_trip.usage_id}/end`, payload);
      else await api.post(`/vehicles/${vehicle.vehicle_id}/usage`, payload);
      form.reset(); setReleaseRequested(false); setPage(1); reload();
      onChanged?.();
    } catch (err) {
      if (err.response?.data?.release_requirements) setResult((current) => ({ ...current, release_requirements: err.response.data.release_requirements }));
      if (err.response?.data?.blocking_tickets) setResult((current) => ({ ...current, blocking_tickets: err.response.data.blocking_tickets }));
      setError(Object.values(err.response?.data?.errors ?? {}).flat().join(' ') || err.response?.data?.message || 'Could not save trip.');
    } finally { setBusy(false); }
  };
  const open = result?.open_trip;
  const blockingTickets = result?.blocking_tickets ?? [];
  const dispatchBlocked = blockingTickets.length > 0;
  const requirements = result?.release_requirements ?? [];
  const canRelease = dispatchBlocked && result?.can_temporary_release && requirements.length === 0 && ['Available', 'Under Maintenance'].includes(vehicle.status);
  const releasing = canRelease && releaseRequested;
  return <section className="veh-card vehicle-trip-log">
    <div className="veh-card-head"><h4>Trip log</h4><span>{result?.total ?? 0} trips · Philippine time</span></div>
    {error && <div className="notice error" role="alert">{error} {loadFailed && <button type="button" className="ghost-button" onClick={reload}>Retry</button>}</div>}
    {loading && <p role="status">Loading trips…</p>}
    {!loading && !loadFailed && <p>{vehicle.vehicle_name} · {open ? `Dispatched since ${dateTime(open.started_at)}` : vehicle.status === 'Available' ? 'In station' : vehicle.status}</p>}
    {!loading && !loadFailed && !open && dispatchBlocked && <div className="notice warning dispatch-release-notice" role="status">
      <p>This vehicle has ongoing maintenance work. Normal dispatch is blocked; an eligible Custodian can authorize a temporary release for one trip.</p>
      <ul>{blockingTickets.map((ticket) => <li key={ticket.ticket_id}>{onViewTicket ? <button type="button" className="cell-link" onClick={() => onViewTicket(ticket)}>View Ticket #{ticket.barangay_number ?? ticket.ticket_id}</button> : ticket.ticket_title} <span>({ticket.status})</span></li>)}</ul>
      {(result?.release_assessments ?? []).map((assessment) => <p key={assessment.id}><strong>{assessment.name} (Ticket #{assessment.ticket_number}):</strong> {!assessment.current ? 'Expired or repair changed - reassessment required' : assessment.safe_to_operate && assessment.assembled && assessment.no_safety_critical_fault ? 'Recorded safe for temporary use' : 'Do not dispatch'}. {assessment.notes}</p>)}
      {requirements.length > 0 && <ul>{requirements.map((message) => <li key={message}>{message}</li>)}</ul>}
      <button type="button" className="ghost-button" onClick={reload}>Refresh release checks</button>
      {canRelease && <label className="release-check"><input type="checkbox" checked={releaseRequested} onChange={(event) => setReleaseRequested(event.target.checked)} /> Authorize temporary dispatch as Custodian</label>}
    </div>}
    {!loading && !loadFailed && open?.temporary_release && <div className="notice warning dispatch-release-notice" role="status">Temporary release: {open.temporary_release.reason}. Expected return: {dateTime(open.temporary_release.expected_return_at)}. The ticket stays open; repairs resume after return.</div>}
    {!loading && !loadFailed && canLog && (open || releasing || (vehicle.status === 'Available' && !dispatchBlocked)) && <form className="trip-log-form" onSubmit={(event) => save(event, !!open)}>
      <h4>{open ? `Return vehicle — ${open.purpose}` : 'Record departure'}</h4>
      {!open && <>
        <label>Driver<input name="driver_name" required maxLength={255} /></label>
        <label>Destination<input name="destination" required maxLength={255} /></label>
        <label>Purpose<input name="purpose" required maxLength={255} /></label>
      </>}
      {releasing && !open && <fieldset className="temporary-release-fields">
        <legend>Temporary release readiness check</legend>
        <p>Admin will be notified immediately. This authorizes one trip and leaves every ticket open.</p>
        <label>Reason repairs can wait and why the vehicle is needed<textarea name="release_reason" required maxLength={2000} rows={3} /></label>
        <label>Expected return (Philippine time)<input name="expected_return_at" type="datetime-local" required /></label>
        <label className="release-check"><input type="checkbox" name="personally_tested" required /> I personally checked, operated and tested this vehicle now</label>
        <label className="release-check"><input type="checkbox" name="safe_for_intended_use" required /> The vehicle is safe for this trip and the mechanic's restrictions will be followed</label>
        <label className="release-check"><input type="checkbox" name="fully_assembled" required /> The vehicle is fully assembled and operational</label>
        <label className="release-check"><input type="checkbox" name="no_safety_critical_fault" required /> No unresolved safety-critical fault prevents operation</label>
      </fieldset>}
      {!releasing && <label>{open ? 'Return time' : 'Departure time'} (optional, defaults to now)<input name={open ? 'ended_at' : 'started_at'} type="datetime-local" /></label>}
      <label>Notes<textarea name="notes" rows={2} /></label>
      <button type="submit" className="primary-button" disabled={busy}>{busy ? 'Saving…' : open ? 'Record return' : releasing ? 'Authorize and dispatch' : 'Dispatch vehicle'}</button>
    </form>}
    {!loading && !loadFailed && <>
      <div className="trip-log-table"><table><thead><tr><th>Driver</th><th>Destination / purpose</th><th>Departure</th><th>Return</th><th>Notes</th><th>Logged by</th></tr></thead><tbody>
        {(result?.data ?? []).map((trip) => <tr key={trip.usage_id}><td>{trip.driver_name || 'Not recorded'}</td><td>{trip.destination || 'Not recorded'}<br />{trip.purpose}{trip.temporary_release && <><br /><strong>Temporary release</strong><br />{trip.temporary_release.reason}<br />Expected return: {dateTime(trip.temporary_release.expected_return_at)}</>}</td><td>{dateTime(trip.started_at)}</td><td>{trip.ended_at ? dateTime(trip.ended_at) : 'Out now'}</td><td>{trip.notes || '—'}</td><td>{trip.logged_by?.name || '—'}</td></tr>)}
        {!result?.data?.length && <tr><td colSpan={6}>No trips recorded yet.</td></tr>}
      </tbody></table></div>
      <nav className="trip-log-pagination" aria-label="Trip history pages"><button className="ghost-button" type="button" disabled={page <= 1} onClick={() => changePage(page - 1)}>Previous</button><span>Page {page} of {result?.last_page ?? 1}</span><button className="ghost-button" type="button" disabled={page >= (result?.last_page ?? 1)} onClick={() => changePage(page + 1)}>Next</button></nav>
    </>}
  </section>;
}
