import {chromium} from 'playwright';
import assert from 'node:assert/strict';
const browser=await chromium.launch({headless:true});const errors=[];
async function setup(role){
 const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();page.setDefaultTimeout(12000);page.on('pageerror',e=>errors.push(e.message));
 const user={id:role==='Maintenance Personnel'?3:2,name:'QA User',role,roles:[role],is_active:true,abilities:['ticket.view','ticket.verify','schedule.view','vehicle.view','record.view']};
 const vehicle={vehicle_id:1,vehicle_name:'QA Rescue',plate_number:'QA-001',category_id:1,category:{category_id:1,category_name:'Rescue'},status:'Available',condition:'Good'};
 const tickets=['Active','For Verification','Closed'].map((status,i)=>({ticket_id:i+1,ticket_title:'Queue '+status,vehicle_id:1,vehicle,priority:'Medium',status,assigned_custodian_id:2,created_at:'2026-10-23T00:00:00Z',sub_issues:[{sub_issue_id:i+1,title:'Repair '+status,assigned_mechanic_id:3,status:status==='Closed'?'Done':status==='For Verification'?'For Inspection':'Under Repair',repair_type:'in_house'}]}));
 const schedules=['Pending Approval','Scheduled','Completed','Cancelled','Declined'].map((status,i)=>({schedule_id:i+1,vehicle_id:1,vehicle,maintenance_type:'Schedule '+status,status,scheduled_date:'2026-10-26',assigned_to:3}));
 await context.addInitScript(()=>localStorage.setItem('token','qa-token'));
 await context.route('http://127.0.0.1:8001/api/**',async route=>{const key=new URL(route.request().url()).pathname.replace('/api','');let data=[];if(key==='/user')data=user;else if(key==='/dashboard')data={};else if(key==='/lookups')data={vehicles:[vehicle],categories:[vehicle.category],maintenance_personnel:[],custodians:[]};else if(key==='/tickets'||key==='/tickets/work-orders')data=tickets;else if(key==='/maintenance-schedules')data=schedules;else if(key==='/maintenance-records')data=['Under Repair','Completed'].map((status,i)=>({maintenance_id:i+1,vehicle_id:1,vehicle,maintenance_type:'Ledger '+status,problem_reason:'Repair',progress_status:status,date_started:'2026-10-23'}));await route.fulfill({json:data});});
 const base=role==='Maintenance Personnel'?'maintenance':role==='Custodian'?'custodian':'admin';await page.goto('http://localhost:5173/'+base);await page.waitForLoadState('networkidle');return {context,page};
}
// The Ongoing only / All statuses shortcut buttons were removed; status is chosen only through the dropdown.
// The dropdown itself still reads "All statuses" when nothing is picked, so only buttons beside it count.
async function noShortcuts(page){const bar=page.getByRole('group',{name:'Work status filters'});await bar.waitFor();assert.equal(await bar.locator(':scope > button').count(),0,'status shortcut buttons should be gone');assert.equal(await page.getByRole('button',{name:'Ongoing only'}).count(),0);}
async function choice(page,label,checked){const bar=page.getByRole('group',{name:'Work status filters'});await bar.locator('.multi-select-trigger').click();await bar.getByRole('checkbox',{name:label,exact:true}).setChecked(checked);await bar.locator('.multi-select-trigger').click();}
try{
 // Work orders open with every assigned status, Completed included (e453683).
 const w=await setup('Maintenance Personnel');let p=w.page;await p.getByRole('button',{name:'My Work Orders',exact:true}).click();for(const t of ['Queue Active','Queue For Verification','Queue Closed'])await p.getByText(t,{exact:true}).waitFor();
 await choice(p,'Completed',true);await p.getByText('Queue Closed',{exact:true}).waitFor();assert.equal(await p.getByText('Queue Active',{exact:true}).count(),0);
 await choice(p,'Completed',false);await p.getByText('Queue Active',{exact:true}).waitFor();await p.getByText('Queue Closed',{exact:true}).waitFor();
 await noShortcuts(p);await w.context.close();
 const c=await setup('Custodian');p=c.page;await p.getByRole('button',{name:'My Tasks',exact:true}).click();await p.getByText('Queue Active',{exact:true}).waitFor();assert.equal(await p.getByText('Queue Closed',{exact:true}).count(),0);await choice(p,'Verified',true);await p.getByText('Queue Closed',{exact:true}).waitFor();await p.getByText('Queue Active',{exact:true}).waitFor();await choice(p,'To Verify',false);assert.equal(await p.getByText('Queue For Verification',{exact:true}).count(),0);await noShortcuts(p);await c.context.close();
 const a=await setup('Admin');p=a.page;await p.getByRole('button',{name:'Maintenance Schedule',exact:true}).click();await p.getByText('Schedule Pending Approval',{exact:true}).waitFor();await p.getByText('Schedule Scheduled',{exact:true}).waitFor();assert.equal(await p.getByText('Schedule Completed',{exact:true}).count(),0);
 await choice(p,'Completed',true);await p.getByText('Schedule Completed',{exact:true}).waitFor();await choice(p,'Scheduled',false);assert.equal(await p.getByText('Schedule Scheduled',{exact:true}).count(),0);await p.getByText('Schedule Pending Approval',{exact:true}).waitFor();
 await noShortcuts(p);await choice(p,'Cancelled',true);await p.getByText('Schedule Cancelled',{exact:true}).waitFor();await choice(p,'Cancelled',false);assert.equal(await p.getByText('Schedule Cancelled',{exact:true}).count(),0);
 // Maintenance Records lists completed repairs only (f5094d4).
 await p.getByRole('button',{name:'Maintenance Records',exact:true}).click();await p.getByText('Ledger Completed',{exact:true}).waitFor();assert.equal(await p.getByText('Ledger Under Repair',{exact:true}).count(),0);await a.context.close();assert.deepEqual(errors,[]);console.log(JSON.stringify({passed:true,checks:['Work orders open with every status and narrow through the dropdown','Custodian active and verification union','Schedule ongoing defaults and independent exclusions','Ongoing only and All statuses buttons removed; dropdown adds and removes statuses','Maintenance records list completed repairs only'],errors}));
}finally{await browser.close();}
