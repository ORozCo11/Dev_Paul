import { useContext, useEffect, useMemo } from 'react';
import { MapContainer, Marker, Polygon, TileLayer, Tooltip, ZoomControl, useMap } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import TouchMapGuard from './TouchMapGuard';
import { MapBoundaryContext } from '../context/MapBoundaryContext';
import { BOUNDARY_REFERENCE_ATTRIBUTION, geoJsonToPolygons } from '../utils/boundary';

const CARTO_TILE_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}';
const CARTO_ATTRIBUTION = 'Tiles &copy; Esri — Source: Esri, HERE, Garmin, OpenStreetMap contributors';

// A blue teardrop pin as a divIcon — avoids Leaflet's default-marker asset issue
// under bundlers and matches the app's blue brand.
const vehiclePin = L.divIcon({
  className: 'veh-map-pin',
  html: `
    <svg xmlns="http://www.w3.org/2000/svg" width="34" height="42" viewBox="0 0 34 42" aria-hidden="true">
      <path d="M17 1C8.7 1 2 7.7 2 16c0 10.5 13.4 23.6 14 24.2a1.4 1.4 0 0 0 2 0C18.6 39.6 32 26.5 32 16 32 7.7 25.3 1 17 1z" fill="#2563eb" stroke="#ffffff" stroke-width="2"/>
      <circle cx="17" cy="16" r="6" fill="#ffffff"/>
    </svg>`,
  iconSize: [34, 42],
  iconAnchor: [17, 42],
  tooltipAnchor: [0, -38],
});

// This map has no other resize handling — it only gets sized correctly on
// mount/coordinate change via the `key`-based remount below. A ResizeObserver
// on the map's own container catches every other layout-driven resize (e.g.
// a collapsing sidebar widening the panel this map sits in) and tells
// Leaflet to recalculate its tile layout so the map doesn't render into a
// stale, wrongly-sized canvas.
function ResizeMapOnContainerResize() {
  const map = useMap();

  useEffect(() => {
    const container = map.getContainer();
    if (!container || typeof ResizeObserver === 'undefined') return undefined;

    const observer = new ResizeObserver(() => {
      map.invalidateSize();
    });
    observer.observe(container);

    return () => observer.disconnect();
  }, [map]);

  return null;
}

function BoundaryViewport({ polygons, lat, lng }) {
  const map = useMap();
  useEffect(() => {
    if (!polygons.length) return;
    const bounds = L.latLngBounds(polygons.flat(2));
    bounds.extend([Number(lat), Number(lng)]);
    map.fitBounds(bounds, { padding: [24, 24], maxZoom: 16 });
  }, [map, polygons, lat, lng]);
  return null;
}

export default function VehicleLocationMap({ lat, lng, label, scrollWheelZoom = false }) {
  const boundary = useContext(MapBoundaryContext);
  const polygons = useMemo(() => geoJsonToPolygons(boundary?.geometry), [boundary?.geometry]);
  const hasCoords = lat != null && lng != null && !Number.isNaN(Number(lat)) && !Number.isNaN(Number(lng));

  if (!hasCoords) {
    return <div className="veh-map-empty">This location isn&apos;t mapped yet.</div>;
  }

  const center = [Number(lat), Number(lng)];

  return (
    <MapContainer
      key={`${lat},${lng}`}
      center={center}
      zoom={16}
      scrollWheelZoom={scrollWheelZoom}
      className="veh-map-canvas"
      attributionControl
      // The default top-left zoom control sat directly under the Map/
      // Satellite toggle below, making the +/- buttons unreachable —
      // moved to the opposite corner instead of just disabled.
      zoomControl={false}
    >
      <ZoomControl position="bottomright" />
      <ResizeMapOnContainerResize />
      <BoundaryViewport polygons={polygons} lat={lat} lng={lng} />
      <TileLayer url={CARTO_TILE_URL} attribution={`${CARTO_ATTRIBUTION}${polygons.length ? ` | ${BOUNDARY_REFERENCE_ATTRIBUTION}` : ''}`} />
      {polygons.map((positions, index) => (
        <Polygon key={`halo-${index}`} positions={positions} pathOptions={{ color: '#1e293b', weight: 6, opacity: 0.45, fill: false }} />
      ))}
      {polygons.map((positions, index) => (
        <Polygon key={`boundary-${index}`} positions={positions} pathOptions={{ color: '#facc15', weight: 3, opacity: 1, fillColor: '#facc15', fillOpacity: 0.05 }}>
          <Tooltip sticky>{boundary?.label ?? 'Barangay boundary'}</Tooltip>
        </Polygon>
      ))}
      <Marker position={center} icon={vehiclePin}>
        {label && (
          <Tooltip permanent direction="top">{label}</Tooltip>
        )}
      </Marker>
      <TouchMapGuard />
    </MapContainer>
  );
}
