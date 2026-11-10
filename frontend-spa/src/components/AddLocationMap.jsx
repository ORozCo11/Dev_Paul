import { useEffect, useMemo } from 'react';
import { MapContainer, Marker, Polygon, TileLayer, useMap, useMapEvents } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import TouchMapGuard from './TouchMapGuard';
import { BOUNDARY_REFERENCE_ATTRIBUTION, isPointWithinBoundaryPolygons } from '../utils/boundary';

const STREET_TILE_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}';
const STREET_ATTRIBUTION = 'Tiles &copy; Esri — Source: Esri, HERE, Garmin, OpenStreetMap contributors';

// Same blue teardrop pin as VehicleLocationMap — not shared from there since
// that component doesn't export it, and duplicating one small icon constant
// is cheaper than restructuring an already-working, unrelated component.
const pickPin = L.divIcon({
  className: 'veh-map-pin',
  html: `
    <svg xmlns="http://www.w3.org/2000/svg" width="34" height="42" viewBox="0 0 34 42" aria-hidden="true">
      <path d="M17 1C8.7 1 2 7.7 2 16c0 10.5 13.4 23.6 14 24.2a1.4 1.4 0 0 0 2 0C18.6 39.6 32 26.5 32 16 32 7.7 25.3 1 17 1z" fill="#2563eb" stroke="#ffffff" stroke-width="2"/>
      <circle cx="17" cy="16" r="6" fill="#ffffff"/>
    </svg>`,
  iconSize: [34, 42],
  iconAnchor: [17, 42],
});

function ClickToPick({ onPick }) {
  useMapEvents({
    click(e) {
      onPick({ lat: e.latlng.lat, lng: e.latlng.lng });
    },
  });
  return null;
}

// Address typed in the form re-centers the map on the geocoded point —
// clicking the map itself already puts the view there, so this only needs
// to react to marker changes that came from OUTSIDE a click (i.e. typing).
function FlyToMarker({ marker }) {
  const map = useMap();
  useEffect(() => {
    if (!marker) return;
    map.flyTo([marker.lat, marker.lng], Math.max(map.getZoom(), 16), { animate: true, duration: 0.6 });
  }, [marker, map]);
  return null;
}

// Same resize-on-container-change pattern as the other two map components —
// Leaflet only measures its container at mount, so a sidebar collapse/expand
// or any other layout-driven resize needs an explicit invalidateSize() or
// the map keeps rendering into a stale-sized canvas.
function ResizeMapOnContainerResize() {
  const map = useMap();

  useEffect(() => {
    const container = map.getContainer();
    if (!container || typeof ResizeObserver === 'undefined') return undefined;

    const observer = new ResizeObserver(() => map.invalidateSize());
    observer.observe(container);

    return () => observer.disconnect();
  }, [map]);

  return null;
}

// Focused, single-purpose map for the "Add Location" page: click anywhere
// to drop/move the pin (the parent reverse-geocodes it into the Address
// field), and re-centers itself when the parent moves the pin instead (the
// admin typed an address that got geocoded). The service-area boundary is
// drawn so it's obvious at a glance where a click will actually be accepted.
export default function AddLocationMap({ marker, boundaryPolygons, onPick: onPickAnywhere, onOutside }) {
  const bounds = useMemo(() => boundaryPolygons.length ? L.latLngBounds(boundaryPolygons.flat(2)) : null, [boundaryPolygons]);
  if (!bounds) return <p role="status">The selected barangay has no boundary reference yet. Ask the system administrator to review its boundary before adding a location.</p>;
  const initialCenter = bounds.getCenter();
  // A pin outside the barangay outline is refused: the marker stays where it was.
  const onPick = (point) => {
    if (isPointWithinBoundaryPolygons(point, boundaryPolygons)) onPickAnywhere(point);
    else onOutside?.(point);
  };

  return (
    <MapContainer
      center={[initialCenter.lat, initialCenter.lng]}
      zoom={17}
      maxZoom={19}
      scrollWheelZoom
      className="add-location-map-canvas"
      attributionControl
    >
      <ResizeMapOnContainerResize />
      <TileLayer url={STREET_TILE_URL} attribution={`${STREET_ATTRIBUTION} | ${BOUNDARY_REFERENCE_ATTRIBUTION}`} maxZoom={19} crossOrigin="anonymous" />
      <ClickToPick onPick={onPick} />
      {boundaryPolygons.map((ring, index) => (
        <Polygon
          key={index}
          positions={ring}
          pathOptions={{ color: '#facc15', fillColor: '#facc15', fillOpacity: 0.05, opacity: 1, weight: 3 }}
        />
      ))}
      {/* The pin can be dragged for a precise final position. */}
      {marker && (
        <Marker
          position={[marker.lat, marker.lng]}
          icon={pickPin}
          draggable
          eventHandlers={{ dragend: (event) => { const { lat, lng } = event.target.getLatLng(); if (isPointWithinBoundaryPolygons({ lat, lng }, boundaryPolygons)) onPickAnywhere({ lat, lng }); else { event.target.setLatLng([marker.lat, marker.lng]); onOutside?.({ lat, lng }); } } }}
        />
      )}
      <FlyToMarker marker={marker} />
      <TouchMapGuard />
    </MapContainer>
  );
}
