import { useEffect, useState } from 'react';
import api from '../api/axios';
import './VehicleTripLog.css';

const dateTime = (value) => value ? new Date(value).toLocaleString('en-PH', { timeZone: 'Asia/Manila', dateStyle: 'medium', timeStyle: 'short' }) : '—';

export default function VehicleTripLog({ vehicle, canLog = false, onChanged }) {
  const [page, setPage] = useState(1);
  const [result, setResult] = useState(null);
  const [revision, setRevision] = useState(0);
  const [loading, setLoading] = useState(true);
  const [loadFailed, setLoadFailed] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
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
    const field = returning ? 'ended_at' : 'started_at';
    if (payload[field]) payload[field] = new Date(`${payload[field]}+08:00`).toISOString();
    setBusy(true); setError('');
    try {
      if (returning) await api.put(`/usage-logs/${result.open_trip.usage_id}/end`, payload);
      else await api.post(`/vehicles/${vehicle.vehicle_id}/usage`, payload);
      form.reset(); setPage(1); reload();
      onChanged?.();
    } catch (err) {
      setError(Object.values(err.response?.data?.errors ?? {}).flat().join(' ') || err.response?.data?.message || 'Could not save trip.');
    } finally { setBusy(false); }
  };
  const open = result?.open_trip;
  return <section className="veh-card vehicle-trip-log">
    <div className="veh-card-head"><h4>Trip log</h4><span>{result?.total ?? 0} trips · Philippine time</span></div>
    {error && <div className="notice error" role="alert">{error} {loadFailed && <button type="button" className="ghost-button" onClick={reload}>Retry</button>}</div>}
    {loading && <p role="status">Loading trips…</p>}
    {!loading && !loadFailed && <p>{vehicle.vehicle_name} · {open ? `Dispatched since ${dateTime(open.started_at)}` : vehicle.status === 'Available' ? 'In station' : vehicle.status}</p>}
    {!loading && !loadFailed && canLog && (open || vehicle.status === 'Available') && <form className="trip-log-form" onSubmit={(event) => save(event, !!open)}>
      <h4>{open ? `Return vehicle — ${open.purpose}` : 'Record departure'}</h4>
      {!open && <>
        <label>Driver<input name="driver_name" required maxLength={255} /></label>
        <label>Destination<input name="destination" required maxLength={255} /></label>
        <label>Purpose<input name="purpose" required maxLength={255} /></label>
      </>}
      <label>{open ? 'Return time' : 'Departure time'} (optional, defaults to now)<input name={open ? 'ended_at' : 'started_at'} type="datetime-local" /></label>
      <label>Notes<textarea name="notes" rows={2} /></label>
      <button type="submit" className="primary-button" disabled={busy}>{busy ? 'Saving…' : open ? 'Record return' : 'Dispatch vehicle'}</button>
    </form>}
    {!loading && !loadFailed && <>
      <div className="trip-log-table"><table><thead><tr><th>Driver</th><th>Destination / purpose</th><th>Departure</th><th>Return</th><th>Notes</th><th>Logged by</th></tr></thead><tbody>
        {(result?.data ?? []).map((trip) => <tr key={trip.usage_id}><td>{trip.driver_name || 'Not recorded'}</td><td>{trip.destination || 'Not recorded'}<br />{trip.purpose}</td><td>{dateTime(trip.started_at)}</td><td>{trip.ended_at ? dateTime(trip.ended_at) : 'Out now'}</td><td>{trip.notes || '—'}</td><td>{trip.logged_by?.name || '—'}</td></tr>)}
        {!result?.data?.length && <tr><td colSpan={6}>No trips recorded yet.</td></tr>}
      </tbody></table></div>
      <nav className="trip-log-pagination" aria-label="Trip history pages"><button className="ghost-button" type="button" disabled={page <= 1} onClick={() => changePage(page - 1)}>Previous</button><span>Page {page} of {result?.last_page ?? 1}</span><button className="ghost-button" type="button" disabled={page >= (result?.last_page ?? 1)} onClick={() => changePage(page + 1)}>Next</button></nav>
    </>}
  </section>;
}
