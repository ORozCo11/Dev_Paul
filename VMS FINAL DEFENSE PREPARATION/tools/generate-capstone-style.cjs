/**
 * Capstone-style renderings of the three primary defense diagrams.
 *
 * Replaces the Graphviz output of generate-diagrams.cjs for:
 *   ERD, Use-Case-Diagram, Use-Case-Platform, System-Architecture
 * The look follows the SkinSense capstone paper: navy-header entity tables with
 * crow's-foot lines and verb labels, stick-figure actors with colored ovals, and a
 * layered architecture sheet. Column names are validated against sources/schema.json.
 *
 * Dependencies: npm install --prefix <tools-dir> sharp
 */
const fs = require('fs');
const path = require('path');

const deps = process.env.VMS_DIAGRAM_TOOLS || path.join(require('os').tmpdir(), 'vms-defense-diagram-tools');
const sharp = require(path.join(deps, 'node_modules/sharp'));
const base = path.resolve(__dirname, '..');
const schema = JSON.parse(fs.readFileSync(path.join(base, 'sources/schema.json'), 'utf8')).tables;

const NAVY = '#2430a0';
const INK = '#161616';
const SERIF = "Georgia, 'Times New Roman', serif";
const SANS = "Arial, Helvetica, sans-serif";
const REVISION = '352fd0e';
const DATE = '25 October 2026';

const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const f = n => Math.round(n * 10) / 10;

function doc(w, h, body) {
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}" font-family="${SANS}">` +
    `<rect width="${w}" height="${h}" fill="#fff"/>${body}</svg>`;
}

function titleBlock(w, small, big, sub) {
  const sw = small.length * 15 + 30;
  return `<text x="${w / 2}" y="78" text-anchor="middle" font-family="${SERIF}" font-size="34" fill="${INK}">${esc(small)}</text>` +
    `<line x1="${w / 2 - sw / 2}" y1="92" x2="${w / 2 + sw / 2}" y2="92" stroke="${INK}" stroke-width="2.5"/>` +
    `<text x="${w / 2}" y="164" text-anchor="middle" font-family="${SERIF}" font-size="70" font-weight="bold" fill="${INK}">${esc(big)}</text>` +
    (sub ? `<text x="${w / 2}" y="208" text-anchor="middle" font-size="19" fill="#4b5563">${esc(sub)}</text>` : '');
}

/* ------------------------------------------------------------------ ERD */
const EW = 310, HEAD = 42, ROW = 29;

function entity(name, cols, x, y) {
  const t = schema[name];
  if (!t) throw Error('Unknown table: ' + name);
  const rows = cols.map(c => {
    const col = t.columns.find(k => k.name === c);
    if (!col) throw Error(name + '.' + c + ' does not exist');
    const fk = t.foreign_keys.some(k => k.from === c);
    return { name: c, key: col.pk ? (fk ? 'PK, FK' : 'PK') : fk ? 'FK' : '', nullable: !col.pk && !col.notnull };
  });
  const h = HEAD + ROW * rows.length;
  let s = `<rect x="${x}" y="${y}" width="${EW}" height="${h}" fill="#fff" stroke="${INK}" stroke-width="1.8"/>` +
    `<rect x="${x}" y="${y}" width="${EW}" height="${HEAD}" fill="${NAVY}" stroke="${INK}" stroke-width="1.8"/>` +
    `<text x="${x + EW / 2}" y="${y + 28}" text-anchor="middle" font-size="19" font-weight="bold" fill="#fff">${esc(name)}</text>`;
  rows.forEach((r, i) => {
    const ry = y + HEAD + ROW * i;
    if (i > 0) s += `<line x1="${x}" y1="${ry}" x2="${x + EW}" y2="${ry}" stroke="#e5e7f0" stroke-width="1"/>`;
    if (r.key) s += `<text x="${x + 12}" y="${ry + 20}" font-size="14.5" font-weight="bold" fill="${NAVY}">${r.key}</text>`;
    s += `<text x="${x + 76}" y="${ry + 20}" font-size="15.5" fill="${INK}">${esc(r.name)}${r.nullable ? '<tspan fill="#6b7280"> ?</tspan>' : ''}</text>`;
  });
  return { name, x, y, w: EW, h, cols, svg: s, right: x + EW, bottom: y + h, rowY: c => {
    const i = cols.indexOf(c);
    if (i < 0) throw Error(name + '.' + c + ' not in diagram');
    return y + HEAD + ROW * (i + 0.5);
  } };
}

/** Crow's-foot / bar / circle markers at an entity edge. u = unit vector pointing away from the entity. */
function marker(p, u, kind) {
  const n = { x: -u.y, y: u.x };
  const at = (d, o = 0) => ({ x: p.x + u.x * d + n.x * o, y: p.y + u.y * d + n.y * o });
  const ln = (a, b) => `<line x1="${f(a.x)}" y1="${f(a.y)}" x2="${f(b.x)}" y2="${f(b.y)}" stroke="${INK}" stroke-width="2.2"/>`;
  const tick = d => ln(at(d, 10), at(d, -10));
  const circle = d => { const c = at(d); return `<circle cx="${f(c.x)}" cy="${f(c.y)}" r="6.5" fill="#fff" stroke="${INK}" stroke-width="2.2"/>`; };
  const crow = () => ln(at(19), at(0, 11)) + ln(at(19), at(0)) + ln(at(19), at(0, -11));
  if (kind === 'one') return tick(12) + tick(20);
  if (kind === 'zeroone') return tick(12) + circle(26);
  if (kind === 'many') return crow() + circle(30);
  throw Error('marker ' + kind);
}

function relation(pts, parentKind, label, opts = {}) {
  const d = 'M' + pts.map(p => `${f(p.x)},${f(p.y)}`).join(' L');
  const dir = (a, b) => { const dx = b.x - a.x, dy = b.y - a.y, l = Math.hypot(dx, dy) || 1; return { x: dx / l, y: dy / l }; };
  const a = pts[0], b = pts[pts.length - 1];
  let s = `<path d="${d}" fill="none" stroke="${INK}" stroke-width="2" stroke-linejoin="round"${opts.dashed ? ' stroke-dasharray="9 6"' : ''}/>`;
  s += marker(a, dir(a, pts[1]), parentKind);
  s += marker(b, dir(b, pts[pts.length - 2]), 'many');
  if (label) {
    const { x, y, anchor = 'middle' } = opts.at;
    s += `<text x="${x}" y="${y}" text-anchor="${anchor}" font-size="15" fill="${INK}" stroke="#fff" stroke-width="6" paint-order="stroke" stroke-linejoin="round">${esc(label)}</text>`;
  }
  return s;
}

function buildERD() {
  const W = 2600, TOP = 310, H = TOP + 1590;
  const defs = {
    users: ['id', 'barangay_id', 'name', 'email', 'roles', 'is_active', 'awaiting_staff_code', 'staff_code_sent_at'],
    barangays: ['id', 'city_id', 'name', 'boundary_status'],
    vehicle_categories: ['category_id', 'category_name', 'domain', 'default_criticality'],
    vehicles: ['vehicle_id', 'barangay_id', 'category_id', 'vehicle_name', 'plate_number', 'status', 'condition', 'readiness_invalidated_at'],
    vehicle_issue_reports: ['issue_report_id', 'vehicle_id', 'reported_by', 'issue_type', 'severity_level', 'status'],
    maintenance_tickets: ['ticket_id', 'vehicle_id', 'issue_report_id', 'assigned_custodian_id', 'assigned_mechanic_id', 'priority', 'status', 'decision_revision'],
    vehicle_maintenance_schedules: ['schedule_id', 'vehicle_id', 'created_by', 'assigned_to', 'resulting_ticket_id', 'resulting_maintenance_id', 'scheduled_date', 'recurrence_months', 'status'],
    vehicle_condition_checks: ['condition_check_id', 'vehicle_id', 'checked_by', 'condition_result', 'resulting_ticket_id'],
    vehicle_readiness_checks: ['readiness_check_id', 'vehicle_id', 'checked_by', 'all_passed', 'checked_at'],
    ticket_sub_issues: ['sub_issue_id', 'ticket_id', 'assigned_mechanic_id', 'source_vehicle_id', 'repair_type', 'status', 'work_history'],
    vehicle_maintenance_records: ['maintenance_id', 'vehicle_id', 'ticket_sub_issue_id', 'maintenance_personnel_id', 'verified_by', 'confirmed_by', 'progress_status', 'date_completed'],
    hidden_ticket_lists: ['user_id', 'ticket_id', 'created_at'],
  };
  const C1 = 80, C2 = 580, C3 = 1150, C4 = 1650, C5 = 2150;
  const E = {
    users: entity('users', defs.users, C1, TOP),
    barangays: entity('barangays', defs.barangays, C1, TOP + 420),
    cats: entity('vehicle_categories', defs.vehicle_categories, C1, TOP + 700),
    vehicles: entity('vehicles', defs.vehicles, C2, TOP + 380),
    ir: entity('vehicle_issue_reports', defs.vehicle_issue_reports, C3, TOP),
    tk: entity('maintenance_tickets', defs.maintenance_tickets, C3, TOP + 310),
    sch: entity('vehicle_maintenance_schedules', defs.vehicle_maintenance_schedules, C3, TOP + 690),
    cc: entity('vehicle_condition_checks', defs.vehicle_condition_checks, C3, TOP + 1050),
    rc: entity('vehicle_readiness_checks', defs.vehicle_readiness_checks, C3, TOP + 1300),
    hid: entity('hidden_ticket_lists', defs.hidden_ticket_lists, C4, TOP + 90),
    sub: entity('ticket_sub_issues', defs.ticket_sub_issues, C4, TOP + 310),
    rec: entity('vehicle_maintenance_records', defs.vehicle_maintenance_records, C5, TOP + 310),
  };
  const nn = (child, col) => (schema[child].columns.find(c => c.name === col).notnull ? 'one' : 'zeroone');
  const enforced = (child, col, parent) => schema[child].foreign_keys.some(k => k.from === col && k.table === parent);
  const P = (x, y) => ({ x, y });
  let rel = '';

  // vehicles -> five children share a bus on the right-hand side
  const v = E.vehicles;
  const bus = [
    ['ir', 'vehicle_issue_reports', v.y + 22, 940, 'has'],
    ['tk', 'maintenance_tickets', v.y + 52, 962, 'has'],
    ['sch', 'vehicle_maintenance_schedules', v.y + 180, 984, 'is scheduled'],
    ['cc', 'vehicle_condition_checks', v.y + 210, 962, 'undergoes'],
    ['rc', 'vehicle_readiness_checks', v.y + 240, 940, 'attests'],
  ];
  for (const [k, tbl, ay, cx, verb] of bus) {
    const c = E[k], ty = c.rowY('vehicle_id');
    rel += relation([P(v.right, ay), P(cx, ay), P(cx, ty), P(c.x, ty)], nn(tbl, 'vehicle_id'), verb,
      { at: { x: (cx + c.x) / 2 + 22, y: ty - 9 } });
  }

  const byBar = E.barangays.rowY ? v.rowY('barangay_id') : 0;
  rel += relation([P(E.barangays.right, byBar), P(v.x, byBar)], nn('vehicles', 'barangay_id'), 'has', { at: { x: (E.barangays.right + v.x) / 2, y: byBar - 9 } });
  const cy = E.cats.y + HEAD + ROW * 1.5, vcy = v.rowY('category_id');
  rel += relation([P(E.cats.right, cy), P(485, cy), P(485, vcy), P(v.x, vcy)], nn('vehicles', 'category_id'), 'classifies', { at: { x: 438, y: cy - 9 } });
  rel += relation([P(235, E.barangays.y), P(235, E.users.bottom)], nn('users', 'barangay_id'), 'has', { at: { x: 250, y: (E.barangays.y + E.users.bottom) / 2 + 5, anchor: 'start' } });

  const ixTk = 1305;
  rel += relation([P(ixTk, E.ir.bottom), P(ixTk, E.tk.y)], nn('maintenance_tickets', 'issue_report_id'), 'leads to', { at: { x: ixTk + 14, y: (E.ir.bottom + E.tk.y) / 2 + 5, anchor: 'start' } });
  const ts = E.sub.rowY('ticket_id');
  rel += relation([P(E.tk.right, ts), P(E.sub.x, ts)], nn('ticket_sub_issues', 'ticket_id'), 'has', { at: { x: (E.tk.right + E.sub.x) / 2, y: ts - 9 } });
  const sr = E.rec.rowY('ticket_sub_issue_id');
  rel += relation([P(E.sub.right, sr), P(E.rec.x, sr)], nn('vehicle_maintenance_records', 'ticket_sub_issue_id'), 'produces', { dashed: !enforced('vehicle_maintenance_records', 'ticket_sub_issue_id', 'ticket_sub_issues'), at: { x: (E.sub.right + E.rec.x) / 2, y: sr - 9 } });
  const rsy = E.sch.rowY('resulting_maintenance_id');
  rel += relation([P(E.rec.x + EW / 2, E.rec.bottom), P(E.rec.x + EW / 2, rsy), P(E.sch.right, rsy)], nn('vehicle_maintenance_schedules', 'resulting_maintenance_id'), 'results in', { at: { x: 1900, y: rsy - 9 } });
  rel += relation([P(1305, E.tk.bottom), P(1305, E.sch.y)], nn('vehicle_maintenance_schedules', 'resulting_ticket_id'), 'results in', { at: { x: 1319, y: (E.tk.bottom + E.sch.y) / 2 + 5, anchor: 'start' } });
  const hy = E.hid.rowY('ticket_id'), ty0 = E.tk.y + 28;
  rel += relation([P(E.tk.right, ty0), P(1550, ty0), P(1550, hy), P(E.hid.x, hy)], nn('hidden_ticket_lists', 'ticket_id'), 'hidden in', { at: { x: 1560, y: (ty0 + hy) / 2 + 5, anchor: 'start' } });
  rel += relation([P(235, E.users.y), P(235, TOP - 62), P(1850, TOP - 62), P(1850, E.hid.y)], nn('hidden_ticket_lists', 'user_id'), 'hides', { at: { x: 1000, y: TOP - 72 } });

  const lg = (x, y) => `<g><rect x="${x}" y="${y}" width="470" height="250" fill="#fafafe" stroke="${INK}" stroke-width="1.6"/>` +
    `<text x="${x + 18}" y="${y + 32}" font-size="18" font-weight="bold" fill="${NAVY}">KEY</text>` +
    [['PK', 'primary key (actual migrated column)'], ['FK', 'migrated database foreign key'], ['?', 'nullable column'],
      ['||', 'exactly one parent  |  o| zero or one parent'], ['o<', 'zero or many children at the database level'],
      ['- - -', 'logical link in code; no migrated foreign key']]
      .map(([k, t], i) => `<text x="${x + 18}" y="${y + 66 + i * 30}" font-size="15" font-weight="bold" fill="${NAVY}">${esc(k)}</text><text x="${x + 78}" y="${y + 66 + i * 30}" font-size="15" fill="${INK}">${esc(t)}</text>`).join('') +
    `<text x="${x + 18}" y="${y + 238}" font-size="13" fill="#4b5563">Selected columns only; see Schema-Reference.md.</text></g>`;

  const body = titleBlock(W, 'Barangay VMS', 'Entity Relationship Diagram', `Core maintenance and readiness  |  revision ${REVISION}  |  ${DATE}`) +
    Object.values(E).map(e => e.svg).join('') + rel + lg(80, TOP + 960);
  return { svg: doc(W, H, body), w: W, h: H };
}

/* ------------------------------------------------------------ use case */
const GREEN = { fill: '#c6f0cb', stroke: '#2e7d32', text: INK };
const BLUE = { fill: '#dbeafe', stroke: '#3b6fc4', text: INK };
const ORANGE = { fill: '#e6a118', stroke: '#9a6700', text: INK };
const DARK = { fill: NAVY, stroke: NAVY, text: '#fff' };
const SLATE = { fill: '#475569', stroke: '#334155', text: '#fff' };

function actorSvg(cx, cy, label) {
  const lines = label.split('\n');
  return `<circle cx="${cx}" cy="${cy}" r="66" fill="#fff" stroke="${INK}" stroke-width="3.5"/>` +
    `<g stroke="${INK}" stroke-width="5" stroke-linecap="round" fill="none"><circle cx="${cx}" cy="${cy - 30}" r="13" fill="#fff"/>` +
    `<line x1="${cx}" y1="${cy - 17}" x2="${cx}" y2="${cy + 12}"/><line x1="${cx - 22}" y1="${cy - 6}" x2="${cx + 22}" y2="${cy - 6}"/>` +
    `<line x1="${cx}" y1="${cy + 12}" x2="${cx - 18}" y2="${cy + 38}"/><line x1="${cx}" y1="${cy + 12}" x2="${cx + 18}" y2="${cy + 38}"/></g>` +
    lines.map((l, i) => `<text x="${cx}" y="${cy + 108 + i * 26}" text-anchor="middle" font-size="22" font-weight="bold" fill="${INK}">${esc(l)}</text>`).join('');
}

function useCaseDiagram(o) {
  const W = o.width, PITCH = o.pitch || 108, OVX = o.ovalX, RX = 330, RY = 42;
  const boxTop = 250, firstY = boxTop + 110;
  const cases = o.cases.map((c, i) => ({ ...c, y: firstY + i * PITCH }));
  const byId = Object.fromEntries(cases.map(c => [c.id, c]));
  const H = firstY + (cases.length - 1) * PITCH + 150 + (o.legend ? 150 : 40);
  const boxBottom = firstY + (cases.length - 1) * PITCH + 80;
  const actors = o.actors.map(a => {
    const ys = cases.filter(c => c.actors.includes(a.id)).map(c => c.y);
    return { ...a, ys, y: ys.reduce((s, y) => s + y, 0) / ys.length };
  });
  for (const side of ['left', 'right']) {   // keep same-side actors apart
    const list = actors.filter(a => a.side === side).sort((p, q) => p.y - q.y);
    for (let i = 1; i < list.length; i++) if (list[i].y - list[i - 1].y < 300) list[i].y = list[i - 1].y + 300;
  }
  const colorOf = c => {
    const owners = c.actors.map(id => actors.find(a => a.id === id));
    const first = owners[0].color;
    return owners.every(a => a.color === first) ? first : ORANGE;
  };
  let svg = `<rect x="${o.boxX}" y="${boxTop}" width="${o.boxW}" height="${boxBottom - boxTop}" fill="#fff" stroke="${INK}" stroke-width="2.5"/>` +
    `<text x="${o.boxX + o.boxW / 2}" y="${boxTop + 50}" text-anchor="middle" font-size="30" font-weight="bold" fill="${INK}">${esc(o.system)}</text>`;
  // associations first so the ovals sit on top
  let lines = '';
  for (const a of actors) {
    for (const c of cases.filter(c => c.actors.includes(a.id))) {
      const ex = a.side === 'left' ? OVX - RX : OVX + RX;
      const ang = Math.atan2((c.y - a.y) * 0.6, (ex - a.x));
      const sx = a.x + 66 * Math.cos(ang), sy = a.y + 66 * Math.sin(ang), dx = (ex - sx) * 0.55 * (a.side === 'left' ? 1 : 1);
      lines += `<path d="M${f(sx)},${f(sy)} C${f(sx + dx)},${f(sy)} ${f(ex - dx)},${c.y} ${ex},${c.y}" fill="none" stroke="${INK}" stroke-width="2.2"/>`;
    }
  }
  svg += lines;
  for (const c of cases) {
    const col = colorOf(c);
    const t = c.text.split('\n');
    svg += `<ellipse cx="${OVX}" cy="${c.y}" rx="${RX}" ry="${RY}" fill="${col.fill}" stroke="${col.stroke}" stroke-width="2.4"/>` +
      t.map((l, i) => `<text x="${OVX}" y="${c.y + 6 + (i - (t.length - 1) / 2) * 22}" text-anchor="middle" font-size="18" font-weight="bold" fill="${col.text}">${esc(l)}</text>`).join('');
  }
  for (const r of o.rels || []) {
    const a = byId[r.from], b = byId[r.to];
    const up = a.y > b.y;
    const y1 = a.y + (up ? -RY : RY), y2 = b.y + (up ? RY : -RY), x = OVX + 205;
    svg += `<line x1="${x}" y1="${y1}" x2="${x}" y2="${y2 + (up ? 12 : -12)}" stroke="${INK}" stroke-width="2.2" stroke-dasharray="8 6"/>` +
      `<path d="M${x},${y2} l-8,${up ? 17 : -17} l16,0 z" fill="${INK}"/>` +
      `<text x="${x + 14}" y="${(y1 + y2) / 2 + 5}" font-size="15" font-style="italic" fill="${INK}" stroke="#fff" stroke-width="5" paint-order="stroke">&lt;&lt;${r.type}&gt;&gt;</text>`;
  }
  for (const a of actors) svg += actorSvg(a.x, a.y, a.label);
  if (o.legend) {
    const ly = boxBottom + 60;
    svg += `<text x="${o.boxX}" y="${ly}" font-size="18" font-weight="bold" fill="${INK}">Oval colors</text>`;
    o.legend.forEach(([col, t], i) => {
      const x = o.boxX + i * 300;
      svg += `<ellipse cx="${x + 28}" cy="${ly + 44}" rx="28" ry="16" fill="${col.fill}" stroke="${col.stroke}" stroke-width="2"/>` +
        `<text x="${x + 66}" y="${ly + 50}" font-size="16" fill="${INK}">${esc(t)}</text>`;
    });
  }
  return { svg: doc(W, H, titleBlock(W, o.small, o.big, o.sub) + svg), w: W, h: H };
}

function buildUseCase() {
  return useCaseDiagram({
    width: 2500, boxX: 700, boxW: 1100, ovalX: 1250, system: 'Barangay Vehicle Management System',
    small: 'Barangay VMS', big: 'Use Case Diagram',
    sub: `Fleet readiness and maintenance  |  associations show role participation, not workflow order  |  revision ${REVISION}`,
    actors: [
      { id: 'cust', label: 'Custodian', side: 'left', x: 320, color: GREEN },
      { id: 'mech', label: 'Maintenance\nPersonnel', side: 'left', x: 320, color: BLUE },
      { id: 'admin', label: 'Admin', side: 'right', x: 2180, color: DARK },
      { id: 'sched', label: 'Laravel\nscheduler', side: 'right', x: 2180, color: SLATE },
    ],
    cases: [
      { id: 'record', text: 'Enter / confirm standalone\nmaintenance record', actors: ['admin'] },
      { id: 'review', text: 'Approve / decline /\nreassign schedule', actors: ['admin'] },
      { id: 'external', text: 'Record external-shop\nsending, return and rework', actors: ['admin'] },
      { id: 'approve', text: 'Review proposal and\nassign repair personnel', actors: ['admin'] },
      { id: 'fleet', text: 'Manage fleet, hubs,\nusers and reports', actors: ['admin'] },
      { id: 'register', text: 'Register vehicle', actors: ['admin', 'cust'] },
      { id: 'history', text: 'View permitted fleet and\ncompleted repair history', actors: ['admin', 'cust', 'mech'] },
      { id: 'verify', text: 'Independently verify repair\nand close or return for rework', actors: ['cust'] },
      { id: 'verifyRecord', text: 'Independently verify\nstandalone maintenance', actors: ['cust'] },
      { id: 'book', text: 'Book / edit own schedule', actors: ['cust'] },
      { id: 'readiness', text: 'Record condition / attest\nfresh operational readiness', actors: ['cust'] },
      { id: 'propose', text: 'Propose / resubmit ticket', actors: ['cust'] },
      { id: 'convert', text: 'Convert due approved\nschedule to ticket proposal', actors: ['sched'] },
      { id: 'issue', text: 'Report vehicle issue', actors: ['cust', 'mech'] },
      { id: 'repair', text: 'Record assigned repairs\nand submit completed work', actors: ['mech'] },
      { id: 'complete', text: 'Complete assigned\nstandalone schedule', actors: ['mech'] },
    ],
    // Only links backed by a migrated result column: condition_checks.resulting_ticket_id and
    // vehicle_maintenance_schedules.resulting_ticket_id.
    rels: [{ from: 'readiness', to: 'propose', type: 'extend' }, { from: 'convert', to: 'propose', type: 'include' }],
    legend: [[GREEN, 'Custodian only'], [BLUE, 'Maintenance Personnel only'], [DARK, 'Admin only'], [ORANGE, 'Shared by several actors'], [SLATE, 'System actor']],
  });
}

function buildPlatformUseCase() {
  return useCaseDiagram({
    width: 2500, boxX: 700, boxW: 1100, ovalX: 1250, system: 'Barangay Vehicle Management System',
    small: 'Barangay VMS', big: 'Use Case Diagram: Platform',
    sub: `Onboarding, activation and platform support  |  merged SMTP activation and Support Center  |  revision ${REVISION}`,
    actors: [
      { id: 'app', label: 'Applicant /\nvisitor', side: 'left', x: 320, color: GREEN },
      { id: 'sa', label: 'Super Admin', side: 'right', x: 2180, color: DARK },
      { id: 'mail', label: 'Email service', side: 'right', x: 2180, color: SLATE },
    ],
    cases: [
      { id: 'register', text: 'Register first barangay Admin\nwithout a staff code', actors: ['app'] },
      { id: 'send', text: 'Review appointment and\nemail activation code', actors: ['sa', 'mail'] },
      { id: 'activate', text: 'Activate with credentials\nand emailed code', actors: ['app'] },
      { id: 'staff', text: 'Register later staff\nwith barangay code', actors: ['app'] },
      { id: 'concern', text: 'Submit public concern', actors: ['app'] },
      { id: 'resolve', text: 'Review / resolve concerns;\nview Concern History', actors: ['sa'] },
      { id: 'manage', text: 'Manage platform users,\nbarangays and codes', actors: ['sa'] },
      { id: 'audit', text: 'Review platform audit\nand boundary references', actors: ['sa'] },
    ],
    legend: [[GREEN, 'Applicant / visitor only'], [DARK, 'Super Admin only'], [ORANGE, 'Shared by several actors'], [SLATE, 'System actor']],
  });
}

/* --------------------------------------------------------- architecture */
function buildArchitecture() {
  const W = 2600, H = 1900;
  let s = '';
  const wrap = (x, y, w, h, fill, stroke, sw = 2) => `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="14" fill="${fill}" stroke="${stroke}" stroke-width="${sw}"/>`;
  const band = (x, y, w, h, name) => wrap(x, y, w, h, '#f5f6fc', NAVY, 2.5) +
    `<path d="M${x},${y + 14} a14,14 0 0 1 14,-14 h${w - 28} a14,14 0 0 1 14,14 v34 h${-w} z" fill="${NAVY}"/>` +
    `<text x="${x + 24}" y="${y + 33}" font-size="21" font-weight="bold" fill="#fff" letter-spacing="1">${esc(name)}</text>`;
  const card = (x, y, w, h, title, lines) => wrap(x, y, w, h, '#fff', '#9aa3d6', 1.8) +
    `<text x="${x + 20}" y="${y + 36}" font-size="20" font-weight="bold" fill="${NAVY}">${esc(title)}</text>` +
    lines.map((l, i) => `<text x="${x + 20}" y="${y + 68 + i * 26}" font-size="16.5" fill="${INK}">&#8226; ${esc(l)}</text>`).join('');
  const link = (x, y1, y2, label) => `<line x1="${x}" y1="${y1}" x2="${x}" y2="${y2}" stroke="${NAVY}" stroke-width="3.5"/>` +
    `<path d="M${x},${y1} l-9,16 h18 z M${x},${y2} l-9,-16 h18 z" fill="${NAVY}"/>` +
    `<text x="${x + 18}" y="${(y1 + y2) / 2 + 6}" font-size="16" fill="${INK}" font-weight="bold">${esc(label)}</text>`;

  // Presentation
  s += band(80, 250, 2440, 230, 'PRESENTATION LAYER  |  Browser');
  s += card(110, 320, 560, 140, 'React 19 / Vite 8 single-page app', ['React Router, Axios client', 'Leaflet maps']);
  s += card(700, 320, 560, 140, 'Role workspaces', ['Admin, Custodian, Maintenance Personnel', 'Tickets, schedules, records, readiness']);
  s += card(1290, 320, 560, 140, 'Super Admin portal', ['Accounts, barangays, codes', 'Concern review and platform audit']);
  s += card(1880, 320, 610, 140, 'Public pages', ['Registration and activation', 'Support Center concern submission']);
  s += link(1300, 480, 560, 'JSON over HTTP(S)  |  Bearer token for protected calls');

  // Application
  s += band(80, 560, 2440, 300, 'APPLICATION LAYER  |  Laravel 13 / PHP');
  s += card(110, 630, 470, 200, 'Public routes', ['Login, registration, activation', 'Public concern submission', 'Geography and address suggestions', 'Validated and rate-limited']);
  s += card(600, 630, 470, 200, 'Protected API boundary', ['Sanctum bearer authentication', 'Active-account and platform guards', 'Role, ownership and state checks', 'Barangay scoping and validation']);
  s += card(1090, 630, 470, 200, 'Controllers and domain logic', ['Fleet, tickets and verification', 'Schedules, records, readiness', 'Users, concerns, platform controls', 'Eloquent models, transactions, locks']);
  s += card(1580, 630, 440, 200, 'Managed file handling', ['Private object references', 'Temporary signed links and renewal', 'Photos, documents, evidence']);
  s += card(2040, 630, 450, 200, 'Laravel scheduler', ['Every minute: due schedules to proposals', 'Hourly: expire unclaimed Admin signups', 'Daily: notification pruning']);

  // Persistence and external services
  s += link(700, 880, 940, 'Eloquent / SQL');
  s += link(1700, 880, 940, 'private bytes');
  s += link(2250, 880, 940, 'mail, tiles, lookups');
  s += band(80, 940, 1280, 290, 'PERSISTENCE LAYER');
  s += card(110, 1010, 610, 190, 'Relational database', ['SQLite for local and testing', 'PostgreSQL is a planned deployment', 'Fleet and platform records, Sanctum tokens']);
  s += card(740, 1010, 590, 190, 'Private file storage', ['Supabase Storage when configured', 'Persistent local private fallback', 'Photos, documents, repair evidence']);
  s += band(1400, 940, 1120, 290, 'EXTERNAL SERVICES');
  s += card(1430, 1010, 350, 190, 'Mail transport', ['Activation code and account emails', 'AccountMail sends synchronously']);
  s += card(1795, 1010, 350, 190, 'Map providers', ['Esri map and satellite tiles', 'Reverse-geocoding services']);
  s += card(2160, 1010, 330, 190, 'Photon / OpenStreetMap', ['Opt-in address suggestions', 'Manual-entry fallback']);

  s += `<text x="80" y="1310" font-size="17" fill="#4b5563">Implementation view at revision ${REVISION}  |  ${DATE}</text>` +
    `<text x="80" y="1338" font-size="17" fill="#4b5563">SQLite is the local and testing database. PostgreSQL and the deployment hosts are proposed; no live production deployment, SMTP delivery or hosted storage is claimed.</text>`;
  s += `<text x="80" y="1396" font-size="19" font-weight="bold" fill="${INK}">How a request travels</text>` +
    ['1. A Custodian proposal leaves the SPA with a bearer token and is checked for account, role and barangay scope.',
      '2. The controller runs the workflow change in a transaction; Admin approval assigns the repair.',
      '3. The mechanic logs evidence; an independent Custodian verifies it, which closes the ticket and clears prior readiness.',
      '4. The database stores the relationships; private storage holds the file bytes.']
      .map((l, i) => `<text x="80" y="${1430 + i * 28}" font-size="17" fill="${INK}">${esc(l)}</text>`).join('');
  return { svg: doc(W, 1580, titleBlock(W, 'Barangay VMS', 'System Architecture', 'Layered implementation view') + s), w: W, h: 1580 };
}

/* --------------------------------------------------------------- output */
async function write(name, built) {
  fs.writeFileSync(path.join(base, name + '.svg'), built.svg);
  await sharp(Buffer.from(built.svg), { density: 96 * 3200 / built.w })
    .resize({ width: 3200 }).flatten({ background: '#fff' }).png().toFile(path.join(base, name + '.png'));
  console.log('wrote', name);
}

(async () => {
  await write('ERD', buildERD());
  await write('Use-Case-Diagram', buildUseCase());
  await write('Use-Case-Platform', buildPlatformUseCase());
  await write('System-Architecture', buildArchitecture());
})().catch(e => { console.error(e); process.exit(1); });
