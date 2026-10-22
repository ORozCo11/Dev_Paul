import { chromium } from 'playwright';
import assert from 'node:assert/strict';
const browser = await chromium.launch({headless:true});
const page = await browser.newPage({viewport:{width:1440,height:1000}});
page.setDefaultTimeout(10000);
page.on('console',m=>{if(m.type()==='error')console.log('CONSOLE',m.text());});
const errors=[];page.on('pageerror',e=>{errors.push(e.message); console.log('PAGE ERROR',e.message);});
const user={id:1,name:'QA Admin',email:'qa@example.test',role:'Admin',roles:['Admin'],is_active:true,approved_at:'2026-01-01',barangay_id:1,barangay:{name:'QA Barangay'},abilities:['usage.view','usage.log','vehicle.edit','schedule.approve','schedule.decline','schedule.edit','schedule.reassign','schedule.delete']};
const vehicle={vehicle_id:1,vehicle_name:'QA Rescue',plate_number:'QA-001',status:'Available',condition:'Good',category_id:1,category:{category_id:1,category_name:'Ambulance',domain:'Land'},current_location:'Station'};
const tickets=['Pending Approval','Active','Closed','Declined','For Verification'].map((status,i)=>({ticket_id:i+1,ticket_title:`QA ${status}`,status,priority:'High',vehicle,vehicle_id:1,sub_issues:[],created_at:'2026-10-22T00:00:00Z'}));
const issues=Array.from({length:12},(_,i)=>({issue_report_id:i+1,vehicle:{...vehicle,vehicle_name:`Report vehicle ${i+1}`},issue_type:'Engine',reported_by:user,created_at:'2026-10-22T00:00:00Z'}));
let impersonationRequests = 0;
let trips=Array.from({length:23},(_,i)=>({usage_id:23-i,driver_name:`QA Driver ${23-i}`,destination:'Hall',purpose:'Supply run',started_at:'2026-10-21T00:00:00Z',ended_at:'2026-10-21T01:00:00Z',logged_by:user}));
await page.route('http://127.0.0.1:8001/api/**',async route=>{
 const req=route.request(),url=new URL(req.url()),path=url.pathname.replace('/api','');let data=[];
 if(path.startsWith('/impersonate')) impersonationRequests += 1;
 if(path==='/user')data=user;
 else if(path==='/lookups')data={vehicles:[vehicle],categories:[vehicle.category],maintenance_personnel:[],custodians:[],priorities:['High','Medium','Low'],conditions:['Good','Needs Repair'],statuses:['Available']};
 else if(path==='/tickets/lookups')data={ticket_statuses:['Pending Approval','Active','Closed','Declined','For Verification'],priorities:['High','Medium','Low'],custodians:[],maintenance_personnel:[]};
 else if(path==='/vehicles')data=[vehicle];
 else if(path==='/tickets')data=tickets;
 else if(path==='/issues')data=issues;
 else if(path==='/conditions')data=[{condition_check_id:2,vehicle_id:1,vehicle,condition_result:'Good',created_at:'2026-10-22'},{condition_check_id:1,vehicle_id:1,vehicle,condition_result:'Needs Repair',created_at:'2026-10-21'}];
 else if(path==='/maintenance-schedules')data=[{schedule_id:1,vehicle_id:1,vehicle,status:'Pending Approval',maintenance_type:'Preventive',scheduled_date:'2026-10-25',assigned_to:2}];
 else if(path==='/dashboard')data={};
 else if(path==='/registration-settings')data={staff_code:'QA'};
 else if(path.endsWith('/readiness'))data={state:'not_checked'};
 else if(path==='/vehicles/1/usage'){
  if(req.method()==='POST'){const payload=req.postDataJSON();data={...payload,usage_id:24,started_at:payload.started_at??new Date().toISOString(),ended_at:null};trips.unshift(data);}
  else {const p=Number(url.searchParams.get('page')??1);data={data:trips.slice((p-1)*10,p*10),total:trips.length,last_page:Math.ceil(trips.length/10),open_trip:trips.find(t=>!t.ended_at)??null};}
 }else if(path.startsWith('/usage-logs/')){trips[0].ended_at=new Date().toISOString();data=trips[0];}
 await route.fulfill({json:data});
});
await page.addInitScript(()=>{localStorage.setItem('token','qa-fixture');localStorage.setItem('vms_maintenance_view','card');});
await page.goto('http://127.0.0.1:5174/admin/vehicles/1');
await page.waitForLoadState('networkidle');
await page.getByRole('heading',{name:'Trip log',exact:true}).waitFor();
await page.getByText('QA Driver 23',{exact:true}).waitFor();
await page.getByRole('button',{name:'Next',exact:true}).click();
await page.getByText('QA Driver 13',{exact:true}).waitFor();
await page.getByLabel('Driver',{exact:true}).fill('New Driver');await page.getByLabel('Destination',{exact:true}).fill('City hall');await page.getByLabel('Purpose',{exact:true}).fill('Response');
await page.getByRole('button',{name:'Dispatch vehicle',exact:true}).click();
await page.getByRole('button',{name:'Record return',exact:true}).waitFor();
await page.getByRole('button',{name:'Record return',exact:true}).click();
await page.getByRole('heading',{name:'Record departure',exact:true}).waitFor();
await page.screenshot({path:'logs/trip-log-qa.png',fullPage:true});
await page.getByRole('button',{name:'Maintenance Tickets',exact:true}).click();
await page.getByText('Latest Reports',{exact:true}).waitFor();
assert.equal(await page.locator('.latest-issue-timeline-item').count(),5);
await page.locator('.latest-issue-card').getByRole('button',{name:'Next page'}).click();
await page.getByText('Report vehicle 7',{exact:true}).waitFor();
const body=await page.locator('body').innerText();assert(body.includes('QA Pending Approval'));assert(body.includes('QA Active'));assert(!body.includes('QA Closed'));assert(!body.includes('QA Declined'));
await page.getByRole('button',{name:'Maintenance Records',exact:true}).click();await page.getByRole('heading',{name:/Maintenance Records/}).last().waitFor();assert.equal(await page.locator('.maintenance-record-card').count(),0);assert.equal(await page.locator('.panel-header-bar').getByRole('button',{name:/Card|View mode/}).count(),0);
await page.getByRole('button',{name:'Condition Monitoring',exact:true}).click();await page.getByText('Current fleet condition',{exact:true}).waitFor();
await page.getByRole('button',{name:'Maintenance Schedule',exact:true}).click();await page.getByRole('button',{name:'Approve',exact:true}).waitFor();
for(const b of await page.locator('.sch-card-actions button').all()){const box=await b.boundingBox();assert(box.width>=34);assert(box.height>=34);assert((await b.innerText()).trim());}
await page.screenshot({path:'logs/schedule-actions-qa.png',fullPage:true});
await page.getByRole('button',{name:'Card View',exact:true}).click(); await page.getByRole('option',{name:'List View',exact:true}).click(); await page.locator('.schedule-table-actions').getByRole('button',{name:'Approve',exact:true}).waitFor();
assert.equal(impersonationRequests, 0); assert.equal(await page.getByRole('button',{name:'Impersonate',exact:true}).count(),0); assert.deepEqual(errors,[]);console.log(JSON.stringify({passed:true,checks:['Trip history paging, departure details and return','Latest reports paging','Admin proposals/active defaults','Maintenance list overrides saved card preference','Condition graph','Labeled schedule actions','Ordinary Admin has no impersonation controls or candidate requests'],pageErrors:errors}));await browser.close();
