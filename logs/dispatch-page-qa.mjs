import {chromium} from 'playwright';
import assert from 'node:assert/strict';
const browser=await chromium.launch({headless:true});const errors=[];
try{
 const page=await browser.newPage({viewport:{width:1440,height:1100}});page.setDefaultTimeout(12000);page.on('pageerror',e=>errors.push(e.message));
 const user={id:1,name:'QA Admin',role:'Admin',roles:['Admin'],is_active:true,abilities:['vehicle.view','usage.view','usage.log']};
 const vehicle={vehicle_id:1,vehicle_name:'Engine 23',plate_number:'QA-001',status:'Available',condition:'Good',category_id:1,category:{category_id:1,category_name:'Fire Truck',domain:'Land'}};
 let trips=Array.from({length:13},(_,i)=>({usage_id:13-i,driver_name:'Driver '+(13-i),destination:'Station',purpose:'Supply run',started_at:'2026-10-22T01:00:00Z',ended_at:'2026-10-22T02:00:00Z',logged_by:user}));
 await page.addInitScript(()=>localStorage.setItem('token','qa-token'));
 await page.route('http://127.0.0.1:8001/api/**',async route=>{const req=route.request(),url=new URL(req.url()),key=url.pathname.replace('/api','');let data=[];
 if(key==='/user')data=user;else if(key==='/dashboard')data={};else if(key==='/lookups')data={vehicles:[vehicle],categories:[vehicle.category]};else if(key==='/vehicles')data=[{...vehicle,open_trip:trips.find(t=>!t.ended_at)??null}];
 else if(key==='/vehicles/1/usage'){if(req.method()==='POST'){data={...req.postDataJSON(),usage_id:14,started_at:new Date().toISOString(),ended_at:null,logged_by:user};trips.unshift(data);}else{const n=Number(url.searchParams.get('page')??1);data={data:trips.slice((n-1)*10,n*10),total:trips.length,last_page:Math.ceil(trips.length/10),open_trip:trips.find(t=>!t.ended_at)??null};}}
 else if(key==='/usage-logs/14/end'){trips[0].ended_at=new Date().toISOString();data=trips[0];}await route.fulfill({json:data});});
 await page.goto('http://localhost:5173/admin');await page.getByRole('button',{name:'Vehicle Management',exact:true}).click();await page.getByRole('button',{name:'Trip log and dispatch',exact:true}).click();
 await page.waitForURL('**/admin/vehicles/1/dispatch');await page.getByRole('heading',{name:'Record departure',exact:true}).waitFor();assert.equal(await page.getByRole('dialog').count(),0);
 assert((await page.locator('.vehicle-dispatch-page').boundingBox()).width>800);
 await page.getByRole('button',{name:'Next',exact:true}).click();await page.getByText('Driver 3',{exact:true}).waitFor();await page.getByRole('button',{name:'Previous',exact:true}).click();
 await page.getByLabel('Driver',{exact:true}).fill('Dispatch Driver');await page.getByLabel('Destination',{exact:true}).fill('City Hall');await page.getByLabel('Purpose',{exact:true}).fill('Emergency response');
 await page.screenshot({path:'logs/dispatch-full-page-qa.png',fullPage:true});
 await page.getByRole('button',{name:'Dispatch vehicle',exact:true}).click();await page.getByRole('button',{name:'Record return',exact:true}).waitFor();await page.reload();await page.getByRole('button',{name:'Record return',exact:true}).waitFor();
 await page.getByRole('button',{name:'Record return',exact:true}).click();await page.getByRole('heading',{name:'Record departure',exact:true}).waitFor();
 await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.getByRole('button',{name:'Back to Vehicle Management',exact:true}).click();await page.waitForURL('http://localhost:5173/admin');
 await page.getByRole('button',{name:'Trip log and dispatch',exact:true}).waitFor();
 user.role='Custodian';user.roles=['Custodian'];await page.goto('http://localhost:5173/custodian/vehicles/1/dispatch');await page.getByRole('heading',{name:'Record departure',exact:true}).waitFor();
 user.role='Maintenance Personnel';user.roles=['Maintenance Personnel'];user.abilities=['vehicle.view'];await page.goto('http://localhost:5173/maintenance/vehicles/1/dispatch');await page.getByText('You do not have access to vehicle dispatch.',{exact:true}).waitFor();assert.equal(await page.locator('.vehicle-trip-log').count(),0);
 assert.deepEqual(errors,[]);console.log(JSON.stringify({passed:true,checks:['Vehicle Management dispatch opens a full page','No dispatch modal','Trip pagination','Departure and return submission','Direct route reload preserves the trip','Mobile layout and return to vehicle list','Custodian direct access and unauthorized-role guard'],errors}));
}finally{await browser.close();}
