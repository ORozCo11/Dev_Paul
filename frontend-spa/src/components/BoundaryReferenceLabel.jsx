import { useMemo } from 'react';
import { CircleMarker, Tooltip } from 'react-leaflet';
import { boundaryLabelPoint } from '../utils/boundary';

export default function BoundaryReferenceLabel({ polygons, label, locality }) {
  const point = useMemo(() => boundaryLabelPoint(polygons), [polygons]);
  if (!point || !label) return null;
  return <CircleMarker center={point} radius={0} interactive={false} pathOptions={{ stroke: false, fill: false }}>
    <Tooltip permanent direction="center" className="boundary-reference-label">
      <small>Barangay</small>
      <strong>{label}</strong>
      {locality && <span>{locality}</span>}
    </Tooltip>
  </CircleMarker>;
}
