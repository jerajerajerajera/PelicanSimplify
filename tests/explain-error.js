const fs=require('fs'),vm=require('vm'),assert=require('assert');
const context={window:{},document:{hidden:false},setInterval:()=>1,clearInterval(){},setTimeout:()=>1,clearTimeout(){}};
vm.createContext(context);vm.runInContext(fs.readFileSync(__dirname+'/../resources/views/js/dashboard.js','utf8'),context);
(async()=>{
 let calls=0,reply={ok:true,found:true,summary:'A mod is missing.',issues:[{title:'Missing mod',cause:'foo needs bar',steps:['Install bar']}],lines:42,model:'Claude Opus 5.5'};
 let asked=[];const wire={explainConsole:async(source)=>{calls++;asked.push(source);return reply;}};
 const make=(boot,permissions)=>context.window.pelicanSimplify(wire,{...boot,server:{permissions,properties:{}}});
 // Hidden without a saved key, and for people who may not watch the console.
 assert.equal(make({},{'websocket.connect':true}).canExplain(),false,'no key: no button');
 assert.equal(make({explain_errors:true,explain_sources:['console']},{}).canExplain(),false,'no console permission: no button');
 const d=make({explain_errors:true,explain_sources:['console']},{'websocket.connect':true});assert.equal(d.canExplain(),true,'key and console permission');
 await d.explainError();
 assert.equal(calls,1);assert.equal(d.explain.loading,false);assert.equal(d.explain.answer.summary,'A mod is missing.');assert.deepEqual([...d.explain.answer.issues[0].steps],['Install bar']);assert.equal(d.explain.answer.found,true);
 reply={ok:false,error:'You asked a lot of questions in a short time.'};await d.explainError();
 assert.equal(d.explain.answer,null);assert.equal(d.explain.error,'You asked a lot of questions in a short time.','error shown');
 wire.explainConsole=async()=>{throw new Error('500')};await d.explainError();assert.match(d.explain.error,/could not answer/,'failed call shown as a plain error');
 // Closing while waiting: the late answer does not reopen the popup.
 let release;wire.explainConsole=()=>new Promise(r=>release=r);const pending=d.explainError();assert.equal(d.explain.loading,true);
 await d.explainError();d.closeExplain();release({ok:true,summary:'late'});await pending;assert.equal(d.explain,null,'closed popup stays closed');
 // Root admins: panel and Wings logs next to the console; Ask again keeps the chosen log; nothing else is sent.
 const admin=make({explain_errors:true,explain_sources:['console','panel','wings']},{'websocket.connect':true});
 assert.deepEqual([...admin.explainSources()],['console','panel','wings']);assert.equal(admin.explainLabel('wings'),'Wings log');assert.equal(admin.explainNoun('wings'),'Wings log');assert.equal(admin.explainNoun(undefined),'log');
 wire.explainConsole=async(source)=>{asked.push(source);return reply;};reply={ok:true,summary:'Locked.',issues:[]};asked=[];await admin.explainError('panel');await admin.explainError();await admin.explainError('nope');
 assert.deepEqual(asked,['panel','panel'],'chosen log kept, unknown source ignored');assert.equal(admin.explain.source,'panel');
 assert.deepEqual([...make({explain_errors:true,explain_sources:['console','panel']},{}).explainSources()],['panel'],'panel log without console permission');
 // A normal log: a calm answer without issues.
 reply={ok:true,found:false,summary:'Everything runs.',lines:0,model:null};await d.explainError();assert.equal(d.explain.answer.found,false);assert.deepEqual([...d.explain.answer.issues],[]);
 const blade=fs.readFileSync(__dirname+'/../resources/views/dashboard.blade.php','utf8');
 const js=fs.readFileSync(__dirname+'/../resources/views/js/dashboard.js','utf8');
 assert.ok(/key: 'tools', label: t\('dashboard\.admin_groups\.tools'\).*'explain'\]/.test(js)&&/key: 'explain', label: t\('dashboard\.explain_panel_errors'\)/.test(js),'Explain panel errors listed under Tools in Admin tools');
 assert.ok(/ps-console-explain" x-show="canExplain\(\)"/.test(blade)&&/ps-explain-sources/.test(blade)&&/Everything seems in order/.test(blade)&&/ps-explain-issue/.test(blade)&&/ps-explain-layer/.test(blade),'button in the console and its popup');
 assert.ok(blade.indexOf('class="ps-modal-layer" :data-open')<blade.indexOf('ps-explain-layer'),'the workspace layer stays the first .ps-modal-layer (syncDock moves the first one)');
 console.log('PASS explain this error: hidden without key or console permission, panel and Wings logs for root admins, answer, errors, closing while waiting');
})().catch(e=>{console.error(e);process.exit(1)});
