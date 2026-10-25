// node scripts/import-boundary-shapefile.cjs ADM4.shp ADM4.dbf OUTPUT_DIRECTORY
// Input: WGS84 Polygon shapefile, with adm4_pcode (PH + ten-digit PSGC).
// Keeps source vertices and holes at six decimal places (~0.1m).
// NAMRIA uses geographic correspondence codes for HUCs; join those using
// the catalog's explicit nine-digit correspondence -> canonical PSGC mapping.
const fs = require('node:fs');
const path = require('node:path');
const zlib = require('node:zlib');
const crypto = require('node:crypto');

const [shpPath, dbfPath, output] = process.argv.slice(2);
if (!output) throw new Error('Expected SHP, DBF, and output directory arguments.');
const catalog = JSON.parse(fs.readFileSync(path.join(__dirname, '../database/data/psgc-barangays.json')));
const cityByCode = new Map(), canonicalCodes = new Set();
for (const [city, rows] of Object.entries(catalog)) {
  for (const b of rows) {
    canonicalCodes.add(b.psgc_code);
    for (const key of new Set([b.psgc_code, b.code.slice(0, 2) + '0' + b.code.slice(2)])) {
      const existing = cityByCode.get(key);
      if (existing && existing.canonical !== b.psgc_code) throw new Error('Ambiguous code correspondence: ' + key);
      cityByCode.set(key, { city, canonical: b.psgc_code });
    }
  }
}
const dbf = fs.readFileSync(dbfPath);
const count = dbf.readUInt32LE(4), header = dbf.readUInt16LE(8), rowSize = dbf.readUInt16LE(10);
const fields = [];
let fieldOffset = 1;
for (let offset = 32; dbf[offset] !== 13; offset += 32) {
  const size = dbf[offset + 16];
  fields.push({ name: dbf.toString('ascii', offset, offset + 11).replace(/\0/g, ''), offset: fieldOffset, size });
  fieldOffset += size;
}
const codeField = fields.find((f) => f.name === 'adm4_pcode');
if (!codeField) throw new Error('Missing adm4_pcode field.');
const codes = [], frequencies = new Map();
for (let i = 0; i < count; i++) {
  const offset = header + i * rowSize;
  const code = dbf[offset] === 42 ? '' : dbf.toString('utf8', offset + codeField.offset, offset + codeField.offset + codeField.size).trim().replace(/^PH/, '');
  codes.push(code);
  frequencies.set(code, (frequencies.get(code) ?? 0) + 1);
}
const inside = (point, ring) => {
  let result = false;
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const [x, y] = ring[i], [px, py] = ring[j];
    if ((y > point[1]) !== (py > point[1]) && point[0] < (px - x) * (point[1] - y) / (py - y) + x) result = !result;
  }
  return result;
};
function geometry(record) {
  if (record.readInt32LE(0) !== 5) throw new Error('Expected a Polygon shapefile record.');
  const parts = record.readInt32LE(36), points = record.readInt32LE(40), pointStart = 44 + parts * 4;
  const rings = [];
  for (let part = 0; part < parts; part++) {
    const begin = record.readInt32LE(44 + part * 4);
    const end = part + 1 < parts ? record.readInt32LE(48 + part * 4) : points;
    const ring = [];
    for (let i = begin; i < end; i++) {
      const x = record.readDoubleLE(pointStart + i * 16), y = record.readDoubleLE(pointStart + i * 16 + 8);
      if (!Number.isFinite(x) || !Number.isFinite(y) || x < 110 || x > 127 || y < 4 || y > 22) throw new Error('Unexpected coordinate system or invalid point: ' + x + ', ' + y);
      ring.push([Math.round(x * 1e6) / 1e6, Math.round(y * 1e6) / 1e6]);
    }
    if (ring.length < 4 || ring[0][0] !== ring.at(-1)[0] || ring[0][1] !== ring.at(-1)[1]) throw new Error('Invalid ring.');
    let area = 0;
    for (let i = 1; i < ring.length; i++) area += ring[i - 1][0] * ring[i][1] - ring[i][0] * ring[i - 1][1];
    rings.push({ ring, area });
  }
  // Shapefile shells are clockwise, holes counterclockwise. GeoJSON uses the reverse.
  const polygons = rings.filter((r) => r.area < 0).map((r) => [r.ring.slice().reverse()]);
  if (!polygons.length) throw new Error('No exterior shell.');
  for (const hole of rings.filter((r) => r.area >= 0)) {
    const parent = polygons.find((p) => inside(hole.ring[0], p[0]));
    if (!parent) throw new Error('Hole has no containing shell.');
    parent.push(hole.ring.slice().reverse());
  }
  return polygons.length === 1 ? { type: 'Polygon', coordinates: polygons[0] } : { type: 'MultiPolygon', coordinates: polygons };
}

fs.mkdirSync(output, { recursive: true });
const staging = fs.mkdtempSync(path.join(require('node:os').tmpdir(), 'vms-boundary-shards-'));
const shp = fs.openSync(shpPath, 'r'), cities = new Set(), matched = new Set();
let offset = 100;
for (let i = 0; i < count; i++) {
  const head = Buffer.alloc(8);
  if (fs.readSync(shp, head, 0, 8, offset) !== 8) throw new Error('Truncated shapefile.');
  const length = head.readInt32BE(4) * 2;
  const code = codes[i], match = cityByCode.get(code);
  if (match && frequencies.get(code) === 1) {
    const { city, canonical } = match;
    if (matched.has(canonical)) throw new Error('Multiple source features map to canonical PSGC ' + canonical);
    const record = Buffer.alloc(length);
    if (fs.readSync(shp, record, 0, length, offset + 8) !== length) throw new Error('Truncated geometry.');
    fs.appendFileSync(path.join(staging, city + '.jsonl'), JSON.stringify([canonical, geometry(record)]) + '\n');
    cities.add(city); matched.add(canonical);
  }
  offset += 8 + length;
}
fs.closeSync(shp);
// Remove only this generator's named city shards; avoid stale shards from an older run.
for (const name of fs.readdirSync(output).filter((name) => /^\d{9}\.json\.gz$/.test(name))) fs.unlinkSync(path.join(output, name));
for (const city of cities) {
  const rows = fs.readFileSync(path.join(staging, city + '.jsonl'), 'utf8').trim().split('\n').map(JSON.parse);
  fs.writeFileSync(path.join(output, city + '.json.gz'), zlib.gzipSync(JSON.stringify(Object.fromEntries(rows)), { level: 9 }));
}
const hash = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
console.log(JSON.stringify({ sourceRecords: count, matched: matched.size, unmatched: canonicalCodes.size - matched.size, duplicateCodes: [...frequencies.values()].filter((n) => n > 1).length, cityShards: cities.size, shpSha256: hash(shpPath), dbfSha256: hash(dbfPath) }, null, 2));
