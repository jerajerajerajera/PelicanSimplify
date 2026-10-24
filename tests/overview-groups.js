const fs=require('fs'),vm=require('vm'),assert=require('assert');
const saved=new Map();const context={window:{},innerWidth:1440,localStorage:{getItem:key=>saved.get(key)||null,setItem:(key,value)=>saved.set(key,value)},URL,location:{origin:'https://panel.example'}};
vm.createContext(context);vm.runInContext(fs.readFileSync(__dirname+'/../resources/views/js/dashboard.js','utf8'),context);
const servers=[{uuid:'a',name:'Alpha',node_id:1,node_name:'One',game:'minecraft'},{uuid:'b',name:'Bravo',node_id:1,node_name:'One',game:'minecraft'},{uuid:'c',name:'Charlie',node_id:2,node_name:'Two',game:'other',game_label:'Valheim'}];
const page=user=>{const d=context.window.pelicanSimplify({}, {server:null,servers:[...servers],user});d.restoreOverviewPreferences();d.restoreOverviewGroups();return d;};
const heads=d=>d.overviewItems().map(x=>x.isNodeHeader?'#'+x.name:x.uuid).join(' ');

// No groups: the overview is exactly as before.
const d=page('Groups');d.setOverviewPreference('groupByNode','off');assert.equal(heads(d),'a b c');
d.addOverviewGroup();d.addOverviewGroup();assert.deepEqual(d.overviewGroups.map(g=>g.name),['Group 1','Group 2']);assert.equal(d.groupEditing,d.overviewGroups[1].id);
assert.equal(heads(d),'a b c','empty groups add no headings');
const [one,two]=d.overviewGroups.map(g=>g.id);
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
// Kept per account across reloads; a removed server stops counting.
const again=page('Groups');assert.equal(heads(again),'#Friends c #Survival a #Other servers b');assert.equal(page('Someone').overviewGroups.length,0);
again.boot.servers=servers.filter(s=>s.uuid!=='a');assert.equal(again.groupCount(again.overviewGroups[1]),0);assert.equal(heads(again),'#Friends c #Other servers b');
again.removeOverviewGroup(two);assert.equal(page('Groups').overviewGroups.length,1);
// Broken or hostile storage falls back safely.
saved.set(again.overviewGroupsKey(),'{bad');assert.equal(page('Groups').overviewGroups.length,0);
saved.set(again.overviewGroupsKey(),JSON.stringify([{id:'x',name:'',servers:['a','a',5]},{id:'x',name:'Dup',servers:[]},{id:'y',name:'Y',servers:['a','b']},null,{name:'no id'}]));
const clean=page('Groups');assert.deepEqual(clean.overviewGroups.map(g=>[g.name,g.servers.join()]),[['Group','a'],['Y','b']]);
saved.set(again.overviewGroupsKey(),JSON.stringify(Array.from({length:40},(_,i)=>({id:'g'+i,name:'G'+i,servers:[]}))));const full=page('Groups');assert.equal(full.overviewGroups.length,30);full.addOverviewGroup();assert.equal(full.overviewGroups.length,30);
context.localStorage.getItem=()=>{throw Error('blocked')};context.localStorage.setItem=()=>{throw Error('blocked')};const blocked=page('Blocked');blocked.addOverviewGroup();assert.equal(blocked.overviewGroups.length,1,'groups still work for this visit without browser storage');
console.log('PASS own server groups: create, rename, one group per server, order, Other servers, node grouping, filters, per-account storage and safe fallbacks');
