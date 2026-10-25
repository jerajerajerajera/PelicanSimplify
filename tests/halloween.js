const fs=require('fs'),vm=require('vm'),assert=require('assert');
const store={};
const context={window:{Simplify:{toast(){}},SimplifyTheme:{accentInk:()=>'#111118'}},innerWidth:1440,setTimeout:(fn)=>{fn();return 1},clearTimeout(){},localStorage:{getItem:(k)=>store[k]??null,setItem:(k,v)=>{store[k]=v},removeItem(k){delete store[k]}},URL,location:{origin:'https://panel.example'}};
vm.createContext(context);vm.runInContext(fs.readFileSync(__dirname+'/../resources/views/js/dashboard.js','utf8'),context);
const servers=[{uuid:'a',name:'Alpha',node_id:1,color:'#112233'},{uuid:'b',name:'Bravo',node_id:1}];
const page=(appearance={accent_color:'#878787',individual_colors:true},user='Halloween')=>{const d=context.window.pelicanSimplify({saveOverviewGroups:async(g,o)=>({groups:g,order:o})},{server:null,servers:servers.map(s=>({...s})),user,appearance});d.restoreOverviewPreferences();d.restoreOverviewGroups();return d;};
const on=new Date(2026,9,31,20),before=new Date(2026,9,24,23,59),first=new Date(2026,9,25,0,1),last=new Date(2026,10,2,23,59),after=new Date(2026,10,3,0,1);

// The season runs from 25 October to 2 November, every year.
const d=page();
assert.equal(d.halloweenSeason(before),false);assert.equal(d.halloweenSeason(first),true);assert.equal(d.halloweenSeason(on),true);
assert.equal(d.halloweenSeason(last),true);assert.equal(d.halloweenSeason(after),false);assert.equal(d.halloweenSeason(new Date(2027,9,30)),true);
assert.equal(d.halloween(on),true,'on by default after the update');
// Each person can turn it off, and that is remembered on the device.
d.setOverviewPreference('halloween',false);assert.equal(d.halloween(on),false);
assert.equal(page(undefined).halloween(on),false,'remembered after a reload');
page().setOverviewPreference('halloween',true);assert.equal(page().halloween(on),true);
// The admin can turn it off for the whole panel.
assert.equal(page({accent_color:'#878787',individual_colors:true,seasonal:false},'Other').halloween(on),false);
// In season the overview's neutral accent turns orange; a panel accent of your own, group and server colours stay.
const real=Date;
const at=(date,fn)=>{context.Date=class extends real{constructor(...a){super(...(a.length?a:[date.getTime()]))}static now(){return date.getTime()}};vm.runInContext('Date=globalThis.Date',context);try{return fn()}finally{context.Date=real;vm.runInContext('Date=globalThis.Date',context)}};
const accent=(p,id)=>p.accentStyle(id)['--ps-accent'];
at(on,()=>{const p=page(undefined,'Accent');assert.equal(accent(p,'overview'),'#c2621a');p.themeDark=true;assert.equal(accent(p,'overview'),'#f0a35a');assert.equal(accent(p,'a'),'#112233','server colours stay');
  assert.equal(accent(page({accent_color:'#abcdef',individual_colors:true},'Accent'),'overview'),'#abcdef','an own panel accent stays');
  p.setOverviewPreference('halloween',false);assert.notEqual(accent(p,'overview'),'#f0a35a','off gives the normal accent back');});
at(after,()=>assert.notEqual(accent(page(undefined,'Late'),'overview'),'#c2621a','after the season the accent is normal again'));
// The markup: lantern by the name, web with a spider, pumpkin New button and the switch in Customize overview.
const blade=fs.readFileSync(__dirname+'/../resources/views/dashboard.blade.php','utf8');
for(const part of['class="ps-hw-lantern"','class="ps-hw-web"','class="ps-hw-spider"','class="ps-hw-tab"',"setOverviewPreference('halloween'","'ps-halloween': halloween()"])assert.ok(blade.includes(part),part);
const css=fs.readFileSync(__dirname+'/../resources/views/css/dashboard.css','utf8');
assert.match(css,/prefers-reduced-motion: reduce\) \{ \.ps-hw-face \{ animation: none; \} \.ps-hw-bat \{ display: none; \} \}/,'no flicker and no bats with reduced motion');
assert.match(css,/\.ps-hw-corner \{[^}]*pointer-events: none/,'the web never catches a click');
console.log('PASS halloween: 25 Oct-2 Nov every year, on by default, off per person (remembered) and per panel, orange overview accent only instead of the neutral one, reduced motion respected');
