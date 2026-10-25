// Eight flat-tipped, tapered teeth using the Realcore reference proportions.
// The recessed circular roots leave distinct gaps between adjacent teeth.
export const GEAR_VIEWBOX = '-50 -50 100 100';
export const GEAR_TEETH = 8;
export const GEAR_RING_RADIUS = 24;
export const GEAR_RING_WIDTH = 4.5;

function point(x, y, sector) {
  const angle = sector * Math.PI / 4;
  return `${(x * Math.cos(angle) - y * Math.sin(angle)).toFixed(3)} ${(x * Math.sin(angle) + y * Math.cos(angle)).toFixed(3)}`;
}

function gearOutline() {
  const rootRadius = 38.5;
  const neckHalfWidth = 10.8;
  const neckY = -Math.sqrt(rootRadius ** 2 - neckHalfWidth ** 2);
  let path = `M ${point(-neckHalfWidth, neckY, 0)}`;
  for (let i = 0; i < GEAR_TEETH; i++) {
    path += ` L ${point(-7.3, -48, i)}`;
    path += ` L ${point(7.3, -48, i)}`;
    path += ` L ${point(neckHalfWidth, neckY, i)}`;
    path += ` A ${rootRadius} ${rootRadius} 0 0 1 ${point(-neckHalfWidth, neckY, i + 1)}`;
  }
  return `${path} Z`;
}

export const GEAR_OUTLINE = gearOutline();

export const GEAR_COLORS = {
  blue: { gear: '#2563eb', ring: '#ffffff' },
  white: { gear: '#ffffff', ring: '#2563eb' },
};
