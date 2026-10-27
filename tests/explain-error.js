const fs=require('fs'),vm=require('vm'),assert=require('assert');
const context={window:{},document:{hidden:false},setInterval:()=>1,clearInterval(){},setTimeout:()=>1,clearTimeout(){}};
vm.createContext(context);vm.runInContext(fs.readFileSync(__dirname+'/../resources/views/js/dashboard.js','utf8'),context);
(async()=>{
 let calls=0,reply={ok:true,found:true,problem:'A mod is missing.',cause:'foo needs bar',steps:['Install bar'],lines:42,model:'Claude Opus 5.5'};
 const wire={explainConsole:async()=>{calls++;return reply;}};
 const make=(boot,permissions)=>context.window.pelicanSimplify(wire,{...boot,server:{permissions,properties:{}}});
 // Hidden without a saved key, and for people who may not watch the console.
 assert.equal(make({},{'websocket.connect':true}).canExplain(),false,'no key: no button');
 assert.equal(make({explain_errors:true},{}).canExplain(),false,'no console permission: no button');
 const d=make({explain_errors:true},{'websocket.connect':true});assert.equal(d.canExplain(),true,'key and console permission');
 await d.explainError();
 assert.equal(calls,1);assert.equal(d.explain.loading,false);assert.equal(d.explain.answer.problem,'A mod is missing.');assert.deepEqual([...d.explain.answer.steps],['Install bar']);assert.equal(d.explain.answer.found,true);
 reply={ok:false,error:'You asked a lot of questions in a short time.'};await d.explainError();
 assert.equal(d.explain.answer,null);assert.equal(d.explain.error,'You asked a lot of questions in a short time.','error shown');
 wire.explainConsole=async()=>{throw new Error('500')};await d.explainError();assert.match(d.explain.error,/could not answer/,'failed call shown as a plain error');
 // Closing while waiting: the late answer does not reopen the popup.
 let release;wire.explainConsole=()=>new Promise(r=>release=r);const pending=d.explainError();assert.equal(d.explain.loading,true);
 await d.explainError();d.closeExplain();release({ok:true,problem:'late'});await pending;assert.equal(d.explain,null,'closed popup stays closed');
 const blade=fs.readFileSync(__dirname+'/../resources/views/dashboard.blade.php','utf8');
 assert.ok(/ps-console-explain" x-show="canExplain\(\)"/.test(blade)&&/ps-explain-layer/.test(blade),'button in the console and its popup');
 assert.ok(blade.indexOf('class="ps-modal-layer" :data-open')<blade.indexOf('ps-explain-layer'),'the workspace layer stays the first .ps-modal-layer (syncDock moves the first one)');
 console.log('PASS explain this error: hidden without key or console permission, answer, errors, closing while waiting');
})().catch(e=>{console.error(e);process.exit(1)});
