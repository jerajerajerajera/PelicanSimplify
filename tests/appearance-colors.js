const fs=require('fs'),vm=require('vm'),assert=require('assert');
// The page loads core.js (contrast colour for accents) before dashboard.js.
const context={window:{dispatchEvent(){},addEventListener(){}},document:{documentElement:{style:{setProperty(){}},dataset:{},classList:{toggle(){},add(){},remove(){},contains:()=>false}},addEventListener(){},querySelectorAll:()=>[],body:null},localStorage:{getItem:()=>null,setItem(){}},matchMedia:()=>({matches:false,addEventListener(){}}),CustomEvent:class{constructor(type,{detail}){this.type=type;this.detail=detail}},getComputedStyle:()=>({getPropertyValue:()=>''}),setTimeout,clearTimeout};
context.window.parent=context.window;vm.createContext(context);
for(const file of ['core.js','dashboard.js'])vm.runInContext(fs.readFileSync(__dirname+'/../resources/views/js/'+file,'utf8'),context);
const state=context.window.pelicanSimplify({}, {appearance:{accent_color:'#446688',individual_colors:true},servers:[{uuid:'a',color:'#aa2233'},{uuid:'b',color:'#44bb66'}]});
assert.equal(state.accentStyle('overview')['--ps-accent'],'#446688');
assert.equal(state.accentStyle('a')['--ps-accent'],'#aa2233');
state.boot.appearance.individual_colors=false;
assert.equal(state.accentStyle('a')['--ps-accent'],'#446688');assert.equal(state.accentStyle('b')['--ps-accent'],'#446688');
assert.equal(state.boot.servers[0].color,'#aa2233');
state.boot.appearance.individual_colors=true;assert.equal(state.accentStyle('a')['--ps-accent'],'#aa2233');
state.boot.appearance.accent_color='invalid';state.boot.appearance.individual_colors=false;assert.equal(state.accentStyle('b')['--ps-accent'],'#44bb66');
console.log('PASS common overview accent, individual server accents, uniform mode, retained colors and malformed accent fallback');

state.boot.appearance.accent_color='#446688';assert.equal(state.creationAccent('other')['--ps-accent'],'#446688');assert.equal(state.creationAccent('minecraft')['--ps-accent'],'#16803d');state.overviewPreferences.minecraftAlwaysGreen=false;assert.equal(state.creationAccent('minecraft')['--ps-accent'],'#446688');state.overviewPreferences.minecraftAlwaysGreen=true;state.themeDark=true;assert.equal(state.creationAccent('minecraft')['--ps-accent'],'#74d69a');
