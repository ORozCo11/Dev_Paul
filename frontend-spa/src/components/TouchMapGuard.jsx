import { useEffect, useRef, useState } from 'react';
import { useMap } from 'react-leaflet';
import L from 'leaflet';

// On touch screens a Leaflet map grabs every one-finger swipe, so the page
// can't be scrolled past it. This keeps one-finger panning off until the
// user taps "Move map" (pinch-to-zoom still works). Mouse users are
// unaffected — the control only renders on coarse-pointer devices.
// Render it as a child of <MapContainer>.
const isTouchDevice = () => typeof window !== 'undefined' && window.matchMedia?.('(pointer: coarse)').matches;

export default function TouchMapGuard() {
  const map = useMap();
  const [touch] = useState(isTouchDevice);
  const [unlocked, setUnlocked] = useState(false);
  const ref = useRef(null);

  useEffect(() => {
    if (!ref.current) return;
    L.DomEvent.disableClickPropagation(ref.current);
    L.DomEvent.disableScrollPropagation(ref.current);
  }, [touch]);

  useEffect(() => {
    if (!touch || !map.dragging) return;
    if (unlocked) map.dragging.enable();
    else map.dragging.disable();
  }, [map, touch, unlocked]);

  if (!touch) return null;

  return (
    <div ref={ref} className="map-touch-guard">
      <button type="button" aria-pressed={unlocked} onClick={() => setUnlocked((v) => !v)}>
        {unlocked ? 'Done moving map' : 'Tap to move map'}
      </button>
    </div>
  );
}
