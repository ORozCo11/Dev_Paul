import L from 'leaflet';
import Icon from './Icon';

export default function BoundaryMapDetails({ name, locality, verified, source, psgcCode }) {
  const sourceLabel = source === 'legacy' ? 'Original reference; source not recorded'
    : source === 'namria_hdx_reference_2025' ? 'PSA / NAMRIA reference via HDX'
    : source?.replace(/^uploaded_reference:\s*/, '');

  return (
    <details className="boundary-map-details" ref={(node) => {
      if (!node) return;
      L.DomEvent.disableClickPropagation(node);
      L.DomEvent.disableScrollPropagation(node);
    }}>
      <summary aria-label={`Boundary details for Barangay ${name}`}>
        <span className="boundary-map-identity">
          <span>Barangay</span>
          <strong>{name}</strong>
          {locality && <small>{locality}</small>}
        </span>
        <span className="boundary-map-details-toggle">Boundary details<Icon name="chevronDown" size={14} /></span>
      </summary>
      <div className="boundary-map-details-body">
        <dl>
          <div><dt>Status</dt><dd>{verified ? 'Reviewed boundary' : 'Unverified reference'}</dd></div>
          {psgcCode && <div><dt>PSGC code</dt><dd>{psgcCode}</dd></div>}
          {sourceLabel && <div><dt>Source</dt><dd>{sourceLabel}</dd></div>}
        </dl>
        <p>{verified ? 'Reviewed by a Super Admin against an uploaded source document.' : 'This reference outline is awaiting review. A Super Admin can review or replace it in Barangay Management.'}</p>
      </div>
    </details>
  );
}
