const fs=require('fs'),vm=require('vm'),assert=require('assert');
const toasts=[];
const context={window:{Simplify:{toast(m,t){toasts.push([m,t]);}},SimplifyTheme:{accentInk:()=>'#111118'}},innerWidth:1440,setTimeout:(fn)=>{fn();return 1},clearTimeout(){},localStorage:{getItem:()=>null,setItem(){},removeItem(){}},URL,location:{origin:'https://panel.example'}};
vm.createContext(context);vm.runInContext(fs.readFileSync(__dirname+'/../resources/views/js/dashboard.js','utf8'),context);
const items=()=>[
  {key:'own-aaaa',source:'own',category:'warning',tone:'warn',title:'Maintenance',text:'Sunday',from:'jera',audience:'everyone',time:'2 hours ago',until:'until Sun 26 Oct 03:00',action:null,dismissed:false},
  {key:'panel-simplify-0.5.1',source:'panel',category:'info',tone:'info',title:'Simplify 0.5.1 is available',text:'You run 0.5.0.',from:'Panel',audience:'admins',time:'10 minutes ago',until:null,action:{label:'View update',url:'/admin/simplify-plugins'},dismissed:false},
  {key:'own-bbbb',source:'own',category:'success',tone:'ok',title:'Old news',text:'',from:'jera',audience:'everyone',time:'yesterday',until:null,action:null,dismissed:true},
];
let fail=false;const calls=[];
const wire={dismissAnnouncement:async(key)=>{calls.push(key);if(fail)throw new Error('down');return items().map((i)=>i.key===key?{...i,dismissed:true}:i);},restoreAnnouncement:async(key)=>{calls.push('open '+key);if(fail)throw new Error('down');return items().map((i)=>i.key===key?{...i,dismissed:false}:i);},announcements:async()=>items()};
const page=()=>{const d=context.window.pelicanSimplify(wire,{server:null,servers:[],user:'jera',announcements:items(),update:{available:true,version:'0.5.1',current:'0.5.0'}});d.initNotices();return d;};
(async()=>{
  const d=page();
  assert.deepEqual(d.announcementBars().map((i)=>i.title),['Maintenance','Simplify 0.5.1 is available'],'collapsed announcements get no bar');
  assert.deepEqual(d.announcementPins().map((i)=>i.title),['Old news'],'a collapsed announcement stays pinned as a one-line pin');
  const bell=d.notifications();
  assert.deepEqual(bell.filter((i)=>i.announcement).map((i)=>i.title),['Maintenance','Simplify 0.5.1 is available','Old news'],'the bell keeps hidden announcements');
  assert.equal(bell.filter((i)=>i.title==='Simplify 0.5.1 is available').length,1,'the Simplify update shows once (as a Panel announcement)');
  assert.equal(bell.find((i)=>i.key==='panel-simplify-0.5.1').time,'Panel · 10 minutes ago');
  assert.equal(d.unreadNotifications(),2,'the count skips hidden announcements');
  assert.equal(d.announcementMeta(items()[0]),'Warning · until Sun 26 Oct 03:00');
  assert.equal(d.announcementMeta(items()[1]),'Info · Panel · admins only');
  assert.equal(d.announcementMeta(items()[2]),'','no line for a plain announcement');
  assert.equal(d.externalLink('https://example.com/x'),true);assert.equal(d.externalLink('/simplify?server=a'),false);
  await d.dismissAnnouncement('own-aaaa');
  assert.deepEqual(calls,['own-aaaa'],'hiding is saved with the account');
  assert.deepEqual(d.announcementBars().map((i)=>i.title),['Simplify 0.5.1 is available']);
  assert.deepEqual(d.announcementPins().map((i)=>i.title).sort(),['Maintenance','Old news'],'collapsing moves the bar to the pins');
  assert.equal(d.unreadNotifications(),1);
  await d.restoreAnnouncement('own-bbbb');
  assert.deepEqual(calls,['own-aaaa','open own-bbbb'],'opening a pin again is saved with the account');
  assert.ok(d.announcementBars().some((i)=>i.title==='Old news'),'an opened pin is a full bar again');
  fail=true;const e=page();await e.dismissAnnouncement('own-aaaa');
  assert.equal(e.announcementBars().length,2,'a failed save brings the bar back');assert.equal(toasts.at(-1)[1],'warn');
  await e.restoreAnnouncement('own-bbbb');
  assert.deepEqual(e.announcementPins().map((i)=>i.key),['own-bbbb'],'a failed open keeps the pin');
  console.log('PASS announcements dashboard: bars skip collapsed ones, collapsed ones stay pinned and open again, bell keeps them, count, labels, hide saved per account and restored on errors');
})().catch((e)=>{console.error(e);process.exit(1);});
