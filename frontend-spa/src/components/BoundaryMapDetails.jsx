import L from 'leaflet';

export default function BoundaryMapDetails({ name, locality }) {
  return (
    <div className="boundary-map-details" aria-label={`Barangay ${name}`} ref={(node) => {
      if (!node) return;
      L.DomEvent.disableClickPropagation(node);
      L.DomEvent.disableScrollPropagation(node);
    }}>
      <div className="boundary-map-heading">
        <span className="boundary-map-identity">
          <span>Barangay</span>
          <strong>{name}</strong>
          {locality && <small>{locality}</small>}
        </span>
      </div>
    </div>
  );
}
