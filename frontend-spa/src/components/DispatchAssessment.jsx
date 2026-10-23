import { useState } from 'react';
import api from '../api/axios';

export default function DispatchAssessment({ ticket, canAssess }) {
  const [assessments, setAssessments] = useState(null);
  const [editing, setEditing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);
  const release = ticket.temporary_dispatch?.temporary_release;
  const rows = assessments ?? ticket.dispatch_assessments ?? [];
  if (!['Active', 'For Verification'].includes(ticket.status)) return null;
  const submit = async (event) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setBusy(true); setError('');
    try {
      await api.post(`/tickets/${ticket.ticket_id}/dispatch-assessment`, {
        safe_to_operate: form.get('safe_to_operate') === 'yes',
        assembled: form.get('assembled') === 'on',
        no_safety_critical_fault: form.get('no_safety_critical_fault') === 'on',
        notes: form.get('notes'),
      });
      const { data } = await api.get(`/tickets/${ticket.ticket_id}`);
      setAssessments(data.dispatch_assessments ?? []); setSaved(true); setEditing(false);
    } catch (err) {
      setError(Object.values(err.response?.data?.errors ?? {}).flat().join(' ') || err.response?.data?.message || 'Could not record assessment.');
    } finally { setBusy(false); }
  };
  return <section className="ticket-section dispatch-assessment">
    <h4>Temporary dispatch assessment</h4>
    {release && <p role="status">Temporarily dispatched. Repair and verification can resume after return. Expected return: {new Date(release.expected_return_at).toLocaleString('en-PH', { timeZone: 'Asia/Manila' })}. Reason: {release.reason}</p>}
    <p className="muted">The Custodian may authorize one trip without waiting for Admin. Each assigned mechanic must first confirm safe operation. Assessments expire after 24 hours and must be renewed if the repair changes.</p>
    {rows.map((row) => <p key={row.id}><strong>{row.name || 'Assigned mechanic'}</strong>: {Boolean(Number(row.safe_to_operate)) && Boolean(Number(row.assembled)) && Boolean(Number(row.no_safety_critical_fault)) ? 'Recorded safe for temporary use' : 'Do not dispatch'} — {row.notes} <span className="muted">({new Date(row.assessed_at.endsWith('Z') ? row.assessed_at : row.assessed_at.replace(' ', 'T') + 'Z').toLocaleString('en-PH', { timeZone: 'Asia/Manila' })})</span></p>)}
    {saved && <p role="status">Assessment recorded. The Custodian has been notified.</p>}
    {error && <p className="notice error" role="alert">{error}</p>}
    {canAssess && !release && !editing && <button className="ghost-button" type="button" onClick={() => setEditing(true)}>Assess temporary dispatch</button>}
    {canAssess && !release && editing && <form onSubmit={submit} className="dispatch-assessment-form">
      <label>Safe for the intended use?<select name="safe_to_operate" required defaultValue=""><option value="" disabled>Select assessment</option><option value="no">No — keep dispatch blocked</option><option value="yes">Yes — repair can wait for this trip</option></select></label>
      <label className="release-check"><input type="checkbox" name="assembled" /> Vehicle is fully assembled and operational</label>
      <label className="release-check"><input type="checkbox" name="no_safety_critical_fault" /> No unresolved safety-critical fault prevents operation</label>
      <label>Assessment and operating restrictions<textarea name="notes" required maxLength={2000} rows={3} /></label>
      <button type="submit" className="primary-button" disabled={busy}>{busy ? 'Saving…' : 'Record assessment'}</button>
      <button type="button" className="ghost-button" disabled={busy} onClick={() => setEditing(false)}>Cancel</button>
    </form>}
  </section>;
}
