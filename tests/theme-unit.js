const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(__dirname+'/../resources/views/js/core.js','utf8');
const storage=new Map();
// Each page (dashboard or embedded workspace) runs its own copy of core.js.
function page(config,frame=null){
 const classes=new Set(),events={},style={};
 const root={style:{setProperty(k,v){style[k]=v}},dataset:{},classList:{toggle(k,v){v?classes.add(k):classes.delete(k)},add:k=>classes.add(k),remove:k=>classes.delete(k),contains:k=>classes.has(k)}};
 const window={simplifyThemeConfig:config,frameElement:frame,dispatchEvent(e){for(const fn of events[e.type]||[])fn(e)},addEventListener(t,fn){(events[t]??=[]).push(fn)}};
 window.parent=window;
 const context={window,document:{documentElement:root,addEventListener(){},querySelectorAll:()=>[],body:null},localStorage:{getItem:k=>storage.get(k),setItem:(k,v)=>storage.set(k,v)},matchMedia:()=>({matches:false,addEventListener(){}}),CustomEvent:class{constructor(type,{detail}){this.type=type;this.detail=detail}},getComputedStyle:()=>({getPropertyValue:()=>''}),setTimeout,clearTimeout};
 vm.createContext(context);vm.runInContext(source,context);
 return {window,root,style,has:k=>classes.has(k)};
}
const config={global_theme:true,legacy_theme:true,console_dark:true,accent_color:'#446688',legacy_page:false};
const dashboard=page(config);
assert.equal(dashboard.window.SimplifyTheme.preference(),'system');assert(dashboard.has('ps-theme'));assert(!dashboard.has('ps-frame'));
assert.equal(dashboard.style['--ps-accent'],'#446688');assert.equal(dashboard.style['--ps-on-accent'],'#ffffff');assert(dashboard.has('ps-console-dark'));
dashboard.window.SimplifyTheme.set('dark');assert(dashboard.has('dark'));assert.equal(storage.get('pelicanSimplify.theme'),'dark');assert.equal(storage.get('theme'),'dark');
// Embedded workspaces read their kind and accent from the iframe element before the page paints.
const creator=page(config,{dataset:{psFrame:'create',psAccent:'#bb6677'}});
assert(creator.has('ps-frame'));assert.equal(creator.root.dataset.psFrame,'create');assert(creator.has('dark'),'saved preference applies inside frames');assert.equal(creator.style['--ps-accent'],'#bb6677');
const legacy=page({...config,legacy_theme:false},{dataset:{psFrame:'native'}});
assert(legacy.has('ps-frame'));assert(!legacy.has('ps-theme'),'native workspaces follow the legacy theme switch');
const settings=page({...config,legacy_theme:false},{dataset:{psFrame:'settings'}});assert(settings.has('ps-theme'),'Simplify settings are always themed');
// Live sync from the dashboard.
creator.window.SimplifyTheme.sync('light','#123456');assert(!creator.has('dark'));assert.equal(creator.style['--ps-accent'],'#123456');
const frameEl={dataset:{psFrame:'native'},contentWindow:legacy.window};dashboard.window.SimplifyTheme.frame(frameEl,'files','#abcdef');assert.equal(frameEl.dataset.psFrame,'files');assert.equal(frameEl.dataset.psAccent,'#abcdef');
// Native pages and theme opt-out.
const native=page({...config,legacy_page:true});assert(!native.has('ps-theme'));
dashboard.window.SimplifyTheme.configure({...config,global_theme:false,console_dark:false});assert(!dashboard.has('ps-theme'));assert(dashboard.has('ps-console-adaptive'));
dashboard.window.SimplifyTheme.set('unsupported');assert.equal(dashboard.window.SimplifyTheme.preference(),'dark');
assert.equal(typeof dashboard.window.Simplify.confirm,'function');assert.equal(typeof dashboard.window.Simplify.toast,'function');
// A Pelican theme plugin: Simplify takes its palette, follows a forced mode and leaves Pelican pages to a theme stylesheet.
storage.set('pelicanSimplify.theme','light');
const themed=page({...config,panel_theme:{active:true,mode:'dark',own_css:true}});
assert(themed.has('ps-panel-theme'));assert(themed.has('dark'),'a dark-only theme wins over the saved light choice');assert.equal(themed.window.SimplifyTheme.forced(),'dark');
assert.equal(themed.style['--ps-accent'],'#446688','a chosen accent stays');
const themedNative=page({...config,panel_theme:{active:true,mode:null,own_css:true}},{dataset:{psFrame:'files'}});assert(!themedNative.has('ps-theme'),'a theme stylesheet keeps Pelican pages');assert(themedNative.has('ps-panel-theme'));
const themedCreator=page({...config,panel_theme:{active:true,mode:null,own_css:true}},{dataset:{psFrame:'create'}});assert(themedCreator.has('ps-theme'),'Simplify screens stay themed');
const plain=page({...config,panel_theme:{active:false,mode:null,own_css:false}});assert(!plain.has('ps-panel-theme'));assert.equal(plain.window.SimplifyTheme.forced(),'');
console.log('PASS panel themes: palette class, forced mode, chosen accent kept, theme stylesheet keeps Pelican pages');
console.log('PASS theme preference, accent, console modes, embedded frame detection, legacy opt-out, live sync and shared UI API');
// Plural forms follow the page language like Laravel's trans_choice: conditions first, then the language's own forms.
const words=page(config);const{choice}=words.window.Simplify;
assert.equal(choice('{0} None|{1} One|[2,*] :count servers',0),'None');assert.equal(choice(':count server|:count servers',1),'1 server');assert.equal(choice(':count server|:count servers',0),'0 servers');
words.root.lang='fr';assert.equal(choice(':count serveur|:count serveurs',0),'0 serveur','French uses the singular for zero');
words.root.lang='ru';assert.deepEqual([1,3,5,21,22,11].map(n=>choice(':count сервер|:count сервера|:count серверов',n)),['1 сервер','3 сервера','5 серверов','21 сервер','22 сервера','11 серверов']);
words.root.lang='ar';assert.deepEqual([0,1,2,3,11,100].map(n=>choice('a|b|c|d|e|f',n)),['a','b','c','d','e','f']);
words.root.lang='ja';assert.equal(choice(':count 台',3),'3 台');
words.root.lang='pt_BR';assert.equal(choice(':count servidor|:count servidores',0),'0 servidor');
console.log('PASS plural forms per language: conditions, two, three and six forms, one form');
