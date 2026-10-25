import { chromium } from 'playwright';
import assert from 'node:assert/strict';
const browser=await chromium.launch({headless:true});
const errors=[];
const svg='data:image/svg+xml,'+encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="640" height="480"><rect width="640" height="480" fill="#dbeafe"/><rect x="90" y="140" width="440" height="180" rx="20" fill="#2563eb"/><circle cx="180" cy="330" r="45"/><circle cx="450" cy="330" r="45"/></svg>');
const vehicle={vehicle_id:1,vehicle_name:'Rescue Truck',plate_number:'QA-001',photo_url:'http://127.0.0.1:8001/qa-vehicle.svg',status:'Available',condition:'Good',brand:'Toyota',model:'Rescue',category_id:1,category:{category_id:1,category_name:'Fire Truck',domain:'Land'},current_location:'Depot'};
const mechanics=[{id:3,name:'Mechanic Alpha',role:'Maintenance Personnel',is_active:true},{id:4,name:'Mechanic Beta',role:'Maintenance Personnel',is_active:true}];
const base={id:2,name:'QA Custodian',email:'qa@test.invalid',role:'Custodian',roles:['Custodian'],is_active:true,barangay_id:1,approved_at:'2026-01-01',abilities:['ticket.propose','ticket.view','schedule.view','schedule.edit','schedule.delete','condition.view','issue.create','catalog.create']};
let ticket={ticket_id:1,ticket_title:'MT-0001 - Rescue - Engine',ticket_description:'Original details',priority:'High',status:'Pending Approval',vehicle_id:1,vehicle,assigned_custodian_id:2,assigned_custodian:base,created_at:'2026-10-23T00:00:00Z',progress:{total:2,done:0,deferred:0},sub_issues:[{sub_issue_id:11,title:'Engine',status:'Open',maintenance_type:'Engine Repair',repair_type:'in_house'},{sub_issue_id:12,title:'Brakes',status:'Open',maintenance_type:'Brake Repair',repair_type:'in_house'}]};
const payloads=[];
let scheduleLocked=true;
let scheduleStatus='Scheduled';
async function fixture(role,path){
 const context=await browser.newContext({viewport:{width:1440,height:1050}});
 const page=await context.newPage();page.setDefaultTimeout(12000);page.on('pageerror',error=>{errors.push(error.message);console.log('PAGEERROR',error.message);});page.on('console',message=>{if(message.type()==='error')console.log('CONSOLE',message.text());});
 const user={...base,role,roles:[role],id:role==='Maintenance Personnel'?3:role==='Admin'?1:2,abilities:role==='Admin'?['ticket.view','ticket.approve','ticket.decline','schedule.view','schedule.approve','vehicle.view']:role==='Maintenance Personnel'?['ticket.view','subissue.log_repairs','ticket.submit_for_verification']:base.abilities};
 await context.addInitScript(()=>{localStorage.setItem('token','qa-token');});
 await context.route('http://127.0.0.1:8001/qa-vehicle.svg',route=>route.fulfill({contentType:'image/svg+xml',body:decodeURIComponent(svg.split(',')[1])}));
 await context.route('http://127.0.0.1:8001/api/**',async route=>{
 const req=route.request(),url=new URL(req.url()),key=url.pathname.replace('/api','');let data=[];
 if(key==='/user')data=user;
 else if(key==='/lookups')data={vehicles:[vehicle],categories:[vehicle.category],maintenance_personnel:mechanics,custodians:[base],priorities:['High','Medium','Low'],maintenance_types:['Engine Repair','Brake Repair']};
 else if(key==='/tickets/lookups')data={vehicles:[vehicle],maintenance_personnel:mechanics,custodians:[base],priorities:['High','Medium','Low'],maintenance_types:['Engine Repair','Brake Repair'],external_reasons:['Warranty coverage'],ticket_statuses:['Pending Approval','Active','For Verification','Closed']};
 else if(key==='/tickets/1')data=ticket;
 else if(key==='/tickets/1/approve'){const payload=req.postDataJSON();payloads.push({key,payload});ticket={...ticket,status:'Active',sub_issues:ticket.sub_issues.map((sub,index)=>({...sub,status:'Under Repair',assigned_mechanic_id:payload.sub_issues[index].assigned_mechanic_id}))};data=ticket;}
 else if(key==='/tickets/propose'){payloads.push({key,payload:req.postDataJSON()});data={...ticket,ticket_id:2};}
 else if(key==='/tickets'||key==='/tickets/work-orders')data=[ticket];
 else if(key==='/maintenance-schedules/1'&&req.method()==='PUT'){payloads.push({key,payload:req.postDataJSON()});data={status:'Pending Approval'};}
 else if(key==='/maintenance-types')data=['Engine Repair','Brake Repair'];
 else if(key==='/maintenance-schedules')data=[{schedule_id:1,vehicle_id:1,vehicle,maintenance_type:'Service',status:scheduleStatus,created_by:2,scheduled_date:'2026-10-24',assigned_to:3,notes:'Existing plan',resulting_ticket_id:scheduleLocked?1:null,resulting_ticket:scheduleLocked?{...ticket,status:'Active'}:null}];
 else if(key==='/dashboard')data={};
 else if(key==='/barangays/1')data={id:1,name:'QA Barangay',city_name:'QA City',boundary:null};
 else if(key.endsWith('/log-repairs')){payloads.push({key,payload:req.postDataJSON()});data=ticket;}
 await route.fulfill({json:data});
 });
 await page.goto('http://localhost:5173'+path);await page.waitForLoadState('networkidle');return {page,context};
}
async function choose(page,field,query,option){await field.fill(query);await page.getByRole('option',{name:option,exact:true}).click();}
const cust=await fixture('Custodian','/custodian/tickets/new');let page=cust.page;
await page.getByRole('radio',{name:'In-House Repair',exact:true}).click();
await choose(page,page.getByRole('combobox',{name:'Vehicle',exact:false}),'Rescue','Rescue Truck (QA-001)');
const photo=page.locator('.ticket-preview-photo--lg'),card=page.locator('.ticket-preview-card--lg'),details=page.locator('.ticket-preview-body--lg');
assert(await photo.evaluate(img=>img.complete&&img.naturalWidth>0));
assert(Math.abs((await photo.boundingBox()).width-(await card.boundingBox()).width)<=3);assert((await details.boundingBox()).y>=(await photo.boundingBox()).y+(await photo.boundingBox()).height);
assert.equal(await page.locator('.ticket-create-header p').evaluate(el=>getComputedStyle(el).whiteSpace),'nowrap');
const colors=await page.getByRole('radio').evaluateAll(elements=>elements.map(el=>getComputedStyle(el).backgroundColor));assert.notEqual(colors[0],colors[1]);assert(colors.every(color=>color!=='rgb(255, 255, 255)'));
await page.screenshot({path:'logs/ticket-proposal-inhouse-qa.png',fullPage:true});
await page.getByRole('radio',{name:'Sent to External Shop',exact:true}).click();
assert.equal(await page.getByLabel('Work to Be Done at the Shop',{exact:false}).count(),0);
assert.equal(await page.getByLabel('Details',{exact:false}).count(),1);
await page.getByLabel('Details',{exact:false}).fill('Check and repair the cooling system');
await choose(page,page.getByRole('combobox',{name:'Reason for Sending Out',exact:false}),'Warranty','Warranty coverage');
await choose(page,page.getByRole('combobox',{name:'Priority',exact:false}),'High','High');
const estimate=await page.getByLabel('Estimated Cost (PHP)',{exact:true}).boundingBox(),priority=await page.getByRole('combobox',{name:'Priority',exact:false}).boundingBox();assert(Math.abs(estimate.y-priority.y)<3);
assert.equal(await page.getByLabel('Repair Type',{exact:false}).getAttribute('required'),null);
await page.screenshot({path:'logs/ticket-proposal-external-qa.png',fullPage:true});
await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth));await page.screenshot({path:'logs/ticket-proposal-mobile-qa.png',fullPage:true});await page.setViewportSize({width:1440,height:1050});
await page.getByRole('button',{name:'Submit for Admin Review',exact:true}).click();await page.waitForTimeout(500);
assert(payloads.find(row=>row.key==='/tickets/propose')?.payload.ticket_description==='Check and repair the cooling system');
await cust.context.close();
const admin=await fixture('Admin','/admin/tickets/1');page=admin.page;
assert.equal(await page.locator('.ticket-process-meter,.pring,.ticket-detail-description-box').count(),0);
for(const [index,mechanic] of mechanics.entries()){
 const assignment=page.getByRole('combobox',{name:`Maintenance Personnel for sub-issue ${index+1}`,exact:true});
 await choose(page,assignment,mechanic.name,mechanic.name);
 const row=page.locator('.proposal-assignment-row').nth(index),bounds=await row.locator('label').evaluateAll(labels=>labels.map(el=>el.getBoundingClientRect().y));assert(Math.abs(bounds[0]-bounds[1])<3);
}
await page.screenshot({path:'logs/ticket-review-per-issue-qa.png',fullPage:true});
await page.getByRole('button',{name:'Approve',exact:true}).click();await page.waitForTimeout(500);
const approved=payloads.find(row=>row.key.endsWith('/approve'));assert.deepEqual(approved.payload.sub_issues.map(sub=>Number(sub.assigned_mechanic_id)),[3,4]);
await admin.context.close();
const schedule=await fixture('Custodian','/custodian/schedules/1');page=schedule.page;
assert.equal(await page.getByRole('button',{name:'Edit',exact:true}).count(),0);assert.equal(await page.getByRole('button',{name:/^Cancel( Schedule)?$/}).count(),0);await schedule.context.close();
scheduleLocked=false;
const editSchedule=await fixture('Custodian','/custodian/schedules/1/edit');page=editSchedule.page;
await page.getByRole('textbox',{name:/^notes$/i}).fill('Revised shared plan').catch(async error=>{console.error((await page.locator('body').innerText()).slice(-6000));throw error;});
await page.getByRole('button',{name:'Update Schedule',exact:true}).click();await page.waitForTimeout(500);
const schedulePayload=payloads.find(row=>row.key==='/maintenance-schedules/1')?.payload;
assert(schedulePayload,'Schedule edit should submit');assert.equal(schedulePayload.status,undefined);assert.equal(schedulePayload.assigned_to,undefined);assert.equal(schedulePayload.notes,'Revised shared plan');
await editSchedule.context.close();
scheduleStatus='Pending Approval';
const pendingSchedule=await fixture('Custodian','/custodian/schedules/1/edit');page=pendingSchedule.page;
await choose(page,page.getByRole('combobox',{name:/^assigned to$/i}),'Beta','Mechanic Beta');
await page.getByRole('button',{name:'Update Schedule',exact:true}).click();await page.waitForTimeout(500);
const pendingPayload=payloads.filter(row=>row.key==='/maintenance-schedules/1').at(-1)?.payload;
assert.equal(Number(pendingPayload.assigned_to),4);assert.equal(pendingPayload.status,undefined);
await pendingSchedule.context.close();
const log=await fixture('Maintenance Personnel','/maintenance/work-orders/1/11/log-repairs');page=log.page;
await page.getByRole('button',{name:'Submit Repair Log',exact:true}).waitFor();assert.equal(await page.getByText('Repair Date',{exact:true}).count(),0);assert.equal(await page.getByLabel('Start',{exact:true}).count(),0);assert.equal(await page.getByLabel('Completion',{exact:true}).count(),0);
await log.context.close();
assert.deepEqual(errors,[]);console.log(JSON.stringify({passed:true,checks:['Searchable vehicle selection','Full-width vehicle photo and details below','Both repair methods colored','Single-line introduction','External Details and optional Repair Type','Priority beside estimate','No ticket percentage or description','Per-sub-issue mechanic assignments','Activated schedule locked','Approved schedule edits omit status and reassignment; pending plans retain mechanic selection','Repair date inputs removed'],errors}));await browser.close();
