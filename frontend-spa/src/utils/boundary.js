// Shared boundary/geometry helpers — used by both the fleet map (to draw
// the outline) and the "Add Location" form (to reject an address outside
// it), so both always agree on what "inside the boundary" means.

export const BOUNDARY_REFERENCE_ATTRIBUTION = 'Boundary reference data: PSA/NAMRIA via <a href="https://data.humdata.org/dataset/cod-ab-phl">HDX</a> (<a href="https://creativecommons.org/licenses/by/3.0/igo/">CC BY-IGO</a>)';

function isLngLatPair(point) {
  return Array.isArray(point) && point.length >= 2
    && Number.isFinite(point[0]) && Number.isFinite(point[1]);
}

function toLatLngRing(ring) {
  if (!Array.isArray(ring) || !ring.every(isLngLatPair)) {
    console.warn('boundary: skipping malformed boundary ring', ring);
    return null;
  }
  return ring.map(([lng, lat]) => [lat, lng]);
}

// Full Leaflet polygon structure: [shell, ...holes] per disjoint polygon.
// Drawing and containment must preserve holes and islands from the source.
export function geoJsonToPolygons(geometry) {
  if (!geometry) return [];
  const polygons = geometry.type === 'Polygon' ? [geometry.coordinates]
    : geometry.type === 'MultiPolygon' ? geometry.coordinates : [];
  if (!Array.isArray(polygons)) return [];
  return polygons.flatMap((polygon) => {
    if (!Array.isArray(polygon) || !polygon.length) return [];
    const rings = polygon.map((ring) => Array.isArray(ring) && ring.length >= 4 ? toLatLngRing(ring) : null);
    // Never drop a malformed hole and accidentally allow locations inside it.
    return rings.every(Boolean) ? [rings] : [];
  });
}

export function isPointWithinBoundaryPolygons(point, polygons) {
  if (!Number.isFinite(point?.lat) || !Number.isFinite(point?.lng) || !polygons?.length) return false;
  return polygons.some(([shell, ...holes]) => isPointInRing(point.lat, point.lng, shell)
    && !holes.some((hole) => isPointInRing(point.lat, point.lng, hole)));
}

export function isLocationPointAllowed(point, polygons, verified) {
  if (!Number.isFinite(point?.lat) || !Number.isFinite(point?.lng) || Math.abs(point.lat) > 90 || Math.abs(point.lng) > 180) return false;
  return !verified || isPointWithinBoundaryPolygons(point, polygons);
}

// Place a reference label inside the largest polygon, avoiding holes and
// concave-shape centroids that can fall outside the actual boundary.
export function boundaryLabelPoint(polygons) {
  if (!polygons?.length) return null;
  const area = (ring) => Math.abs(ring.reduce((sum, [lat, lng], i) => {
    const next = ring[(i + 1) % ring.length];
    return sum + lng * next[0] - next[1] * lat;
  }, 0));
  const polygon = polygons.reduce((largest, candidate) => area(candidate[0]) > area(largest[0]) ? candidate : largest);
  const bounds = polygon[0].reduce((b, [lat, lng]) => ({
    south: Math.min(b.south, lat), north: Math.max(b.north, lat),
    west: Math.min(b.west, lng), east: Math.max(b.east, lng),
  }), { south: Infinity, north: -Infinity, west: Infinity, east: -Infinity });
  const center = { lat: (bounds.south + bounds.north) / 2, lng: (bounds.west + bounds.east) / 2 };
  if (isPointWithinBoundaryPolygons(center, [polygon])) return [center.lat, center.lng];
  let best = null, widest = 0;
  for (let sample = 1; sample < 24; sample++) {
    const lat = bounds.south + (bounds.north - bounds.south) * sample / 24;
    const intersections = [];
    for (const ring of polygon) {
      for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const [a, x] = ring[i], [b, y] = ring[j];
        if ((a > lat) !== (b > lat)) intersections.push(x + (y - x) * (lat - a) / (b - a));
      }
    }
    intersections.sort((a, b) => a - b);
    for (let i = 0; i + 1 < intersections.length; i += 2) {
      const width = intersections[i + 1] - intersections[i];
      if (width > widest) { widest = width; best = [lat, (intersections[i] + intersections[i + 1]) / 2]; }
    }
  }
  return best;
}

// Ray-casting point-in-polygon test (standard even-odd algorithm). `ring`
// is an array of [lat, lng] pairs — Leaflet's convention, used throughout
// this app, NOT GeoJSON's [lng, lat].
function isPointInRing(lat, lng, ring) {
  let inside = false;
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const [latI, lngI] = ring[i];
    const [latJ, lngJ] = ring[j];
    const intersects = ((lngI > lng) !== (lngJ > lng))
      && (lat < ((latJ - latI) * (lng - lngI)) / (lngJ - lngI) + latI);
    if (intersects) inside = !inside;
  }
  return inside;
}
