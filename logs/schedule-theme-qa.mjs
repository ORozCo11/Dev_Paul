import { chromium } from 'playwright';
import assert from 'node:assert/strict';
const browser=await chromium.launch({headless:true});
const errors=[];
async function fixture(role,status='Scheduled') {
 const context=await browser.newContext({viewport:{width:1440,height:1000}});
 const page=await context.newPage(); page.setDefaultTimeout(12000);page.on('pageerror',e=>errors.push(e.message));
 const user={id:3,name:'QA Staff',role,roles:[role],is_active:true,abilities:['schedule.view','schedule.complete','schedule.reassign','schedule.decline','schedule.approve']};
 const vehicle={vehicle_id:1,vehicle_name:'Rescue Truck',plate_number:'QA-001',status:'Available',condition:'Good',category:{category_name:'Rescue',domain:'Land'}};
 let row={schedule_id:1,vehicle_id:1,vehicle,status,maintenance_type:'Engine Service',scheduled_date:'2026-10-25',assigned_to:3,created_by:2};
 const requests=[];
 await context.addInitScript(()=>localStorage.setItem('token','qa-token'));
 await context.route('http://127.0.0.1:8001/api/**',async route=>{
  const req=route.request(),key=new URL(req.url()).pathname.replace('/api','');let data=[];
  if(key==='/user')data=user;
  else if(key==='/lookups')data={vehicles:[vehicle],maintenance_personnel:[{id:4,name:'Other Mechanic'}],categories:[]};
  else if(key==='/dashboard')data={};
  else if(key==='/maintenance-schedules')data=[row];
  else if(key==='/maintenance-schedules/1/complete'){requests.push(key);row={...row,status:'Completed',resulting_maintenance_id:1};data={schedule:row,record:{maintenance_id:1,progress_status:'For Verification'}};}
  else if(key==='/maintenance-schedules/1/reassign'){requests.push(key);data=row;}
  else if(key==='/maintenance-schedules/1/decline'){requests.push(key);data=row;}
  await route.fulfill({json:data});
 });
 const base=role==='Maintenance Personnel'?'maintenance':role==='Custodian'?'custodian':'admin';
 await page.goto('http://localhost:5173/'+base+'/schedules/1');await page.waitForLoadState('networkidle');
 return {page,context,requests};
}
try {
 const f=await fixture('Maintenance Personnel');let p=f.page;
 await p.getByRole('button',{name:'Mark as Done',exact:true}).click();
 const dialog=p.getByRole('dialog');await dialog.waitFor();
 await dialog.getByRole('button',{name:'Mark as Done',exact:true}).click();
 await p.waitForTimeout(400);assert(f.requests.includes('/maintenance-schedules/1/complete'));assert.equal(await p.getByRole('dialog').count(),0);
 await p.getByRole('button',{name:'Switch to dark mode'}).click();assert.equal(await p.evaluate(()=>document.documentElement.dataset.theme),'dark');
 await p.reload();await p.getByRole('button',{name:'Switch to light mode'}).waitFor();assert.equal(await p.evaluate(()=>localStorage.getItem('theme')),'dark');
 await p.waitForLoadState('networkidle');await p.waitForTimeout(300);
  await p.screenshot({path:'logs/schedule-dark-qa.png',fullPage:true});
 await p.setViewportSize({width:390,height:844});assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
 await p.getByRole('button',{name:'Switch to light mode'}).click();assert.equal(await p.evaluate(()=>document.documentElement.dataset.theme),'light');await f.context.close();
 const a=await fixture('Admin');await a.page.getByRole('button',{name:'Reassign',exact:true}).click();await a.page.getByRole('dialog').waitFor();await a.context.close();
 const d=await fixture('Admin','Pending Approval');await d.page.getByRole('button',{name:'Decline',exact:true}).click();await d.page.getByRole('dialog').waitFor();await d.context.close();
 assert.deepEqual(errors,[]);console.log(JSON.stringify({passed:true,checks:['Schedule detail completion opens and submits','Detail reassignment and decline open','Theme toggle and reload persistence','Mobile theme control'],errors}));
} finally {await browser.close();}
