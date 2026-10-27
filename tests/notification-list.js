const fs=require('fs'),vm=require('vm'),assert=require('assert');
const toasts=[];
const context={window:{Simplify:{toast(m,t){toasts.push([m,t]);}},SimplifyTheme:{accentInk:()=>'#111118'}},innerWidth:1440,setTimeout:(fn)=>{fn();return 1},clearTimeout(){},setInterval:()=>1,clearInterval(){},localStorage:{getItem:()=>null,setItem(){},removeItem(){}},URL,Intl,location:{origin:'https://panel.example'}};
vm.createContext(context);vm.runInContext(fs.readFileSync(__dirname+'/../resources/views/js/dashboard.js','utf8'),context);
const announcements=()=>[
  {key:'own-rules',source:'own',category:'info',tone:'info',title:'Server rules',text:'',from:'jera',audience:'everyone',time:'1 day ago',until:null,ends_at:null,action:null,dismissed:true,pinned:true,unread:false},
  {key:'own-news',source:'own',category:'success',tone:'ok',title:'New modpack',text:'',from:'jera',audience:'everyone',time:'1 hour ago',until:'until Sun 26 Oct 03:00',ends_at:1792000000,action:null,dismissed:false,pinned:false,unread:true},
];
const notice=(i,unread=true)=>({id:'pelican-'+i,key:'0000000'+i+'-0000-0000-0000-000000000000',tone:'ok',title:'Backup '+i+' finished',text:'',time:i+' hours ago',unread,action:null});
const pages={1:{items:[1,2,3].map((i)=>notice(i,i<3)),more:true,unread:7},2:{items:[3,4,5].map((i)=>notice(i,false)),more:false,unread:7}};
const calls=[];let fail=false;
const wire={
  notificationPage:async(page)=>{calls.push('page '+page);if(fail)throw new Error('down');return pages[page];},
  markAllNotificationsRead:async()=>{calls.push('all');if(fail)throw new Error('down');return announcements().map((a)=>({...a,unread:false}));},
  markPanelNotificationsRead:async(ids)=>{calls.push('read '+ids.length);return [];},
  announcements:async()=>announcements(),panelNotifications:async()=>[],
};
const admin=[{label:'Panel',items:[{key:'manageannouncements',label:'Announcements',url:'/admin/simplify-announcements',icon:'bell'}]}];
const page=(extra={})=>{const d=context.window.pelicanSimplify(wire,{server:null,servers:[],user:'jera',announcements:announcements(),panel_notifications:[notice(1),notice(2)],panel_unread:7,admin_pages:admin,...extra});d.focusModal=()=>{};d.initNotices();return d;};
(async()=>{
  const d=page();
  assert.equal(d.unreadNotifications(),8,'the count has every unread Pelican notification (7, also the ones not loaded) and unread announcements');
  assert.deepEqual(d.pinnedNotifications().map((i)=>i.title),['Server rules'],'an admin\'s pinned announcement is on top');
  assert.ok(!d.otherNotifications().some((i)=>i.pinned),'and not again below');
  assert.ok(d.pinnedNotifications()[0].dismissed,'it stays pinned in the bell after the bar is unpinned');
  await d.toggleNotifications();
  assert.deepEqual(calls,['page 1'],'opening the bell loads the first page');
  assert.equal(d.otherNotifications().filter((i)=>i.pelican).length,3,'read notifications are listed too');
  assert.ok(d.feed.more,'and there are more');
  await d.loadNotificationPage();
  assert.deepEqual(d.otherNotifications().filter((i)=>i.pelican).map((i)=>i.title),[1,2,3,4,5].map((i)=>'Backup '+i+' finished'),'Show more adds older ones once');
  assert.equal(d.feed.more,false);
  await d.markPanelNoticesRead([notice(1).key]);
  assert.equal(d.panelUnread,6,'marking one read lowers the count');
  await d.markPanelNoticesRead([notice(4).key]);
  assert.equal(d.panelUnread,6,'an already read one does not');
  await d.markAllNotificationsRead();
  assert.ok(calls.includes('all'),'mark all read asks the server to mark everything');
  assert.equal(d.unreadNotifications(),0,'everything is read, also what was not loaded');
  assert.ok(d.otherNotifications().every((i)=>i.unread===false),'every item shows as read');
  // The full list: a popup, a sheet on phones, with New announcement for root admins.
  d.openAllNotifications();
  assert.equal(d.modal,'inbox');assert.equal(d.modalTitle(),'Notifications');assert.equal(d.modalSize(),'is-md');
  d.newAnnouncement();
  assert.equal(d.modal,'admin-page');
  const url=new URL(d.adminPage.url,'https://panel.example');
  assert.equal(url.pathname,'/admin/simplify-announcements');assert.equal(url.searchParams.get('action'),'addAnnouncement','Filament opens the form from ?action=');assert.ok(url.searchParams.get('tz'),'the browser\'s time zone goes along');
  await d.close();
  assert.equal(d.modal,'inbox','closing the form comes back to the list');
  const user=page({admin_pages:[]});assert.equal(user.announcementsPage(),null,'no New announcement without the admin page');
  // Failures put things back.
  fail=true;const e=page();await e.markAllNotificationsRead();
  assert.equal(e.unreadNotifications(),8,'a failed mark all read keeps the count');assert.equal(toasts.at(-1)[1],'warn');
  // Times: "until" in this browser's time zone, from Unix time.
  assert.match(d.announcementUntil(announcements()[1]),/^until \w{3} \d{1,2} \w{3} \d{2}:\d{2}$/);
  assert.equal(d.announcementUntil({until:'until X'}),'until X','the server text without Unix time');
  console.log('PASS notification list: pinned block on top, all notifications with Show more, count includes unloaded ones, mark all read on the server, full list popup with New announcement, browser time zone');
})().catch((e)=>{console.error(e);process.exit(1);});
