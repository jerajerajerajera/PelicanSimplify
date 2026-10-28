// Browser test harness: serves the dashboard Blade view (with mock Livewire data), the real asset
// bundles and simple embedded Filament pages. Used by theme-browser.js; also runnable on its own:
//   node tests/browser-harness.js 8902   → open http://127.0.0.1:8902/?mode=server&theme=dark
const fs = require('fs'), http = require('http'), path = require('path');
// Blade views are read as English (lang/en), like the panel shows them by default.
const { lang, locale } = require('./lang-en.js');
const dir = ['ar', 'fa', 'he', 'ur'].includes(locale.split('_')[0]) ? 'rtl' : 'ltr';
const repo = path.resolve(__dirname, '..');
const views = path.join(repo, 'resources/views');
const tools = path.join(repo, 'node_modules');
const read = (p) => fs.readFileSync(path.join(views, p), 'utf8');

// Mirrors AssetController: bundle definitions come from BuildInfo::BUNDLES.
function asset(name) {
  const php = fs.readFileSync(path.join(repo, 'src/Services/BuildInfo.php'), 'utf8');
  const map = {};
  for (const m of php.matchAll(/'([a-z]+\.(?:css|js))' => \[([^\]]*)\]/g)) map[m[1]] = [...m[2].matchAll(/'([^']+)'/g)].map((x) => x[1]);
  if (!map[name]) return null;
  return map[name].map(read).join('\n');
}

const icon = (fill, letter) => `<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" rx="8" fill="${fill}"/><text x="32" y="42" font-size="30" text-anchor="middle" fill="#fff" font-family="sans-serif">${letter}</text></svg>`;
const servers = [
  { uuid: '11111111-1111-1111-1111-111111111111', alias: 'Survival', name: 'survival-world-01', color: '#2f9e44', software: 'Paper', game: 'minecraft', game_label: 'Minecraft', mine: true, status: 'running', linked: true, created: 1760000000, port: 25565, node_id: 1, permissions: { 'control.start': true, 'control.stop': true, 'control.restart': true }, url: '/?mode=server', server_icon: '/img/a.svg', software_icon: '/img/paper.svg', egg_icon: null, icon: null, background: '/bg/minecraft-valley.jpg', players: 3, max_players: 20, reported_version: 'Paper 1.21.4', motd: '§aWelcome to §lSurvival\n§7Have fun!', usage: { cpu_percent: 23.4, cpu_limit: 200, memory_bytes: 2.4e9, memory_limit_bytes: 4294967296, disk_bytes: 5.2e9, network_rx_bytes: 1.2e8, network_tx_bytes: 3.4e8 } },
  { uuid: '22222222-2222-2222-2222-222222222222', alias: '', name: 'Creative build server with a very long name', color: '#3b82f6', software: 'Vanilla', game: 'minecraft', game_label: 'Minecraft', mine: true, status: 'offline', linked: true, created: 1760500000, port: 25566, node_id: 1, permissions: { 'control.start': true, 'control.stop': true, 'control.restart': true }, url: '/?mode=server', server_icon: null, software_icon: '/img/vanilla.svg', background: null, players: 0, max_players: 10, usage: {} },
  { uuid: '33333333-3333-3333-3333-333333333333', alias: 'Modpack', name: 'atm10', color: '#a855f7', software: 'NeoForge', game: 'minecraft', game_label: 'Minecraft', mine: false, status: 'starting', linked: true, created: 1759000000, port: 25570, node_id: 2, permissions: {}, url: '/?mode=server', server_icon: '/img/b.svg', software_icon: '/img/neo.svg', background: '/bg/minecraft-harbor.jpg', players: null, max_players: null, usage: { cpu_percent: 88, memory_bytes: 7.1e9, memory_limit_bytes: 8589934592 } },
  { uuid: '44444444-4444-4444-4444-444444444444', alias: '', name: 'Valheim', color: null, software: 'Valheim', game: 'other', game_label: 'Valheim', mine: false, status: 'running', linked: false, created: 1761000000, port: 2456, node_id: 2, permissions: {}, url: null, server_icon: null, software_icon: null, egg_icon: '/img/v.svg', background: null, players: null, usage: { cpu_percent: 5, memory_bytes: 1.1e9 } },
];
const hostHealth = { state: 'healthy', label: 'Healthy', available: 2, hosts: 2, cpu_percent: 31.2, memory_used: 12e9, memory_total: 32e9, disk_used: 220e9, disk_total: 500e9, network_rx_bytes: 5e9, network_tx_bytes: 8e9, network_rx_per_second: 120000, network_tx_per_second: 340000, metric_states: {},
  nodes: [{ id: 1, name: 'node-eu-1', state: 'healthy', label: 'Healthy', available: 1, hosts: 1, cpu_percent: 20, memory_used: 6e9, memory_total: 16e9, disk_used: 100e9, disk_total: 250e9, network_rx_bytes: 2e9, network_tx_bytes: 3e9 }, { id: 2, name: 'node-eu-2', state: 'warning', label: 'High load', available: 1, hosts: 1, cpu_percent: 87, memory_used: 14e9, memory_total: 16e9, disk_used: 120e9, disk_total: 250e9, network_rx_bytes: 3e9, network_tx_bytes: 5e9 }] };
const allPerms = Object.fromEntries(['control.console', 'control.start', 'control.stop', 'control.restart', 'file.read', 'file.read-content', 'file.update', 'settings.rename', 'backup.read', 'backup.download', 'backup.restore', 'schedule.read', 'activity.read', 'startup.read', 'websocket.connect', 'database.read', 'user.read'].map((p) => [p, true]));
const server = { ...servers[0], uuid: servers[0].uuid, short: '11111111', address: 'play.example.com:25565', memory: 4096, disk: 20480, cpu: 200, image: 'ghcr.io/pelican-eggs/yolks:java_21', startup: 'java -Xms128M -jar server.jar', sftp: { username: 'admin.11111111', url: 'sftp://admin.11111111@panel.example:2022' }, package_kind: 'plugins', native_sections: { schedules: '/frame/native', activity: '/frame/native', startup: '/frame/native', databases: '/frame/native', access: '/frame/native' }, owner: true, minecraft: true, can_resources: true, permissions: allPerms, properties: { motd: '§aWelcome to §lSurvival', 'max-players': '20', difficulty: 'normal', gamemode: 'survival', pvp: 'true', 'white-list': 'false', 'view-distance': '10', 'spawn-protection': '16' }, properties_available: true, pending: null, pack: { modpack_name: '', version: '1.21.4' }, player_names: [{ name: 'Steve' }, { name: 'Alex' }], background_id: 'auto', icon: '/img/a.svg', root_admin: true, files_url: '/frame/files', backups_url: '/frame/native', admin_server_url: '/admin', backup_options: { enabled: true, interval_minutes: 30, retention: 2 } };
// Sample announcements (?announcements=1): one of each source, as Announcements::forUser() returns them.
const announcements = [
  { key: 'own-0000000000000001', source: 'own', category: 'warning', tone: 'warn', title: 'Maintenance on Sunday', text: 'The servers restart between 03:00 and 04:00 for a Pelican update. Save your builds before then.', from: 'jera', audience: 'everyone', at: 1761400000, time: '2 hours ago', until: 'until Sun 26 Oct 03:00', action: null, dismissed: false },
  { key: 'own-0000000000000002', source: 'own', category: 'success', tone: 'ok', title: 'New modpack server', text: 'ATM10 is online. Join with play.example.com:25570.', from: 'jera', audience: 'everyone', at: 1761390000, time: '5 hours ago', until: null, action: { label: 'Open server', url: '/?mode=server' }, dismissed: false },
  { key: 'panel-wings-2-1.0.1', source: 'panel', category: 'info', tone: 'info', title: 'Wings 1.0.1 is available for node-eu-2', text: 'node-eu-2 runs Wings 1.0.0. Update Wings on that machine when it suits you.', from: 'Panel', audience: 'admins', at: 1761380000, time: 'today', until: null, action: { label: 'How to update', url: 'https://pelican.dev/docs/wings/update' }, dismissed: false },
];
function boot(mode, extra = {}) {
  const base = { banner_upload_bytes: 5242880, build_version: 'harness', admin_url: '/admin', legacy_create_url: '/frame/native', create_url: '/frame/creator', can_create: true, create_games: ['minecraft', 'other'], web: false, native_list_url: '/', profile_url: '/frame/native', appearance: { accent_color: '', individual_colors: true }, panel_name: 'Pelican', features: { host_health: true, settings: true, files: true, players: true, backups: true, packages: true, software: true, resources: true, advanced: true, activity: true, schedules: true, dashboard: true }, settings_url: '/frame/settings', website_url: null, list_url: '/', server: null, servers: [], user: 'jera', avatar: null, favicon_url: '/favicon.svg', admin_pages: [{ label: 'Panel', items: [{ key: 'health', label: 'Health', url: '/frame/native', icon: 'heart' }, { key: 'eggresource', label: 'Eggs', url: '/frame/native', icon: 'egg' }] }], ...extra };
  if (mode === 'server') return { ...base, server, available_colors: { '#2f9e44': 'Green', '#3b82f6': 'Blue', '#a855f7': 'Purple', '#f59e0b': 'Amber', '#ef4444': 'Red' }, background_choices: [{ id: 'valley', name: 'Valley', url: '/bg/minecraft-valley.jpg' }, { id: 'harbor', name: 'Harbor', url: '/bg/minecraft-harbor.jpg' }], uploaded_backgrounds: [] };
  return { ...base, servers, host_health: hostHealth };
}

function bladeToHtml(src) {
  return src
    .replace(/@include\('pelican-simplify::dashboard-icons'\)/g, () => read('dashboard-icons.blade.php'))
    .replace(/@include\('pelican-simplify::motd-editor'\)/g, () => read('motd-editor.blade.php'))
    .replace(/@include\('pelican-simplify::announcement-bars'\)/g, () => read('announcement-bars.blade.php'))
    .replace(/@include\('pelican-simplify::announcement-chips'\)/g, () => read('announcement-chips.blade.php'))
    .replace(/@include\('pelican-simplify::announcement-bar'\)/g, () => read('announcement-bar.blade.php'))
    .replace(/@include\('pelican-simplify::health-checks', \['open' => '((?:[^'\\]|\\.)*)'\]\)/g, (_, open) => read('health-checks.blade.php').replace('{!! $open !!}', open.replace(/\\'/g, "'")))
    .replace(/\{\{--[\s\S]*?--\}\}/g, '')
    .replace(/<x-filament-panels::page>|<\/x-filament-panels::page>/g, '')
    .replace(/@assets[\s\S]*?@endassets/g, '')
    .replace(/@js\(\$this->initial\(\)\)/g, 'window.__boot')
    // The subdomain card is kept (its data comes from connectionData); the website extension's fallback is dropped.
    .replace(/@if\(\\Simplify\\Panel\\Services\\Subdomains::active\(\)\)/g, '')
    .replace(/@elseif\(\\Simplify\\Panel\\Services\\Extensions::web\(\)\)[\s\S]*?@endif/g, '')
    .replace(/@if\(\\Simplify\\Panel\\Services\\Extensions::web\(\)\)[\s\S]*?@endif/g, '')
    .replace(/@error\([^)]*\)[\s\S]*?@enderror/g, '')
    .replace(/@csrf/g, '')
    .replace(/\{\{[^}]*UploadLimits[^}]*\}\}/g, '5 MB')
    .replace(/\{\{[^}]*\}\}/g, '#')
    .replace(/@php[\s\S]*?@endphp/g, '');
}

const wireMock = `window.__wire={listing:async()=>__boot.servers,hostHealth:async()=>__boot.host_health,refreshInfo:async()=>({}),socket:async()=>{throw new Error('no ws')},backupList:async()=>({configured:true,items:[{uuid:'b1',name:'Automatic backup 1',completed:true,successful:true,bytes:1.2e9},{uuid:'b2',name:'Automatic backup 2',completed:false}]}),players:async()=>({whitelist:[{name:'Steve'}],ops:[{name:'jera'}],'banned-players':[]}),installedPackages:async()=>({context:{kind:'plugins',loader:'paper',version:'1.21.4'},items:[{file:'EssentialsX-2.22.0.jar',enabled:true,title:'EssentialsX',version:'2.22.0',source:'spigot',label:'SpigotMC via GitHub',key:'spigot:9089',icon:'',page:'https://www.spigotmc.org/resources/9089/'},{file:'LuckPerms-Bukkit-5.4.141.jar.disabled',enabled:false,title:'LuckPerms Bukkit',version:'5.4.141',source:'',label:'Added by hand',key:'',icon:'',page:''}]}),packageDetails:async(keys)=>Object.fromEntries(keys.map((k)=>[k,{installable:true,version:'7.3.10',fits:k!=='hangar:Old',for:k==='hangar:Old'?'1.16.5 – 1.20.6':'1.21 – 1.21.4',label:'Modrinth'}])),togglePackage:async()=>{},checkPluginHealth:async()=>({disabled:[],watching:false}),explainPluginFailure:async()=>({ok:true,found:true,summary:'The plugin needs a newer Paper version.',issues:[],lines:12,model:'demo'}),installPackage:async()=>({version:'7.3.10',label:'Modrinth',fits:true}),advancedData:async()=>({eggs:[{id:1,name:'Paper'}]}),connectionData:async()=>({}),saveSettings:async()=>({pending:true}),saveAlias:async(a)=>({alias:a}),power:async()=>{},overviewPower:async()=>{},packageSearch:async()=>({items:[{key:'modrinth:p1',source:'modrinth',label:'Modrinth',title:'WorldEdit',author:'EngineHub',description:'In-game map editor',icon:'/img/a.svg',page:'https://modrinth.com/plugin/worldedit',fits:true,downloads:1,also:[{source:'hangar',label:'Hangar',key:'hangar:WorldEdit',fits:true,page:'https://hangar.papermc.io/EngineHub/WorldEdit'}]},{key:'hangar:Old',source:'hangar',label:'Hangar',title:'OldPlugin',author:'someone',description:'Last updated for 1.20',icon:'',page:'https://hangar.papermc.io/x/Old',fits:false,downloads:1,also:[]}],context:{version:'1.21.4'},offline:[]}),availableServerColors:async()=>__boot.available_colors,saveServerColor:async(c)=>c,saveOverviewGroups:async(g)=>g,announcements:async()=>__boot.announcements||[],pushSettings:async()=>({key:'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U',admin:true,preferences:{announcements:true,updates:true},devices:[{id:'d1',label:'Safari on iPhone · installed app',created_at:1761300000,last_sent_at:Math.floor(Date.now()/1000)-86400,current:false},{id:'d2',label:'Edge on Windows',created_at:1761200000,last_sent_at:null,current:false}]}),savePushPreferences:async(p)=>({announcements:true,updates:true,...p}),removePushDevice:async()=>({devices:[]}),sendTestPush:async()=>({ok:true,message:'Test sent. It should appear within a few seconds.'}),dismissAnnouncement:async(k)=>(__boot.announcements=(__boot.announcements||[]).map((a)=>a.key===k?{...a,dismissed:true}:a)),restoreAnnouncement:async(k)=>(__boot.announcements=(__boot.announcements||[]).map((a)=>a.key===k?{...a,dismissed:false}:a)),discard:async()=>{},restartChanges:async()=>{},fileRevision:async()=>'0'.repeat(64)};`;

function filamentShell(body, opts = {}) {
  const dark = opts.theme === 'dark';
  return `<!doctype html><html lang="${locale}" dir="${dir}" class="fi ${dark ? 'dark' : ''}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${opts.title || 'Harness'}</title>
<style>/* minimal Filament base */*,::before,::after{box-sizing:border-box}body{margin:0;font-family:Inter,ui-sans-serif,system-ui,sans-serif;background:#fafafa;color:#18181b}html.dark body{background:#09090b;color:#fafafa}.fi-layout{display:flex;min-height:100vh}.fi-main-ctn{flex:1;display:flex;flex-direction:column;min-width:0}.fi-main{flex:1;margin:0 auto;width:100%;max-width:80rem;padding:2rem}.fi-page{display:grid;gap:2rem}.fi-topbar{height:64px;background:#fff;border-bottom:1px solid #e4e4e7}.fi-footer{padding:16px;text-align:center;font-size:13px;color:#71717a}.fi-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:8px;background:#f59e0b;color:#fff;border:0;font:600 14px Inter,sans-serif}.fi-section{background:#fff;border-radius:12px;box-shadow:0 1px 2px #0001;border:1px solid #e4e4e7;padding:16px}.fi-input-wrp{border:1px solid #d4d4d8;border-radius:8px;background:#fff;display:flex}.fi-input{border:0;background:transparent;padding:8px 10px;font:inherit;width:100%}.fi-ta-ctn{background:#fff;border-radius:12px;border:1px solid #e4e4e7}.fi-ta-row td{padding:10px 14px;border-top:1px solid #e4e4e7}.fi-header{display:flex;justify-content:space-between}</style>
${opts.head || ''}</head><body class="fi-body"><div class="fi-layout"><div class="fi-main-ctn">${opts.topbar === false ? '' : '<div class="fi-topbar"></div>'}<main class="fi-main"><div class="fi-page">${body}</div></main><footer class="fi-footer"><p>© 2026 Pelican</p></footer></div></div>${opts.tail || ''}</body></html>`;
}

function sharedAssets(themeConfig) {
  const v = '?v=h';
  return `<link id="simplify-core-css" rel="stylesheet" href="/assets/core.css${v}"><script>window.simplifyThemeConfig=${JSON.stringify(themeConfig)};window.simplifyLang=${JSON.stringify(lang)};</script><script src="/assets/core.js${v}"></script>`;
}
const themeConfig = (extra = {}) => ({ global_theme: true, legacy_theme: true, console_dark: true, accent_color: '', individual_colors: true, legacy_page: false, ...extra });

function dashboardPage(q) {
  const mode = q.get('mode') || 'overview';
  const b = boot(mode, q.get('announcements') ? { announcements } : {});
  const theme = q.get('theme') || 'light';
  const head = `<script>try{localStorage.setItem('pelicanSimplify.theme','${theme}')}catch{}</script>` + sharedAssets(themeConfig()) +
    `<link id="simplify-dashboard-css" rel="stylesheet" href="/assets/dashboard.css?v=h"><script src="/assets/dashboard.js?v=h"></script>`;
  const tail = `<script>window.__boot=${JSON.stringify(b)};${wireMock}</script><script>document.addEventListener('alpine:init',()=>{});</script><script defer src="/vendor/focus.js"></script><script defer src="/vendor/alpine.js"></script>`;
  let body = bladeToHtml(read('dashboard.blade.php')).replace(/pelicanSimplify\(\$wire,/g, 'pelicanSimplify(window.__wire,');
  return filamentShell(body, { head, tail, theme, title: 'Dashboard', topbar: true });
}

function framePage(kind, q) {
  const embedded = '';
  const head = sharedAssets(themeConfig()) + (['creator', 'settings'].includes(kind) ? '<link rel="stylesheet" href="/assets/wizard.css?v=h">' : '');
  let body = '';
  if (kind === 'files') {
    // Mirrors Filament 4's page + table markup (header with breadcrumbs, table container, pagination).
    const rows = Array.from({ length: 40 }, (_, i) => `<tr class="fi-ta-row"><td class="fi-ta-cell"><a href="/frame/files/edit/file${i}.yml/">file${i}.yml</a></td><td class="fi-ta-cell">${i} KB</td><td class="fi-ta-cell">2026-10-0${(i % 9) + 1}</td></tr>`).join('');
    body = `<div class="fi-page"><section class="fi-page-header-main-ctn"><header class="fi-header fi-header-has-breadcrumbs"><div><nav class="fi-breadcrumbs"><a href="#">Files</a> / <a href="#">plugins</a></nav><h1 class="fi-header-heading">Files</h1></div></header><div class="fi-page-main"><div class="fi-page-content"><div class="fi-ta"><div class="fi-ta-ctn fi-ta-ctn-with-header fi-ta-ctn-with-footer"><div class="fi-ta-main"><div class="fi-ta-header-ctn"><div class="fi-ta-header-toolbar" style="padding:12px;display:flex;gap:8px"><button class="fi-btn" wire:click="mountAction('upload')"><svg class="fi-btn-icon" width="16" height="16"></svg>Upload</button><button class="fi-btn" wire:click="mountAction('new_folder')">New folder</button></div></div><div class="fi-ta-content-ctn"><table class="fi-ta-table" style="width:100%;border-collapse:collapse"><thead><tr><th class="fi-ta-header-cell">Name</th><th class="fi-ta-header-cell">Size</th><th class="fi-ta-header-cell">Modified</th></tr></thead><tbody>${rows}</tbody></table></div><nav class="fi-pagination">Showing 1 to 40</nav></div></div></div></div></div></section></div>`;
  } else if (kind === 'editor') {
    const lines = Array.from({ length: 120 }, (_, i) => `<div>line ${i + 1}: value-${i}</div>`).join('');
    body = `<div class="fi-page fi-resource-edit-file-page"><section class="fi-page-header-main-ctn"><div class="fi-page-main"><div class="fi-page-content"><form><div class="fi-sc"><section class="fi-section fi-section-has-header"><header class="fi-section-header"><h3 class="fi-section-header-heading">Editing: config.yml</h3></header><div class="fi-section-content-ctn"><div class="fi-section-content"><label class="fi-fo-field-label-content">Syntax</label><div class="fi-input-wrp"><select class="fi-select-input"><option>yaml</option></select></div><div class="fme-wrapper" style="height:650px"><div class="fme-container"><div class="fme-element-wrapper"><div class="fme-element monaco-editor" style="height:100%;overflow:auto;font:13px monospace">${lines}</div></div></div></div></div></div><footer class="fi-section-footer"><button type="button" class="fi-btn" wire:click="mountAction('fm_save_and_close')">Save &amp; close</button><button type="button" class="fi-btn" wire:click="mountAction('fm_save')">Save</button><button type="button" class="fi-btn fi-color-danger" wire:click="mountAction('fm_cancel')">Cancel</button></footer></section></div></form></div></div></section></div>`;
  } else if (kind === 'widgets') {
    // Mirrors resources/views/plugin-widgets.blade.php with one stats widget (like Player Counter); ?empty=1 has none.
    const stats = ['Hostname', 'Players', 'Map'].map((l) => `<div class="fi-section fi-wi-stats-overview-stat"><span>${l}</span><strong>${l === 'Players' ? '3 / 20' : 'Survival'}</strong></div>`).join('');
    body = `<div class="simplify-plugin-widgets" style="display:grid;gap:12px">${q.get('empty') ? '' : `<div class="fi-wi-stats-overview" style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">${stats}</div>`}</div>`;
  } else if (kind === 'creator') {
    body = `<div class="ssc-wizard"><div class="ssc-draft-bar"><span class="ssc-draft-status">Draft ready</span><span class="ssc-draft-indicator"></span><div class="ssc-delete-concept"><button type="button" class="ssc-draft-action ssc-delete-button">✕</button></div></div><nav class="ssc-progress" style="--ssc-steps: 4; --ssc-step: 1"><button class="is-current"><span>1</span>Server Type</button><span class="ssc-step-arrow">&gt;</span><button disabled><span>2</span>Version &amp; Content</button><span class="ssc-step-arrow">&gt;</span><button disabled><span>3</span>Server properties</button><span class="ssc-step-arrow">&gt;</span><button disabled><span>4</span>Review</button><span class="ssc-progress-count">Step 1 of 4</span></nav><form><div class="fi-sc-wizard fi-contained"><div class="fi-section"><div class="ssc-cards ssc-card-options ssc-type-options" role="radiogroup">${['Vanilla', 'Vanilla+', 'Modded'].map((l, i) => `<label class="ssc-card"><input class="ssc-card-input" type="radio" name="t" ${i === 0 ? 'checked' : ''}><span class="ssc-card-content"><svg class="ssc-outline-icon" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m12 3 9 5v8l-9 5-9-5V8z"/></svg><span class="ssc-card-title">${l}</span><span class="ssc-card-description">Description of ${l}</span></span></label>`).join('')}</div><div style="margin-top:16px"><label class="fi-fo-field-label-content">Server name</label><div class="fi-input-wrp"><input class="fi-input" placeholder="Amazing Fox"></div><p class="fi-fo-field-helper-text">This name is used by the server and dashboard.</p></div></div><div class="fi-sc-wizard-footer" style="display:flex;justify-content:space-between;margin-top:16px"><button type="button" class="fi-btn ssc-back-button">Back</button><button type="button" class="fi-btn ssc-next-button">Next</button></div></div></form></div>`;
  } else if (kind === 'settings') {
    body = `<div class="ps-settings-page is-embedded"><div class="ssc-wizard ps-settings-form"><form>${Array.from({ length: 5 }, (_, i) => `<section class="fi-section"><header class="fi-section-header"><h3 class="fi-section-header-heading">Section ${i + 1}</h3><p class="fi-section-header-description">Settings description</p></header><div class="fi-section-content"><label class="fi-fo-field-label-content">Option</label><div class="fi-input-wrp"><input class="fi-input" value="Value ${i}"></div></div></section>`).join('')}<footer class="ps-settings-actions"><button class="fi-btn">Save settings</button></footer></form></div><button type="button" class="ps-diagnostics-toggle">System checks</button></div>`;
  } else {
    body = `<div class="fi-header"><h1 class="fi-header-heading">Native page</h1></div><section class="fi-section"><p>Native Pelican content</p><button class="fi-btn">Action</button><button class="fi-btn fi-color-danger">Delete</button></section><div class="fi-ta-ctn"><table style="width:100%">${Array.from({ length: 8 }, (_, i) => `<tr class="fi-ta-row"><td>Row ${i}</td></tr>`).join('')}</table></div>`;
  }
  return filamentShell(body, { head, title: kind, topbar: false });
}

function handler(req, res) {
  const url = new URL(req.url, 'http://x');
  const send = (type, body, code = 200) => { res.writeHead(code, { 'Content-Type': type, 'Cache-Control': 'no-store' }); res.end(body); };
  try {
    if (url.pathname === '/') return send('text/html', dashboardPage(url.searchParams));
    if (url.pathname.startsWith('/assets/')) {
      const name = url.pathname.slice(8);
      if (name.endsWith('.woff2')) return send('font/woff2', fs.readFileSync(path.join(repo, 'resources/fonts', path.basename(name))));
      const body = asset(name);
      if (body == null) return send('text/plain', 'missing', 404);
      return send(name.endsWith('.css') ? 'text/css' : 'application/javascript', body);
    }
    if (url.pathname === '/vendor/alpine.js') return send('application/javascript', fs.readFileSync(tools + '/alpinejs/dist/cdn.min.js'));
    if (url.pathname === '/vendor/focus.js') return send('application/javascript', fs.readFileSync(tools + '/@alpinejs/focus/dist/cdn.min.js'));
    if (url.pathname.startsWith('/bg/')) return send(url.pathname.endsWith('.svg') ? 'image/svg+xml' : 'image/jpeg', fs.readFileSync(path.join(repo, 'resources/backgrounds', path.basename(url.pathname))));
    if (url.pathname.startsWith('/img/')) { const n = path.basename(url.pathname, '.svg'); return send('image/svg+xml', icon({ a: '#2f9e44', b: '#7c3aed', paper: '#1e40af', vanilla: '#65a30d', neo: '#ea580c', v: '#0f766e' }[n] || '#555', n[0].toUpperCase())); }
    if (url.pathname === '/favicon.svg') return send('image/svg+xml', fs.readFileSync(path.join(repo, 'resources/branding/favicon.svg')));
    if (url.pathname.startsWith('/frame/files/edit/')) return send('text/html', framePage('editor', url.searchParams));
    if (url.pathname.startsWith('/frame/files')) return send('text/html', framePage('files', url.searchParams));
    if (url.pathname.startsWith('/frame/')) return send('text/html', framePage(url.pathname.slice(7), url.searchParams));
    send('text/plain', 'not found', 404);
  } catch (e) { send('text/plain', String(e.stack), 500); }
}

/** Starts the harness on a free port; resolves to the http.Server. */
function start(port = 0) {
  return new Promise((resolve) => {
    const server = http.createServer(handler);
    server.listen(port, '127.0.0.1', () => resolve(server));
  });
}
module.exports = { start };
if (require.main === module) start(Number(process.argv[2] || 8902)).then((server) => console.log('harness on http://127.0.0.1:' + server.address().port));
