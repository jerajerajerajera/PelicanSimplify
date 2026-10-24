const fs=require('fs'),vm=require('vm'),assert=require('assert');
const saved=new Map(),toasts=[];const context={window:{Simplify:{toast:(m)=>toasts.push(m)}},innerWidth:1440,setTimeout:(fn)=>{fn();return 1},clearTimeout(){},localStorage:{getItem:key=>saved.get(key)||null,setItem:(key,value)=>saved.set(key,value),removeItem:key=>saved.delete(key)},URL,location:{origin:'https://panel.example'}};
vm.createContext(context);vm.runInContext(fs.readFileSync(__dirname+'/../resources/views/js/dashboard.js','utf8'),context);
const servers=[{uuid:'a',name:'Alpha',node_id:1,node_name:'One',game:'minecraft'},{uuid:'b',name:'Bravo',node_id:1,node_name:'One',game:'minecraft'},{uuid:'c',name:'Charlie',node_id:2,node_name:'Two',game:'other',game_label:'Valheim'}];
// The account's saved groups, as Dashboard::saveOverviewGroups keeps them (null: never saved).
const accounts=new Map();let failing=false;
const page=user=>{const wire={saveOverviewGroups:async(groups)=>{if(failing)throw Error('offline');accounts.set(user,JSON.parse(JSON.stringify(groups)));return groups;}};const d=context.window.pelicanSimplify(wire,{server:null,servers:[...servers],user,overview_groups:accounts.has(user)?accounts.get(user):null});d.restoreOverviewPreferences();d.restoreOverviewGroups();return d;};
const heads=d=>d.overviewItems().map(x=>x.isNodeHeader?'#'+x.name:x.uuid).join(' ');

(async()=>{
// No groups: the overview is exactly as before.
const d=page('Groups');d.setOverviewPreference('groupByNode','off');assert.equal(heads(d),'a b c');assert.equal(accounts.has('Groups'),false,'nothing is saved before you make a group');
d.addOverviewGroup();d.addOverviewGroup();assert.deepEqual(d.overviewGroups.map(g=>g.name),['Group 1','Group 2']);assert.equal(d.groupEditing,d.overviewGroups[1].id);
assert.equal(heads(d),'a b c','empty groups add no headings');
const [one,two]=d.overviewGroups.map(g=>g.id);assert.match(one,/^[A-Za-z0-9_-]{1,40}$/,'ids pass the server check');
assert.equal(d.renameOverviewGroup(one,'  Survival  '),'Survival');assert.equal(d.renameOverviewGroup(one,'   '),'Survival','an empty name keeps the old one');assert.equal(d.renameOverviewGroup(two,'x'.repeat(80)).length,40);d.renameOverviewGroup(two,'Friends');
d.toggleGroupServer(one,'c');assert.equal(heads(d),'#Survival c #Other servers a b');
d.toggleGroupServer(two,'c');assert.deepEqual(d.overviewGroups.map(g=>g.servers.length),[0,1],'a server sits in one group at most');d.toggleGroupServer(one,'a');assert.equal(heads(d),'#Survival a #Friends c #Other servers b');
d.moveOverviewGroup(two,-1);assert.equal(heads(d),'#Friends c #Survival a #Other servers b');d.moveOverviewGroup(two,-1);assert.equal(d.overviewGroups[0].id,two,'moving past the top does nothing');
d.toggleGroupServer(one,'b');assert.equal(heads(d),'#Friends c #Survival a b','no Other heading when every server has a group');
d.toggleGroupServer(one,'b');assert.equal(d.serverGroup('b'),null);assert.equal(d.serverGroup('a').name,'Survival');
// Grouping by node still splits the servers outside the own groups.
d.setOverviewPreference('groupByNode','always');d.toggleGroupServer(two,'c');assert.equal(heads(d),'#Survival a #One b #Two c');
d.setOverviewPreference('groupByNode','off');
// Filters apply inside groups and an empty group hides its heading.
d.toggleGroupServer(two,'c');d.setGameFilter('valheim');assert.equal(heads(d),'#Friends c');d.setGameFilter('minecraft');assert.equal(heads(d),'#Survival a #Other servers b');d.setGameFilter('all');
// Saved with the account: another device (no browser storage) shows the same; other accounts start empty.
assert.equal([...saved.keys()].some(k=>k.includes('overviewGroups')),false,'groups are not kept in the browser any more');
const again=page('Groups');assert.equal(heads(again),'#Friends c #Survival a #Other servers b');assert.equal(page('Someone').overviewGroups.length,0);
again.boot.servers=servers.filter(s=>s.uuid!=='a');assert.equal(again.groupCount(again.overviewGroups[1]),0);assert.equal(heads(again),'#Friends c #Other servers b');
again.removeOverviewGroup(two);assert.equal(page('Groups').overviewGroups.length,1);
// A failed save keeps the change on screen and says so.
failing=true;again.addOverviewGroup();await new Promise(r=>setImmediate(r));assert.equal(again.overviewGroups.length,2);assert.equal(toasts.length,1);failing=false;
// Groups kept by this browser before (beta.5) are handed over to an account that has none, then forgotten here.
const oldKey='pelicanSimplify.overviewGroups.Older';saved.set(oldKey,JSON.stringify([{id:'x',name:'',servers:['a','a',5]},{id:'x',name:'Dup',servers:[]},{id:'y',name:'Y',servers:['a','b']},null,{name:'no id'}]));
const older=page('Older');await new Promise(r=>setImmediate(r));assert.deepEqual(older.overviewGroups.map(g=>[g.name,g.servers.join()]),[['Group','a'],['Y','b']]);assert.equal(accounts.get('Older').length,2,'handed over to the account');assert.equal(saved.has(oldKey),false);
saved.set(oldKey,JSON.stringify([{id:'z',name:'Stale',servers:[]}]));assert.equal(page('Older').overviewGroups[0].name,'Group','an account with groups ignores what a browser still has');
saved.set('pelicanSimplify.overviewGroups.Broken','{bad');assert.equal(page('Broken').overviewGroups.length,0);assert.equal(accounts.has('Broken'),false);
accounts.set('Full',Array.from({length:40},(_,i)=>({id:'g'+i,name:'G'+i,servers:[]})));const full=page('Full');assert.equal(full.overviewGroups.length,30);full.addOverviewGroup();assert.equal(full.overviewGroups.length,30);
console.log('PASS own server groups: create, rename, one group per server, order, Other servers, node grouping, filters, saved per account, failed saves, hand-over from this browser and safe fallbacks');
})().catch(e=>{console.error(e);process.exit(1)});
