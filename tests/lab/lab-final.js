// What 0.6.0-beta.13 adds, on one desktop and one phone: Only the add-ons (wizard choice, Pelican's menu with the
// Simplify group, the dashboard out of the way, back to Full Simplify), egg updates (notification, Update, Put back,
// Update all, Move servers, Test first with a test server that installs or fails, automatic updates) against the
// lab egg host (egg-host.js on https://127.0.0.1:8443), the notification window's scheduler warning, the app
// notification switch on new announcements, and the snapshot channel. Screenshots and result.json in --out.
// node lab-final.js --base http://127.0.0.1:8001 --urls urls.json --servers servers.json --panel <dir> --php php8.4 --out shots/beta38-final
const fs = require('fs'), path = require('path'), { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > 0 ? process.argv[i + 1] : d; };
const base = arg('base'), out = arg('out'), info = JSON.parse(fs.readFileSync(arg('urls'), 'utf8'));
const panel = arg('panel'), php = arg('php', 'php8.3');
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
fs.mkdirSync(out, { recursive: true });
const checks = [];
const ok = (name, pass, detail = '') => { checks.push({ name, pass: !!pass, detail: String(detail).slice(0, 300) }); console.log((pass ? 'ok   ' : 'FAIL ') + name + (detail ? ' | ' + String(detail).slice(0, 160) : '')); };
const state = (...a) => execFileSync(php, [path.join(__dirname, 'state.php'), ...a], { cwd: panel, encoding: 'utf8' });
const eggInfo = (ids) => JSON.parse(state('egg-info', ids));
const VANILLA = 2, SPARE = 5, SPARE2 = 6; // Vanilla Minecraft has three lab servers; the other two have none.

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, baseURL: base });
  const page = await ctx.newPage();
  page.on('dialog', (d) => d.accept().catch(() => {}));
  // Pelican's own console page talks to the fake Wings over a websocket it cannot open: those errors are the lab's.
  let pelicanConsole = false; const errors = []; page.on('pageerror', (e) => { if (!pelicanConsole) errors.push(e.message); });
  const settle = async () => { await page.waitForLoadState('networkidle').catch(() => {}); await sleep(600); };
  const shot = (name) => page.screenshot({ path: path.join(out, name + '.png') }).catch(() => {});
  const text = (sel) => page.locator(sel).filter({ visible: true }).first().innerText().catch(() => '');
  const confirm = async (label) => { const b = page.locator('.ps-modal [data-confirm]:visible').filter({ hasText: label }).last(); await b.waitFor({ timeout: 5000 }).catch(() => {}); await b.click().catch(() => {}); await sleep(2500); await settle(); };
  const eggTab = async () => { await page.goto(info.pages['SimplifySettings (admin)'] + '?section=egg-updates::data::tab'); await settle(); return text('.ps-egg-updates'); };
  const eggRow = (name) => page.locator('.ps-egg-updates .ps-plugin').filter({ has: page.locator('strong', { hasText: name }) });

  // The window sits behind an icon in the top bar, or in the account menu when the bar is narrow.
  const openPush = async () => {
    await page.locator('button:visible').filter({ hasText: 'lab-admin' }).first().click().catch(() => {}); await sleep(700);
    await page.locator('button[aria-label="Notifications on this device"]').first().click({ timeout: 5000 }).catch(() => {});
  };
  await page.goto('/login'); await settle();
  await page.fill('#form\\.login', 'lab@example.com'); await page.fill('#form\\.password', 'LabPassword123!');
  await page.click('button[type="submit"]'); await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 30000 }).catch(() => {});
  const overview = info.pages['Dashboard (app)'];

  try {
    // 1. Notifications on this device: the scheduler warning when the heartbeats are ten minutes old, and what is pushed.
    state('scheduler', 'stale');
    await page.goto(overview); await settle();
    await openPush();
    await sleep(2000);
    const notices = await text('.ps-push');
    ok('Notifications on this device warns that the scheduler has not run', /scheduler is not running/i.test(notices), notices.replace(/\n+/g, ' · ').slice(0, 300));
    ok('root admin sees the cron line to add', /schedule:run/.test(notices));
    ok('the window explains what is sent as an app notification', /app notification|push/i.test(notices));
    await shot('notifications-scheduler');
    state('scheduler', 'fresh');
    await page.goto(overview); await settle();
    await openPush();
    await sleep(2000);
    ok('no scheduler warning while the scheduler runs', !/scheduler is not running/i.test(await text('.ps-push')));
    await page.keyboard.press('Escape'); await sleep(400);
    // New announcement: "Also send as app notification" starts switched on.
    await page.goto(info.pages['ManageAnnouncements (admin)']); await settle();
    await page.locator('button:visible, a:visible').filter({ hasText: /New announcement/ }).first().click().catch(() => {}); await sleep(1500);
    const push = await page.evaluate(() => {
      const label = [...document.querySelectorAll('label, span, p')].find((el) => /Also send as app notification/.test(el.textContent) && el.offsetParent);
      const field = label?.closest('.fi-fo-field, .fi-fo-field-wrp, [data-field-wrapper], .fi-sc-component') || label?.parentElement;
      const input = field?.querySelector('input[type=checkbox]'), sw = field?.querySelector('[role=switch]');
      return label ? (input ? input.checked : sw ? sw.getAttribute('aria-checked') === 'true' : null) : 'no field';
    });
    ok('new announcement: Also send as app notification is on by default', push === true, String(push));
    await shot('announcement-new');
    state('notify', '0');

    // 2. Egg updates, Ask first: the check finds two new versions and tells root admins.
    state('egg-reset'); state('egg-mode', 'ask'); state('egg-host', [VANILLA, SPARE, SPARE2].join(','), '0'); state('egg-run');
    state('egg-host', [VANILLA, SPARE].join(','), '1');
    state('egg-run');
    let e = eggInfo([VANILLA, SPARE].join(','));
    ok('egg check finds both new versions (Ask first)', Object.keys(e.state.found || {}).length === 2, JSON.stringify(e.state.found));
    ok('Ask first changes no egg by itself', e.eggs.every((x) => x.description !== 'Lab update 1'));
    ok('root admin gets an egg update notification', e.notifications.some((n) => /egg updates? (available|for)/i.test(n)), e.notifications[0]);
    // The bell: the notification opens the Egg updates tab.
    await page.goto(overview); await settle();
    await page.locator('.ps-notify button[aria-label="Notifications"]').first().click().catch(() => {}); await sleep(800);
    const bell = await text('#ps-notify-menu');
    ok('bell lists the egg update notification', /egg update/i.test(bell), bell.replace(/\n+/g, ' · ').slice(0, 200));
    await shot('egg-bell');
    await page.locator('#ps-notify-menu a, #ps-notify-menu button').filter({ hasText: /^\s*Egg updates\s*$/ }).first().click().catch(() => {}); await sleep(3500);
    const settingsFrame = page.frames().find((f) => /pelican-simplify-settings/.test(f.url()));
    if (settingsFrame) { await settingsFrame.waitForLoadState('networkidle').catch(() => {}); await settingsFrame.locator('.ps-egg-updates').waitFor({ timeout: 10000 }).catch(() => {}); }
    const frameText = settingsFrame ? await settingsFrame.locator('.ps-egg-updates').innerText().catch(() => '') : '';
    ok('the notification opens Simplify settings on Egg updates', /Available updates/.test(frameText), settingsFrame ? settingsFrame.url().replace(base, '') : 'no settings frame');
    await shot('egg-from-notification');

    // The tab: modes, the list, Update, Put back, Update all, Move servers.
    let tab = await eggTab();
    const modes = await page.evaluate(() => { const el = [...document.querySelectorAll('select, [id]')].find((x) => /egg_updates\.mode/.test(x.id)); return el ? (el.tagName === 'SELECT' ? [...el.options].map((o) => o.text) : (el.closest('.fi-fo-field, .fi-fo-field-wrp') || el).innerText.split('\n')) : []; });
    if (modes.length < 4) { await page.locator('[id*="egg_updates.mode"]').first().click().catch(() => {}); await sleep(600); modes.push(...await page.locator('.choices__list--dropdown .choices__item, [role=option]').allInnerTexts().catch(() => [])); await page.keyboard.press('Escape'); }
    ok('Egg updates tab offers Ask first, Test first, automatic and Off', modes.length >= 4, modes.join(' / '));
    ok('Egg updates lists both eggs with Update and Test first', (await page.locator('.ps-egg-updates .ps-plugin').count()) >= 2 && /Test first/.test(tab) && /Update all/.test(tab) && /Check now/.test(tab), tab.replace(/\n+/g, ' · ').slice(0, 200));
    await shot('egg-tab');
    const spareName = e.eggs.find((x) => x.id === SPARE).name, vanillaName = e.eggs.find((x) => x.id === VANILLA).name;
    await eggRow(spareName).locator('button').filter({ hasText: /^\s*Update\s*$/ }).click().catch(() => {}); await sleep(600);
    await confirm('Update');
    e = eggInfo(String(SPARE));
    ok('Update installs the new version and keeps the old one', e.eggs[0].description === 'Lab update 1' && e.eggs[0].backups >= 1, JSON.stringify(e.eggs[0]).slice(0, 200));
    tab = await eggTab();
    ok('updated egg is listed under Updated by Simplify with Put back', /Updated by Simplify/.test(tab) && /Put back/.test(tab));
    await page.locator('.ps-egg-updates .ps-plugin').filter({ has: page.locator('strong', { hasText: spareName }) }).locator('button').filter({ hasText: /^\s*Put back\s*$/ }).last().click().catch(() => {}); await sleep(600);
    await confirm('Put back');
    e = eggInfo(String(SPARE));
    ok('Put back restores the previous version', e.eggs[0].description !== 'Lab update 1', e.eggs[0].description);
    tab = await eggTab();
    ok('a put-back egg is listed again, marked Put back', /Put back/.test(await eggRow(spareName).innerText().catch(() => '')), tab.replace(/\n+/g, ' · ').slice(0, 200));
    await shot('egg-put-back');
    await page.locator('.ps-egg-updates button').filter({ hasText: /Update all/ }).first().click().catch(() => {}); await sleep(600);
    await confirm('Update all');
    e = eggInfo([VANILLA, SPARE].join(','));
    ok('Update all updates every listed egg', e.eggs.every((x) => x.description === 'Lab update 1'), e.eggs.map((x) => x.name + ': ' + x.description).join(', '));
    ok('running servers keep their image until Move servers', e.servers.filter((s) => s.egg_id === VANILLA).every((s) => !/java_25/.test(s.image)), e.servers.map((s) => s.image).join(', '));
    tab = await eggTab();
    ok('Move servers is offered for the egg with servers', /still use/.test(tab) && /Move servers/.test(tab), tab.replace(/\n+/g, ' · ').slice(0, 240));
    await shot('egg-updated-move');
    await page.locator('.ps-egg-updates .ps-plugin').filter({ has: page.locator('strong', { hasText: vanillaName }) }).locator('button').filter({ hasText: /Move servers/ }).first().click().catch(() => {}); await sleep(600);
    await confirm('Move servers');
    e = eggInfo(String(VANILLA));
    ok('Move servers gives the servers the new Docker image', e.servers.length > 0 && e.servers.every((s) => /java_25/.test(s.image)), e.servers.map((s) => s.image).join(', '));
    ok('servers with their own startup command keep it', e.servers.every((s) => !/--lab/.test(s.startup)), e.servers.map((s) => s.startup).join(' | '));

    // 3. Test first, the test server installs and starts: then the real egg is updated and everything is cleaned up.
    state('egg-mode', 'test'); state('egg-host', String(VANILLA), '2');
    state('egg-run');
    e = eggInfo(String(VANILLA));
    const t = e.test_servers[0];
    ok('Test first makes one test server on a test port (45000-45999)', e.test_servers.length === 1 && t.port >= 45000 && t.port <= 45999, JSON.stringify(t));
    ok('the test server uses a copy of the egg', e.copies.some((c) => /\(update check\)/.test(c)), e.copies.join(', '));
    ok('the real egg waits for the test', e.eggs[0].description === 'Lab update 1');
    ok('the test server is left out of server lists', /^12 of 13$/.test(e.hidden_from_query), e.hidden_from_query);
    await page.goto(overview); await settle();
    const listed = await text('main');
    ok('the overview does not show the test server', !/Egg update check/.test(listed) && /Survival/.test(listed));
    const embed = await (await ctx.request.get('/simplify/embed/servers.json')).json().catch(() => ({}));
    ok('the website embed does not show the test server', !(embed.servers || []).some((s) => /Egg update check/.test(s.name || '')));
    tab = await eggTab();
    ok('Egg updates shows the running test', /Installing on a test server|Testing/.test(tab), tab.replace(/\n+/g, ' · ').slice(0, 200));
    await shot('egg-testing');
    state('egg-test', 'installed'); const s1 = state('egg-tick'); const s2 = state('egg-tick');
    e = eggInfo(String(VANILLA));
    ok('after install and start the real egg is updated', e.eggs[0].description === 'Lab update 2', s1.trim().replace(/\s+/g, ' ') + ' / ' + s2.trim().replace(/\s+/g, ' '));
    ok('test server, test port and copy are removed', e.test_servers.length === 0 && e.test_ports.length === 0 && e.copies.length === 0, JSON.stringify({ s: e.test_servers.length, p: e.test_ports.length, c: e.copies }));
    ok('root admin hears the egg was updated after a test', e.notifications.some((n) => /updated after a test/.test(n)), e.notifications[0]);

    // 4. Test first, the install fails: the current egg stays, the notification has the reason and the last log lines.
    state('egg-host', String(VANILLA), '3'); state('egg-run');
    state('egg-test', 'install_failed'); state('egg-tick');
    e = eggInfo(String(VANILLA));
    ok('a failed test keeps the current egg', e.eggs[0].description === 'Lab update 2', e.eggs[0].description);
    ok('a failed test is cleaned up too', e.test_servers.length === 0 && e.test_ports.length === 0 && e.copies.length === 0);
    const failed = e.notifications.find((n) => /not installed/.test(n)) || '';
    ok('the failure notification has the reason and the last install lines', /did not install/.test(failed) && /exited with 1/.test(failed), failed);
    tab = await eggTab();
    ok('Egg updates shows why the test failed', /the test server did not install/.test(tab), tab.replace(/\n+/g, ' · ').slice(0, 200));
    await shot('egg-test-failed');

    // 5. Automatic: an egg without servers is updated, one that was put back is left alone.
    state('egg-mode', 'auto'); state('egg-host', [SPARE2].join(','), '4'); state('egg-run');
    e = eggInfo(String(SPARE2));
    ok('automatic mode updates an egg without servers', e.eggs[0].description === 'Lab update 4', e.eggs[0].description);
    await eggTab(); await shot('egg-auto');
    state('egg-mode', 'ask');

    // 6. Only the add-ons: the setup wizard asks first, then Settings → General switches.
    await page.goto(info.pages['SimplifySettings (admin)']); await settle();
    await page.locator('button:visible').filter({ hasText: 'Setup wizard' }).first().click().catch(() => {}); await sleep(600);
    await page.getByRole('button', { name: 'Start setup' }).click().catch(() => {}); await sleep(2500);
    for (let i = 0; i < 3 && !/How much Simplify\?/.test(await text('main')); i++) { await page.locator('main button:visible').filter({ hasText: /^\s*(Go to first setup|Next|Continue)\s*$/ }).last().click().catch(() => {}); await sleep(1500); }
    const wizard = await text('main');
    ok('setup wizard asks How much Simplify? with Full Simplify and Only the add-ons', /How much Simplify\?/.test(wizard) && /Full Simplify/.test(wizard) && /Only the add-ons/.test(wizard));
    await shot('wizard-how-much');
    await page.locator('main label').filter({ hasText: 'Only the add-ons' }).first().click().catch(() => {}); await sleep(1500);
    const steps = await text('main');
    ok('Only the add-ons shortens the wizard (no Features, People & access, Look)', /Add-ons/.test(steps) && !/People & access/.test(steps), steps.split('\n').slice(0, 12).join(' · '));
    await shot('wizard-addons');
    await page.locator('.ps-rerun-banner button').filter({ hasText: 'Cancel' }).first().click().catch(() => {}); await sleep(1500);

    const before = JSON.parse(execFileSync(php, ['-r', 'echo file_get_contents("storage/app/private/simplify/features.json") ?: file_get_contents("storage/app/simplify/features.json");'], { cwd: panel, encoding: 'utf8' }) || '{}');
    await page.goto(info.pages['SimplifySettings (admin)']); await settle();
    await page.locator('label').filter({ hasText: 'Only the add-ons' }).first().click().catch(() => {}); await sleep(1200);
    await shot('settings-general-addons');
    await page.locator('button:visible').filter({ hasText: /^\s*Save( changes| settings)?\s*$/ }).last().click().catch(() => {}); await sleep(3000); await settle();
    await page.goto(overview); await settle();
    ok('Only the add-ons: the Simplify dashboard sends you to Pelican\'s own list', !/\/simplify/.test(new URL(page.url()).pathname) && !(await page.locator('.ps').count()), page.url().replace(base, ''));
    await shot('addons-pelican-list');
    const createBtn = page.locator('a[href*="simple-server-creator"]:visible, a[href*="create"]:visible, button[aria-label*="Create" i]:visible, a[aria-label*="Create" i]:visible').first();
    ok('Pelican\'s list has a Create server button', await createBtn.count() > 0);
    if (await createBtn.count()) { await createBtn.click().catch(() => {}); await sleep(2500); await settle(); }
    const created = page.url() + ' ' + await page.locator('.fi-dropdown-panel:visible, [role=menu]:visible').first().innerText().catch(() => '') + ' ' + await text('main');
    ok('Create server on Pelican\'s list offers the Server Maker', /simple-server-creator|Server Maker|Simplify creator/i.test(created), created.replace(/\s+/g, ' ').slice(0, 160));
    await shot('addons-create-server');
    await page.locator('a:visible').filter({ hasText: /Simplify creator|Server Maker/ }).first().click().catch(() => {}); await sleep(3000); await settle();
    ok('the Server Maker opens from Pelican\'s list with Only the add-ons', await page.locator('.ssc-minecraft-type, .ssc-game').count() > 0, page.url().replace(base, ''));
    await shot('addons-server-maker');
    await page.goto('/admin'); await settle();
    const nav = await text('.fi-sidebar');
    ok('Pelican\'s admin menu has a Simplify group with Server Maker, Announcements and Simplify settings', /Simplify/.test(nav) && /Server Maker/.test(nav) && /Announcements/.test(nav) && /Simplify settings|Settings/.test(nav), nav.replace(/\n+/g, ' · ').slice(-260));
    await shot('addons-admin-menu');
    await page.goto('/'); await settle(); pelicanConsole = true;
    await page.locator('main').getByText('Survival', { exact: false }).first().click().catch(() => {}); await sleep(3000);
    ok('Pelican\'s own server page opens with Only the add-ons', /\/server\/[^/]+\/console/.test(page.url()) && !(await page.locator('.ps').count()), page.url().replace(base, ''));
    await shot('addons-server-page'); await page.goto('/admin'); await settle(); pelicanConsole = false;
    const phone = await browser.newContext({ viewport: { width: 390, height: 844 }, baseURL: base, storageState: await ctx.storageState() });
    const pp = await phone.newPage(); await pp.goto('/'); await pp.waitForLoadState('networkidle').catch(() => {}); await sleep(800);
    await pp.screenshot({ path: path.join(out, 'addons-phone.png') }); await phone.close();
    // Back to Full Simplify: the earlier settings return.
    await page.goto(info.pages['SimplifySettings (admin)']); await settle();
    await page.locator('label').filter({ hasText: 'Full Simplify' }).first().click().catch(() => {}); await sleep(1200);
    await page.locator('button:visible').filter({ hasText: /^\s*Save( changes| settings)?\s*$/ }).last().click().catch(() => {}); await sleep(3000); await settle();
    const after = JSON.parse(execFileSync(php, ['-r', 'echo file_get_contents("storage/app/private/simplify/features.json") ?: file_get_contents("storage/app/simplify/features.json");'], { cwd: panel, encoding: 'utf8' }) || '{}');
    const keys = ['dashboard', 'host_mode', 'global_theme', 'legacy_theme', 'host_health'];
    ok('back to Full Simplify restores the earlier settings', keys.every((k) => before[k] === after[k]) && !after.addons_only, keys.map((k) => `${k} ${before[k]}→${after[k]}`).join(', '));
    await page.goto(overview); await settle();
    ok('Full Simplify: the dashboard is back', /\/simplify/.test(page.url()) && await page.locator('.ps').count() > 0);
  } catch (err) {
    ok('final walk runs to the end', false, err.message.split('\n')[0]);
    await shot('crash');
  } finally {
    try { state('mode', 'full'); state('egg-reset'); state('notify', '0'); } catch {}
  }

  // 7. The snapshot channel: beta.13 on Snapshot sees the newest snapshot as an update.
  const channel = execFileSync(php, ['-r', `require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); auth()->setUser(App\\Models\\User::where('email','lab@example.com')->first());
    $f=Simplify\\Panel\\Services\\FeatureSettings::all(); $f['simplify_snapshot']=true; Simplify\\Panel\\Services\\FeatureSettings::save($f,true);
    $p=App\\Models\\Plugin::all()->first(fn($p)=>$p->id==='pelican-simplify'); $m=new ReflectionMethod(Simplify\\Panel\\Services\\PluginUpdater::class,'latestVersion');
    $latest=$m->invoke(null,$p,true); $f['simplify_snapshot']=false; Simplify\\Panel\\Services\\FeatureSettings::save($f,true);
    echo json_encode(['installed'=>$p->version,'latest'=>$latest,'newer'=>$latest?Simplify\\Panel\\Services\\PluginUpdater::isNewer($latest,$p->version):null]);`], { cwd: panel, encoding: 'utf8' });
  const c = JSON.parse(channel.trim().split('\n').pop());
  ok('Snapshot channel: a newer snapshot counts as an update for this beta', /snapshot/.test(c.latest || '') && c.newer === true, channel.trim());
  ok('no page errors', errors.length === 0, errors.slice(0, 3).join(' | '));
  fs.writeFileSync(path.join(out, 'result.json'), JSON.stringify({ version: info.version, base, checks }, null, 2));
  const failed = checks.filter((x) => !x.pass).length;
  console.log(`${info.version} final walk: ${checks.length - failed}/${checks.length} checks passed`);
  await browser.close();
  process.exit(failed ? 1 : 0);
})();
