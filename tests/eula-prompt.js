const fs=require('fs'),vm=require('vm'),assert=require('assert');const asked=[];const c={window:{Simplify:{confirm:async options=>{asked.push(options);return true;},toast:()=>{}}}};vm.createContext(c);vm.runInContext(fs.readFileSync(__dirname+'/../resources/views/js/dashboard.js','utf8'),c);const calls=[];let eula=false;const w=c.window.pelicanSimplify({acceptEula:async()=>calls.push('accept'),power:async(a)=>calls.push(a),finishInstall:async()=>({eula})},{});
(async()=>{
 w.server={permissions:{'file.update':false,'control.start':true}};w.promptEula();assert.equal(w.eulaNeeded,false,'No EULA step without file permission');
 w.server.permissions['file.update']=true;w.promptEula();assert.equal(w.eulaNeeded,true,'The banner asks for the EULA');assert.equal(asked.length,0,'No popup: the banner is the EULA step');
 w.eulaNeeded=false;w.promptEula();assert.equal(w.eulaNeeded,false,'Asked once per page view');
 w.eulaNeeded=true;await w.readyStart();assert.deepEqual(calls,['accept'],'Accept and start sends the EULA acceptance (which starts the server)');assert.equal(w.eulaNeeded,false);
 w.eulaPrompted=false;eula=true;await w.finishInstall();assert.equal(w.eulaNeeded,false,'An accepted EULA (creator) needs no step');
 eula=false;await w.finishInstall();assert.equal(w.eulaNeeded,true,'A missing EULA after installing shows the step in the ready banner');
 w.eulaNeeded=false;w.eulaPrompted=true;await w.readyStart();assert.deepEqual(calls,['accept','start'],'Without a needed EULA the banner just starts');
 const src=fs.readFileSync(__dirname+'/../resources/views/js/dashboard.js','utf8');assert.match(src,/event === 'console output' && \/agree to the EULA\/i\.test\(line\)/);assert.match(src,/event === 'feature match'/,'Wings feature matches for the EULA open the same step');
 const blade=fs.readFileSync(__dirname+'/../resources/views/dashboard.blade.php','utf8');assert.match(blade,/minecraft\.net\/eula/);
 const creator=fs.readFileSync(__dirname+'/../src/Filament/Admin/Pages/CreateMinecraftServer.php','utf8');assert.doesNotMatch(creator,/accept_eula|'eula' => true/,'The Server Maker leaves the EULA to the ready banner');
 const provider=fs.readFileSync(__dirname+'/../src/Providers/SimplifyProvider.php','utf8');assert.match(provider,/Features\\MinecraftEula/,'Pelican\'s own EULA popup is replaced');
 console.log('PASS EULA step in the ready banner: guards, once per page, accept-and-start, check after installing, EULA left to the banner by the Server Maker and Pelican popup replaced');
})().catch(e=>{console.error(e);process.exit(1)});
