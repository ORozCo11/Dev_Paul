import { lazy, Suspense } from 'react';

const Density = lazy(() => import('./LocationDensityMap'));
const Location = lazy(() => import('./VehicleLocationMap'));
const Add = lazy(() => import('./AddLocationMap'));
const fallback = <div className="map-loading" role="status">Loading map…</div>;

export function LocationDensityMap(props) { return <Suspense fallback={fallback}><Density {...props} /></Suspense>; }
export function VehicleLocationMap(props) { return <Suspense fallback={fallback}><Location {...props} /></Suspense>; }
export function AddLocationMap(props) { return <Suspense fallback={fallback}><Add {...props} /></Suspense>; }
