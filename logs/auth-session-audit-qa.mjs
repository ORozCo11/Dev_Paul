import { chromium } from 'playwright';
import assert from 'node:assert/strict';
const browser = await chromium.launch({headless:true});
const context = await browser.newContext();
const page = await context.newPage();
const errors=[];
page.on('pageerror', e=>{ errors.push(e.message); console.error(e.message); });
const source = await (await fetch('http://localhost:5173/src/context/AuthContext.jsx')).text();
const version = source.match(/react\.js\?v=([a-z0-9]+)/)[1];
const mainSource = await (await fetch('http://localhost:5173/src/main.jsx')).text();
const domVersion = mainSource.match(/react-dom_client\.js\?v=([a-z0-9]+)/)[1];
const html=`<html><body><div id="root"></div><script type="module">
import RefreshRuntime from '/@react-refresh';
RefreshRuntime.injectIntoGlobalHook(window);
window.$RefreshReg$ = () => {};
window.$RefreshSig$ = () => (type) => type;
window.__vite_plugin_react_preamble_installed__ = true;
</script><script type="module">
import React from '/node_modules/.vite/deps/react.js?v=${version}';
import ReactDOM from '/node_modules/.vite/deps/react-dom_client.js?v=${domVersion}';
import {AuthProvider} from '/src/context/AuthContext.jsx';
import {AuthContext} from '/src/context/AuthContextObject.js';
function Harness(){const auth=React.useContext(AuthContext);return React.createElement('div',null,
React.createElement('output',{id:'identity'},auth.user?.name??'none'),
React.createElement('button',{id:'login',onClick:()=>auth.login({id:2,name:'New account'},'new-token')},'Login'),
React.createElement('button',{id:'next',onClick:()=>auth.login({id:3,name:'Next account'},'next-token')},'Next'),
React.createElement('button',{id:'refresh',onClick:()=>auth.refreshUser()},'Refresh'));}
ReactDOM.createRoot(document.getElementById('root')).render(React.createElement(AuthProvider,null,React.createElement(Harness)));
</script></body></html>`;
let oldRoute, refreshRoute, holdRefresh=false;
await page.addInitScript(()=>localStorage.setItem('token','old-token'));
await page.route('http://localhost:5173/audit-auth-test',r=>r.fulfill({contentType:'text/html',body:html}));
await page.route('http://127.0.0.1:8001/api/**',async route=>{
 const token=route.request().headers().authorization;
 if(token==='Bearer old-token'){oldRoute=route;return;}
 if(token==='Bearer new-token'&&holdRefresh){refreshRoute=route;return;}
 const name=token==='Bearer next-token'?'Next account':'New account';
 await route.fulfill({json:{id:name==='Next account'?3:2,name}});
});
await page.goto('http://localhost:5173/audit-auth-test', {waitUntil:'domcontentloaded'});
await page.waitForFunction(()=>document.querySelector('#login'), null, {timeout:5000}).catch(async e=>{console.error((await page.content()).slice(0,2200));console.error(errors);throw e;});
await page.waitForTimeout(100);
assert(oldRoute,'Initial session check should be pending');
await page.locator('#login').click();
await page.waitForFunction(()=>document.querySelector('#identity')?.textContent==='New account');
await oldRoute.fulfill({status:401,json:{message:'Expired old session'}});
await page.waitForTimeout(200);
assert.equal(await page.locator('#identity').textContent(),'New account');
assert.equal(await page.evaluate(()=>localStorage.getItem('token')),'new-token');
holdRefresh=true;
await page.locator('#refresh').click();
await page.waitForTimeout(100);assert(refreshRoute);
await page.locator('#next').click();
await page.waitForFunction(()=>document.querySelector('#identity')?.textContent==='Next account');
await refreshRoute.fulfill({json:{id:2,name:'Stale old account'}});
await page.waitForTimeout(200);
assert.equal(await page.locator('#identity').textContent(),'Next account');
assert.equal(await page.evaluate(()=>localStorage.getItem('token')),'next-token');
assert.deepEqual(errors,[]);
console.log(JSON.stringify({passed:true,checks:['Expired previous session cannot log out the new account','Late profile refresh cannot overwrite a newly switched account'],errors}));
await browser.close();
