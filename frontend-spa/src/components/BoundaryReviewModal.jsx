import { useEffect, useMemo, useRef, useState } from 'react';
import { MapContainer, Polygon, TileLayer } from 'react-leaflet';
import api from '../api/axios';
import Icon from './Icon';
import FileCabinet from './FileCabinet';
import { geoJsonToPolygons } from '../utils/boundary';

// Matches the API: every ring repeats its first point as its last.
function hasClosedRings(geometry) {
  const polygons = geometry?.type === 'Polygon' ? [geometry.coordinates] : geometry?.type === 'MultiPolygon' ? geometry.coordinates : [];
  return polygons.every((polygon) => polygon.every((ring) => {
    const first = ring[0];
    const last = ring[ring.length - 1];
    return first?.[0] === last?.[0] && first?.[1] === last?.[1];
  }));
}

function savedSourceIsUpload(source) {
  return source.startsWith('uploaded_reference: ');
}

export default function BoundaryReviewModal({ barangay, onClose, onConfirm, onDiscard, onSaved }) {
  const [geometry, setGeometry] = useState(barangay.pending_boundary ?? null);
  const [savedGeometry, setSavedGeometry] = useState(barangay.pending_boundary ?? null);
  const [hasCandidate, setHasCandidate] = useState(Boolean(barangay.pending_boundary));
  const [pendingSource, setPendingSource] = useState(barangay.pending_boundary_source ?? '');
  const [files, setFiles] = useState([]);
  const [document, setDocument] = useState(null);
  const [source, setSource] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const readVersion = useRef(0);
  useEffect(() => {
    if (barangay.pending_boundary) return;
    let current = true;
    api.get(`/barangays/${barangay.id}`).then(({ data }) => {
      if (current && readVersion.current === 0) { setGeometry(data.boundary); setSavedGeometry(data.boundary); }
    }).catch(() => { if (current) setError('Could not load the current outline. You can still choose a replacement file.'); });
    return () => { current = false; };
  }, [barangay.id, barangay.pending_boundary]);
  const polygons = useMemo(() => geoJsonToPolygons(geometry), [geometry]);
  const changeFile = async (next) => {
    const version = ++readVersion.current;
    setFiles(next); setDocument(null); setError('');
    if (!next.length) { setGeometry(savedGeometry); return; }
    try {
      if (next[0].size > 5 * 1024 * 1024) throw new Error('Choose a GeoJSON file smaller than 5 MB.');
      const json = JSON.parse(await next[0].text());
      if (version !== readVersion.current) return;
      if (json.type === 'FeatureCollection' && json.features?.length !== 1) throw new Error('The file must contain exactly one barangay feature.');
      const feature = json.type === 'FeatureCollection' ? json.features[0] : json;
      const candidate = feature.type === 'Feature' ? feature.geometry : feature;
      if (!geoJsonToPolygons(candidate).length) throw new Error('The file must contain a Polygon or MultiPolygon.');
      if (!hasClosedRings(candidate)) throw new Error('Every ring must be closed: its last point must repeat its first point.');
      setDocument(json); setGeometry(candidate);
    } catch (failure) { if (version === readVersion.current) { setError(failure.message); setGeometry(savedGeometry); } }
  };
  const save = async () => {
    setBusy(true); setError('');
    try {
      const { data } = await api.post(`/superadmin/barangays/${barangay.id}/boundary/candidate`, { geojson: document, source: source.trim(), psgc_code: barangay.psgc_code ?? null });
      setGeometry(data.pending_boundary); setSavedGeometry(data.pending_boundary); setPendingSource(data.pending_boundary_source ?? ''); setHasCandidate(true); setFiles([]); setDocument(null);
      await onSaved?.();
    } catch (failure) { setError(failure.response?.data?.message ?? 'Could not save the replacement boundary.'); }
    finally { setBusy(false); }
  };
  // Only an uploaded file naming its source document becomes a reviewed boundary.
  const candidateIsReference = hasCandidate && !savedSourceIsUpload(pendingSource) && !document;
  const run = async (action) => { setBusy(true); try { await action(); } finally { setBusy(false); } };
  return <div className="modal-overlay" onClick={busy ? undefined : onClose}>
    <div className="modal-box boundary-review-dialog" role="dialog" aria-modal="true" aria-label="Review barangay boundary" onClick={(event) => event.stopPropagation()}>
      <div className="modal-header"><h3>Review Boundary — {barangay.name}</h3><button type="button" className="modal-close-btn" disabled={busy} onClick={onClose} aria-label="Close"><Icon name="close" size={18} /></button></div>
      <div className="modal-body">
        <p className="muted">{barangay.city_name} · PSGC {barangay.psgc_code ?? 'not assigned'}. Imported outlines are reference data. Confirm a boundary after checking an LGU-approved map or survey.</p>
        {polygons.length > 0 && <div style={{ height: 280, margin: '12px 0' }}><MapContainer key={JSON.stringify(geometry)} bounds={polygons.flat(2)} style={{ height: '100%', width: '100%' }} scrollWheelZoom>
          <TileLayer attribution="&copy; OpenStreetMap contributors" url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
          {polygons.map((polygon, index) => <Polygon key={index} positions={polygon} pathOptions={{ color: '#d97706', fillOpacity: 0.15 }} />)}
        </MapContainer></div>}
        <FileCabinet name="boundary_geojson" title="Replacement boundary" accept=".json,.geojson" files={files} onChange={changeFile} />
        <label>Source document or URL<input value={source} maxLength={200} onChange={(event) => setSource(event.target.value)} placeholder="LGU-approved boundary map or survey reference" /></label>
        <p className="muted">Upload one WGS84 GeoJSON Polygon, MultiPolygon, or single-feature file. Saving stages a replacement; confirmation applies it to existing accounts and future registrations and marks it as reviewed against the named source.</p>
        {candidateIsReference && <p className="notice info">This candidate came from {pendingSource === 'osm_nominatim' ? 'an OpenStreetMap lookup' : 'imported reference data'}. Confirming it applies the outline as an unverified reference; it will not restrict location pins. Upload an LGU-approved boundary file to mark it reviewed.</p>}
        {error && <p className="notice error" role="alert">{error}</p>}
        <div className="form-actions" style={{ marginTop: 12 }}>
          <button type="button" className="ghost-button" disabled={busy || !hasCandidate || files.length > 0} onClick={() => run(onDiscard)}>Discard candidate</button>
          <button type="button" className="primary-button" disabled={busy || !document || !source.trim()} onClick={save}>Save for review</button>
          <button type="button" className="success-button" disabled={busy || !hasCandidate || files.length > 0 || !polygons.length} onClick={() => run(onConfirm)}>Confirm Boundary</button>
        </div>
      </div>
    </div>
  </div>;
}
