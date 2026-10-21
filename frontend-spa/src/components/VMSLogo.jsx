import './VMSLogo.css';
import { GEAR_COLORS, GEAR_OUTLINE, GEAR_RING_RADIUS, GEAR_RING_WIDTH, GEAR_VIEWBOX } from '../assets/vmsGear';

export default function VMSLogo({ variant = 'blue', size = 28, className = '' }) {
  const { gear, ring } = GEAR_COLORS[variant] ?? GEAR_COLORS.blue;
  return <svg className={`vms-gear ${className}`} width={size} height={size} viewBox={GEAR_VIEWBOX} aria-hidden="true" focusable="false">
    <path d={GEAR_OUTLINE} fill={gear} stroke="none" />
    <circle r={GEAR_RING_RADIUS} fill="none" stroke={ring} strokeWidth={GEAR_RING_WIDTH} />
  </svg>;
}
