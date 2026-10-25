import './VMSLogo.css';
import { GEAR_BIG, GEAR_COLORS, GEAR_DOTS, GEAR_DOT_RADIUS, GEAR_MIRROR, GEAR_SINGLE_SHARE, GEAR_SINGLE_VIEWBOX, GEAR_SMALL, GEAR_VIEWBOX } from '../assets/vmsGear';

// The big gear spans ~71% of the viewBox; 1.35 matches the old single-gear size, x2 on request.
const MARK_SCALE = 1.35 * 2;

// Single flat colour (currentColor) so the mark never turns multi-coloured.
// `single` draws just the big gear (for loaders), at the same gear size as the full mark.
export default function VMSLogo({ variant = 'white', size = 28, className = '', single = false }) {
  const color = GEAR_COLORS[variant] ?? GEAR_COLORS.white;
  const px = Math.round(size * MARK_SCALE * (single ? GEAR_SINGLE_SHARE : 1));
  if (single) {
    return <svg className={`vms-gear ${className}`} width={px} height={px} viewBox={GEAR_SINGLE_VIEWBOX} style={{ color }} aria-hidden="true" focusable="false">
      <path className="vms-gear-big" d={GEAR_BIG} fill="currentColor" fillRule="evenodd" />
    </svg>;
  }
  return <svg className={`vms-gear ${className}`} width={px} height={px} viewBox={GEAR_VIEWBOX} style={{ color }} aria-hidden="true" focusable="false">
    {/* Mirrored so the small gear sits top-left and the big gear hugs the wordmark. */}
    <g transform={GEAR_MIRROR}>
      <path className="vms-gear-big" d={GEAR_BIG} fill="currentColor" fillRule="evenodd" />
      <path className="vms-gear-small" d={GEAR_SMALL} fill="currentColor" fillRule="evenodd" />
      {GEAR_DOTS.map((dot) => <circle key={`${dot.x}-${dot.y}`} className={`vms-gear-dot vms-gear-dot-${dot.phase}`} cx={dot.x} cy={dot.y} r={GEAR_DOT_RADIUS} fill="currentColor" />)}
    </g>
  </svg>;
}
