import { useEffect, useState } from 'react';
import api from '../api/axios';

export default function AddressSuggestions({ barangayId, query, active }) {
  const [enabled, setEnabled] = useState(false);
  const [result, setResult] = useState({ key: '', rows: [] });
  const key = `${barangayId}:${query}`;
  useEffect(() => {
    if (!enabled || !active || !barangayId || query.trim().length < 3 || query.length > 150) return;
    const controller = new AbortController();
    const timer = setTimeout(() => {
      api.get('/address-suggestions', { params: { barangay_id: barangayId, q: query.trim() }, signal: controller.signal })
        .then(({ data }) => { if (!controller.signal.aborted) setResult({ key, rows: Array.isArray(data) ? data : [] }); })
        .catch(() => { if (!controller.signal.aborted) setResult({ key, rows: [] }); });
    }, 700);
    return () => { clearTimeout(timer); controller.abort(); };
  }, [enabled, active, barangayId, query, key]);
  return <><label className="register-address-consent"><input type="checkbox" checked={enabled} onChange={(event) => setEnabled(event.target.checked)} /> Allow address suggestions: send my typed address and selected location to Photon / OpenStreetMap.</label><datalist id="registration-address-suggestions">{enabled && active && result.key === key && result.rows.map((address) => <option key={address} value={address} />)}</datalist></>;
}
