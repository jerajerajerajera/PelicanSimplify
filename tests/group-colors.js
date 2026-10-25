const fs=require('fs'),vm=require('vm'),assert=require('assert');
const context={window:{Simplify:{toast(){}},SimplifyTheme:{accentInk:()=>'#111118'}},innerWidth:1440,setTimeout:(fn)=>{fn();return 1},clearTimeout(){},localStorage:{getItem:()=>null,setItem(){},removeItem(){}},URL,location:{origin:'https://panel.example'}};
vm.createContext(context);vm.runInContext(fs.readFileSync(__dirname+'/../resources/views/js/dashboard.js','utf8'),context);
const servers=[{uuid:'a',name:'Alpha',node_id:1,color:'#112233'},{uuid:'b',name:'Bravo',node_id:1,color:'#445566'},{uuid:'c',name:'Charlie',node_id:1}];
const saves=[];
const page=(appearance={accent_color:'#878787',individual_colors:true},groups=null)=>{const d=context.window.pelicanSimplify({saveOverviewGroups:async(groups,order)=>{saves.push(JSON.parse(JSON.stringify(groups)));return {groups,order};}},{server:null,servers:servers.map(s=>({...s})),user:'Colors',appearance,overview_groups:groups,settings_url:'https://panel.example/simplify/settings'});d.restoreOverviewPreferences();d.restoreOverviewGroups();return d;};
const accent=(d,id)=>d.accentStyle(id)['--ps-accent'];

// Without group colours every server keeps its own colour, and the common colour wins when separate colours are off.
const d=page();assert.equal(accent(d,'a'),'#112233');assert.equal(accent(d,'b'),'#445566');
assert.equal(accent(page({accent_color:'#abcdef',individual_colors:false}),'a'),'#abcdef');
// A group colour wins for the servers in that group only, also over the common colour; its heading takes it too.
d.addOverviewGroup();const g=d.overviewGroups[0].id;d.toggleGroupServer(g,'a');d.toggleGroupServer(g,'c');
assert.equal(accent(d,'a'),'#112233','a group without a colour changes nothing');
d.setGroupColor(g,'#FFAA00');assert.equal(d.overviewGroups[0].color,'#ffaa00');assert.equal(saves.at(-1)[0].color,'#ffaa00','the colour is saved with the account');
assert.equal(accent(d,'a'),'#ffaa00');assert.equal(accent(d,'c'),'#ffaa00','servers without a colour of their own take it too');assert.equal(accent(d,'b'),'#445566','servers outside the group keep theirs');
const heading=d.overviewItems().find(x=>x.uuid==='group-'+g);assert.equal(heading.color,'#ffaa00');assert.equal(accent(d,'group-'+g),'#ffaa00');
assert.equal(d.accentStyle('group-other')['--ps-accent'],undefined,'Other servers keeps the page colour');
const off=page({accent_color:'#abcdef',individual_colors:false},{groups:saves.at(-1),order:'name'});assert.equal(accent(off,'a'),'#ffaa00','group colour beats the common colour');assert.equal(accent(off,'b'),'#abcdef');
assert.equal(accent(off,'overview'),'#abcdef','the overview itself keeps the panel accent');
// Invalid colours are ignored; removing the colour gives each server its own back.
d.setGroupColor(g,'orange');assert.equal('color' in d.overviewGroups[0],false);assert.equal(accent(d,'a'),'#112233');
d.setGroupColor(g,'#00ff00');d.setGroupColor(g,null);assert.equal('color' in d.overviewGroups[0],false);assert.equal(saves.at(-1)[0].color,undefined);
assert.equal(page(undefined,{groups:[{id:'x',name:'X',servers:['b'],color:'nope'}],order:'name'}).overviewGroups[0].color,undefined,'a bad saved colour reads as none');
// The settings link opens the Appearance tab of Simplify settings.
d.settingsSection='appearance';assert.equal(new URL(d.panelSettingsUrl()).searchParams.get('section'),'appearance');d.settingsSection='settings';assert.equal(new URL(d.panelSettingsUrl()).searchParams.get('section'),null);
console.log('PASS group colours: group colour beats the common and each server\'s own colour for its servers and heading, saved with the account, invalid colours ignored, removable; settings link opens Appearance');
