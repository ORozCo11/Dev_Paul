import { lazy, Suspense } from 'react';

const Files = lazy(() => import('./FileCabinet'));
const RepairFiles = lazy(() => import('./RepairFileCabinet'));
const fallback = <div role="status">Loading files…</div>;

export function FileCabinet(props) { return <Suspense fallback={fallback}><Files {...props} /></Suspense>; }
export function RepairFileCabinet(props) { return <Suspense fallback={fallback}><RepairFiles {...props} /></Suspense>; }
