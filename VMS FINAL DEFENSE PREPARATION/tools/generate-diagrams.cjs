/* Rebuild from the source DOT files and an isolated schema export.
 * Dependencies: npm install --prefix <tools-dir> @viz-js/viz sharp
 * Set VMS_DIAGRAM_TOOLS to <tools-dir>, or use %TEMP%/vms-defense-diagram-tools.
 */
const fs = require('fs');
const path = require('path');
const base = path.dirname(__dirname);
const deps = process.env.VMS_DIAGRAM_TOOLS || path.join(require('os').tmpdir(), 'vms-defense-diagram-tools');
const { instance } = require(path.join(deps, 'node_modules/@viz-js/viz'));
const sharp = require(path.join(deps, 'node_modules/sharp'));
const schema = JSON.parse(fs.readFileSync(path.join(base, 'sources/schema.json'), 'utf8')).tables;
const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const q = s => JSON.stringify(s);
const write = (p, s) => fs.writeFileSync(path.join(base, p), s);
const theme = `graph [bgcolor="white", fontname="Arial", fontsize=24, fontcolor="#0f172a", pad=0.45, nodesep=0.4, ranksep=1.0, labelloc=t, labeljust=l];
node [fontname="Arial", fontsize=13, color="#94a3b8", fontcolor="#0f172a", penwidth=1.1];
edge [fontname="Arial", fontsize=10, color="#64748b", fontcolor="#475569", penwidth=1.1];`;
function entity(table, fields) {
  const t = schema[table];
  if (!t) throw Error('Unknown table: '+table);
  const wanted = fields || t.columns.filter(c => c.pk || t.foreign_keys.some(f => f.from === c.name)).map(c => c.name);
  const seen = [...new Set(wanted)];
  for (const col of seen) if (!t.columns.some(c => c.name === col)) throw Error(table+'.'+col+' does not exist');
  const lines = seen.map(name => {
    const c = t.columns.find(c => c.name === name);
    const fk = t.foreign_keys.some(f => f.from === name);
    const key = c.pk ? 'PK'+(fk?'/FK':'') : fk?'FK':'';
    return `<TR><TD ALIGN="LEFT"><FONT COLOR="#1d4ed8">${key || '&#160;'}</FONT></TD><TD ALIGN="LEFT">${esc(name)}${!c.pk && !c.notnull ? ' ?' : ''}</TD></TR>`;
  }).join('');
  return `${q(table)} [shape=plain, label=<<TABLE BORDER="1" COLOR="#cbd5e1" CELLBORDER="0" CELLSPACING="0" CELLPADDING="6"><TR><TD COLSPAN="2" BGCOLOR="#e8f0fe"><B>${table}</B></TD></TR>${lines}</TABLE>>];`;
}
function link(child, column, parent, opts={}) {
  const t=schema[child];
  const col=t.columns.find(c=>c.name===column);
  if(!col) throw Error('Missing relationship column '+child+'.'+column);
  const enforced=t.foreign_keys.some(f=>f.from===column&&f.table===parent);
  return `${q(parent)} -> ${q(child)} [dir=both, arrowtail=${col.notnull?'teetee':'teeodot'}, arrowhead="crowodot", style=${enforced?'solid':'dashed'}, label=${q(opts.label||column)}${opts.constraint===false?', constraint=false':''}];`;
}
function coreERD() {
 const defs={
  barangays:['id','city_id','name','boundary_status'],
  users:['id','barangay_id','name','email','roles','is_active','awaiting_staff_code','staff_code_sent_at'],
  vehicle_categories:['category_id','category_name','domain','default_criticality'],
  vehicles:['vehicle_id','barangay_id','category_id','vehicle_name','plate_number','status','condition','readiness_invalidated_at'],
  vehicle_issue_reports:['issue_report_id','vehicle_id','reported_by','issue_type','severity_level','status'],
  maintenance_tickets:['ticket_id','vehicle_id','issue_report_id','assigned_custodian_id','assigned_mechanic_id','priority','status','decision_revision'],
  ticket_sub_issues:['sub_issue_id','ticket_id','assigned_mechanic_id','source_vehicle_id','repair_type','status','work_history'],
  vehicle_maintenance_records:['maintenance_id','vehicle_id','ticket_sub_issue_id','maintenance_personnel_id','verified_by','confirmed_by','progress_status','date_completed'],
  vehicle_maintenance_schedules:['schedule_id','vehicle_id','created_by','assigned_to','resulting_ticket_id','resulting_maintenance_id','scheduled_date','recurrence_months','status'],
  vehicle_condition_checks:['condition_check_id','vehicle_id','checked_by','condition_result','resulting_ticket_id'],
  vehicle_readiness_checks:['readiness_check_id','vehicle_id','checked_by','all_passed','checked_at'],
  hidden_ticket_lists:['user_id','ticket_id','created_at'],
 };
 const links=[['users','barangay_id','barangays'],['vehicles','barangay_id','barangays'],['vehicles','category_id','vehicle_categories'],
 ['vehicle_issue_reports','vehicle_id','vehicles'],['maintenance_tickets','vehicle_id','vehicles'],['maintenance_tickets','issue_report_id','vehicle_issue_reports'],
 ['ticket_sub_issues','ticket_id','maintenance_tickets'],['vehicle_maintenance_records','ticket_sub_issue_id','ticket_sub_issues'],
 ['vehicle_maintenance_schedules','vehicle_id','vehicles'],['vehicle_maintenance_schedules','resulting_ticket_id','maintenance_tickets'],
 ['vehicle_maintenance_schedules','resulting_maintenance_id','vehicle_maintenance_records'],['vehicle_condition_checks','vehicle_id','vehicles'],
 ['vehicle_readiness_checks','vehicle_id','vehicles'],['hidden_ticket_lists','user_id','users'],['hidden_ticket_lists','ticket_id','maintenance_tickets']];
 return `digraph ERD { ${theme}
 graph [rankdir=LR, splines=polyline, label="01  ENTITY-RELATIONSHIP DIAGRAM | Core maintenance and readiness\nBarangay VMS | 25 October 2026 | revision 352fd0e\nSelected attributes and relationships; see Schema-Reference.md for all physical foreign keys."];
 ${Object.entries(defs).map(([t,f])=>entity(t,f)).join('\n')}
 ${links.map(x=>link(...x)).join('\n')}
 legend [shape=note, fontsize=12, color="#cbd5e1", label="KEY\nPK = primary key; FK = migrated foreign key; ? = nullable\nAt parent: || = exactly one, o| = zero or one\nAt child: o< = zero or many\nSolid line = migrated FK; dashed = code relationship\nOther user-role/source FKs are listed in the table boxes/reference.\nA proposal requires work items in application logic.\nNo separate Role table; users.roles stores multiple roles."];
 }`;
}
function supportingERD(){
 const defs={provinces:['id','code','name'],cities:['id','province_id','name'],barangays:['id','city_id','name'],
 registration_settings:['id','barangay_id','staff_code'],users:['id','barangay_id','email','roles'],vehicles:['vehicle_id','barangay_id','category_id'],
 vehicle_categories:['category_id','category_name'],vehicle_type_fields:['field_id','category_id','key','field_type','is_active'],
 vehicle_documents:['document_id','vehicle_id','added_by','file_url'],vehicle_histories:['history_id','vehicle_id','updated_by','related_table','related_record_id'],
 vehicle_locations:['location_record_id','vehicle_id','updated_by','current_location'],vehicle_hubs:['hub_id','barangay_id','created_by','name','lat','lng'],
 issue_report_attachments:['attachment_id','issue_report_id','uploaded_by','file_url'],vehicle_issue_reports:['issue_report_id','vehicle_id'],
 ticket_sub_issues:['sub_issue_id','ticket_id','source_vehicle_id'],ticket_sub_issue_donor_parts:['part_id','sub_issue_id','part_name','quantity'],
 cannibalization_records:['cannibalization_id','source_vehicle_id','destination_vehicle_id','recorded_by'],cannibalization_parts:['part_id','cannibalization_id','part_name','quantity'],
 concern_reports:['id','concern_type','reporter_name','status','resolved_by','deleted_at'],activity_logs:['log_id','user_id','barangay_id','action','impersonator_user_id'],
 managed_files:['id','barangay_id','uploaded_by','disk','path'],notifications:['notification_id','user_id','ticket_id','schedule_id','read_at']};
 const edges=[['cities','province_id','provinces'],['barangays','city_id','cities'],['registration_settings','barangay_id','barangays'],['users','barangay_id','barangays'],
 ['vehicles','barangay_id','barangays'],['vehicles','category_id','vehicle_categories'],['vehicle_type_fields','category_id','vehicle_categories'],
 ['vehicle_documents','vehicle_id','vehicles'],['vehicle_histories','vehicle_id','vehicles'],['vehicle_locations','vehicle_id','vehicles'],['vehicle_hubs','barangay_id','barangays'],
 ['vehicle_issue_reports','vehicle_id','vehicles'],['issue_report_attachments','issue_report_id','vehicle_issue_reports'],['ticket_sub_issue_donor_parts','sub_issue_id','ticket_sub_issues'],
 ['cannibalization_records','source_vehicle_id','vehicles'],['cannibalization_parts','cannibalization_id','cannibalization_records'],
 ['concern_reports','resolved_by','users'],['activity_logs','user_id','users'],['managed_files','uploaded_by','users'],['notifications','user_id','users']];
 return `digraph Support {${theme} graph [rankdir=LR, splines=polyline, label="01B  SUPPORTING ENTITIES | Geography, evidence and platform support\nSelected attributes/relations | Solid = migrated FK; dashed = logical code relationship | ? = nullable"];
 ${Object.entries(defs).map(([t,f])=>entity(t,f)).join('\n')}
 ${edges.map(x=>link(...x)).join('\n')}
 }`;
}
function useCases(platform=false){
 const actors=platform?{applicant:'Applicant / visitor',sa:'Super Admin',email:'Email service'}:{admin:'Admin',custodian:'Custodian',mechanic:'Maintenance Personnel',clock:'Laravel scheduler'};
 const cases=platform?[
 ['register','Register first barangay Admin\nwithout a staff code',['applicant']],
 ['send','Review appointment and\nemail activation code',['sa','email']],
 ['activate','Activate with credentials\nand emailed code',['applicant']],
 ['staff','Register later staff\nwith barangay code',['applicant']],
 ['manage','Manage platform users,\nbarangays and codes',['sa']],
 ['concern','Submit public concern',['applicant']],
 ['resolve','Review / resolve concerns;\nview Concern History',['sa']],
 ['audit','Review platform audit\nand boundary references',['sa']],
 ]:[
 ['vehicle','Register vehicle',['admin','custodian']],
 ['history','View permitted fleet and\ncompleted repair history',['admin','custodian','mechanic']],
 ['fleet','Manage fleet, hubs,\nusers and reports',['admin']],
 ['issue','Report vehicle issue',['custodian','mechanic']],
 ['propose','Propose / resubmit ticket',['custodian']],
 ['approve','Review proposal and\nassign repair personnel',['admin']],
 ['repair','Record assigned repairs\nand submit completed work',['mechanic']],
 ['external','Record external-shop\nsending, return and rework',['admin']],
 ['verify','Independently verify repair\nand close or return for rework',['custodian']],
 ['readiness','Record condition / attest\nfresh operational readiness',['custodian']],
 ['book','Book / edit own schedule',['custodian']],
 ['review','Approve / decline /\nreassign schedule',['admin']],
 ['complete','Complete assigned\nstandalone schedule',['mechanic']],
 ['record','Enter / confirm standalone\nmaintenance record',['admin']],
 ['verifyRecord','Independently verify\nstandalone maintenance',['custodian']],
 ['convert','Convert due approved\nschedule to ticket proposal',['clock']],
 ];
 const actorNodes=Object.entries(actors).map(([id,title])=>`${id} [shape=box, style="rounded,filled", fillcolor="#f1f5f9", color="#94a3b8", label=${q('<<actor>>\n'+title)}];`).join('\n');
 return `digraph UseCase {${theme} graph [rankdir=LR, splines=polyline, nodesep=0.32, ranksep=1.1, label=${q(platform?'02B  USE-CASE DIAGRAM | Onboarding and platform support\nMerged SMTP activation and Support Center | revision 352fd0e':'02  USE-CASE DIAGRAM | Fleet readiness and maintenance\nAssociations show role participation, not workflow order | revision 352fd0e')}];
 ${actorNodes}
 subgraph cluster_system {label="Barangay Vehicle Management System"; fontsize=18; color="#93c5fd"; style="rounded"; margin=24;
 ${cases.map(([id,label])=>`${id} [shape=ellipse, style=filled, fillcolor="#eff6ff", color="#93c5fd", margin="0.18,0.12", label=${q(label)}];`).join('\n')}
 }
 ${cases.flatMap(([id,,as])=>as.map(a=>`${a} -> ${id} [dir=none];`)).join('\n')}
 }`;
}
function architecture(){return `digraph Architecture {${theme}
graph [rankdir=LR, splines=polyline, ranksep=1.2, nodesep=0.6, label="03  SYSTEM ARCHITECTURE | Barangay VMS\nImplementation view at revision 352fd0e | 25 October 2026\nDeployment hosts are proposed; this diagram does not claim a live production deployment."];
node [shape=box, style="rounded,filled", margin="0.24,0.18"];
subgraph cluster_client {label="PRESENTATION | Browser"; fontsize=18; color="#93c5fd"; style=rounded;
spa [fillcolor="#eff6ff", label="React 19 / Vite 8 SPA\nReact Router, Axios, Leaflet\nFleet role workspaces\nSeparate Super Admin portal\nPublic registration and Support Center"];
}
subgraph cluster_api {label="APPLICATION | Laravel 13 / PHP"; fontsize=18; color="#86efac"; style=rounded;
public [fillcolor="#f0fdf4", label="Public routes\nLogin / registration / activation\nConcern submission\nGeography and opt-in suggestions"];
gate [fillcolor="#f0fdf4", label="Protected API boundary\nSanctum bearer authentication\nActive-account and platform-scope guards\nRole / ownership / state checks\nBarangay scoping; input validation"];
domain [fillcolor="#f0fdf4", label="Controllers and domain logic\nFleet, tickets and verification\nSchedules, records and readiness\nUsers, concerns and platform controls\nEloquent models; transactions / locks"];
files [fillcolor="#f0fdf4", label="Managed file handling\nPrivate object references\nTemporary signed links / renewal"];
schedule [fillcolor="#f0fdf4", label="Laravel scheduler\nEvery minute: due schedules -> proposals\nHourly: expire unclaimed Admin signups\nDaily: notification pruning"];
}
subgraph cluster_data {label="PERSISTENCE"; fontsize=18; color="#c4b5fd"; style=rounded;
db [shape=cylinder, fillcolor="#f5f3ff", label="Relational database\nSQLite for local/testing\nPostgreSQL: planned deployment\nTenant fleet + platform records\nSanctum tokens; activity/history"];
storage [shape=cylinder, fillcolor="#f5f3ff", label="Private files\nSupabase Storage when configured\nPersistent local private fallback\nPhotos, documents, repair evidence"];
}
subgraph cluster_external {label="EXTERNAL SERVICES"; fontsize=18; color="#fcd34d"; style=rounded;
mail [fillcolor="#fffbeb", label="Configured mail transport\nActivation code and account emails\nCurrent AccountMail sends synchronously"];
maps [fillcolor="#fffbeb", label="Map / geocoding providers\nEsri map and satellite tiles\nReverse-geocoding services"];
photon [fillcolor="#fffbeb", label="Photon / OpenStreetMap\nOpt-in address suggestions\nManual-entry fallback"];
}
spa -> public [label="JSON / HTTP(S)\npublic actions"];
spa -> gate [label="JSON / HTTP(S)\nBearer token"];
public -> domain [label="validated commands"];
gate -> domain [label="authorized commands"];
domain -> db [label="Eloquent / SQL"];
domain -> files [label="upload / resolve"];
files -> storage [label="private bytes"];
files -> spa [label="signed download link", style=dashed, constraint=false];
domain -> mail [label="activation and account mail"];
spa -> maps [label="tiles / location lookup", constraint=false];
public -> photon [label="rate-limited\nsuggestion lookup"];
schedule -> domain [label="scheduled commands", constraint=false];
}`;}
function schemaReference(){
 const lines=['# Schema reference','', 'Revision: `352fd0e`. Fresh migrations on isolated SQLite `:memory:`; no seed data or fleet database access. This records the resulting schema, including new activation fields and concern soft deletion.','',
 'PK = primary key; FK = migrated foreign key. Nullable fields are marked yes. A column can be a logical reference without an enforced foreign key. Diagram boxes abbreviate attributes; this reference lists them all.',''];
 for(const [table,t] of Object.entries(schema)){
  lines.push('## '+table,'','| Column | SQLite type | Key | Nullable | Reference |','|---|---|---|---|---|');
  for(const c of t.columns){const f=t.foreign_keys.find(f=>f.from===c.name);lines.push(`| ${c.name} | ${c.type||'(unspecified)'} | ${c.pk?'PK '+c.pk:''}${f?' FK':''} | ${c.pk||c.notnull?'no':'yes'} | ${f?f.table+'.'+f.to+'; delete '+f.on_delete:'-'} |`);}
  lines.push('');
 }
 write('Schema-Reference.md',lines.join('\n'));
}
(async()=>{
 const viz=await instance();
 const diagrams={'ERD':coreERD(),'ERD-Supporting':supportingERD(),'Use-Case-Diagram':useCases(),'Use-Case-Platform':useCases(true),'System-Architecture':architecture()};
 for(const [name,dot] of Object.entries(diagrams)){
  write('sources/'+name+'.dot',dot);
  const result=viz.render(dot,{format:'svg',engine:'dot'});
  if(result.status!=='success') throw Error(JSON.stringify(result.errors));
  write(name+'.svg',result.output);
  await sharp(Buffer.from(result.output),{density:160}).resize({width:3200,withoutEnlargement:false}).flatten({background:'white'}).png().toFile(path.join(base,name+'.png'));
  console.log(name+': SVG + PNG rendered');
 }
 schemaReference();
 const sections=Object.keys(diagrams).map(n=>`<section><img src="${n}.svg" alt="${n}"/></section>`).join('\n');
 write('Defense-Diagrams.html',`<!doctype html><html lang="en"><head><meta charset="utf-8"><title>VMS Final Defense Diagrams</title><style>@page{size:A3 landscape;margin:10mm}@page portrait{size:A3 portrait;margin:10mm}*{box-sizing:border-box}body{margin:0;background:#e2e8f0}section{background:white;margin:20px auto;padding:12px;width:95vw;height:67vw;display:flex;align-items:center;justify-content:center;break-after:page}section:last-child{break-after:auto}img{max-width:100%;max-height:100%;object-fit:contain}@media print{body{background:white}section{margin:0;padding:0;width:400mm;height:276mm}section:nth-child(3),section:nth-child(4){page:portrait;width:276mm;height:400mm}}</style></head><body>${sections}</body></html>`);
})();
