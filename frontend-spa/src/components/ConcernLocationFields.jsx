import { useEffect, useId, useState } from 'react';
import api from '../api/axios';

// The system serves one municipality, so the reporter only picks a barangay; the city and province follow from it.
export default function ConcernLocationFields({ value, onChange }) {
  const id = useId();
  const [barangays, setBarangays] = useState([]);
  const [lookupFailed, setLookupFailed] = useState(false);
  useEffect(() => {
    let current = true;
    api.get('/barangays/registered').then(({ data }) => { if (current) setBarangays(data); })
      .catch(() => { if (current) setLookupFailed(true); });
    return () => { current = false; };
  }, []);
  const pick = (name) => {
    const row = barangays.find((item) => item.name === name);
    onChange({ barangay_name: name, city_name: row?.city_name ?? '', province_name: row?.province_name ?? '' });
  };
  return <>
    <label className="auth-field auth-field-full">
      <span>Barangay</span>
      <div className="auth-input-wrapper auth-input-plain">
        {lookupFailed
          ? <input name="barangay_name" value={value.barangay_name ?? ''} maxLength={255} autoComplete="off" placeholder="Type your barangay" onChange={(event) => onChange({ barangay_name: event.target.value })} />
          : <select id={`${id}-barangay`} name="barangay_name" className="concern-location-select" value={value.barangay_name ?? ''} onChange={(event) => pick(event.target.value)}>
            <option value="">Select barangay</option>
            {barangays.map((row) => <option key={row.id} value={row.name}>{row.name}</option>)}
          </select>}
      </div>
    </label>
    <p className="auth-field-full muted">Barangay is optional.{lookupFailed ? ' The barangay list is unavailable, so please type it instead.' : ''}</p>
  </>;
}
