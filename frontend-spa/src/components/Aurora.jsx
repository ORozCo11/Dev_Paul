import { useEffect, useRef } from 'react';

export default function Aurora({
  colorStops = ['#00d2ff', '#7b2ff7', '#ff6edf'],
  amplitude = 1,
  blend = 0.5,
}) {
  const containerRef = useRef(null);

  useEffect(() => {
    const el = containerRef.current;
    if (!el) return;
    el.style.setProperty('--aurora-amplitude', amplitude);
    el.style.setProperty('--aurora-blend', blend);
    colorStops.forEach((color, i) => {
      el.style.setProperty(`--aurora-color-${i}`, color);
    });
  }, [colorStops, amplitude, blend]);

  return (
    <div ref={containerRef} className="aurora-root" aria-hidden="true">
      {colorStops.map((color, i) => (
        <div key={i} className={`aurora-blob aurora-blob-${i}`} style={{ background: color }} />
      ))}
      <div className="aurora-overlay" />
    </div>
  );
}
