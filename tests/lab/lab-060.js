// Browser walk over what 0.6.0 adds, on a desktop, a short laptop screen, a phone and a phone turned sideways, in
// light and dark: Admin tools (new grouping) and the phone menu, Settings in one place (Simplify's and Pelican's tabs
// in one frame, every tab and button reachable), the setup wizard, the Server Maker, the installation view, the
// server's plugins page, Explain this error, notifications (bell, ✕, Show all, Mark all read, Mark as unread, Clear read,
// Archived), host mode (admin tree, Servers tab, health strip),
// Browse Hub and the folding Customize sections. Same issue checks as lab.js; screenshots and result.json in --out.
// node lab-060.js [--stands desktop,phone-390] --base http://127.0.0.1:8001 --urls urls.json --servers servers.json --panel <dir> --php php8.3 --out shots/beta38-060
const fs = require('fs'), path = require('path'), { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > 0 ? process.argv[i + 1] : d; };
const base = arg('base'), out = arg('out'), info = JSON.parse(fs.readFileSync(arg('urls'), 'utf8'));
const servers = JSON.parse(fs.readFileSync(arg('servers'), 'utf8')), panel = arg('panel'), php = arg('php', 'php8.3');
for (const k of Object.keys(info.pages)) info.pages[k] = info.pages[k].replace(/^https?:\/\/[^/]+/, '');
fs.mkdirSync(out, { recursive: true });
const issues = [], checks = [];
const ok = (name, pass, detail = '') => { checks.push({ name, pass, detail }); if (!pass) issues.push(name + (detail ? ': ' + detail : '')); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const state = (...a) => execFileSync(php, [path.join(__dirname, 'state.php'), ...a], { cwd: panel }).toString().trim();
const overview = info.pages['Dashboard (app)'], serverUrl = (uuid) => overview.split('?')[0] + '?server=' + uuid;
const installing = servers.find((s) => s.name === 'Test server').uuid;
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROMIUM || undefined });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, baseURL: base });
  const page = await context.newPage();
  // A page that is still saving (Server Maker draft) can abort the next navigation once; try again.
  const rawGoto = page.goto.bind(page); page.goto = async (u, o) => { for (let i = 0; ; i++) { try { return await rawGoto(u, o); } catch (e) { if (i >= 3 || !/ERR_ABORTED|ERR_CONNECTION_REFUSED|ERR_EMPTY_RESPONSE|ERR_CONNECTION_RESET|interrupted by another navigation/.test(e.message)) throw e; await sleep(1500); } } };
  let current = 'login';
  page.on('pageerror', (e) => issues.push(current + ' page error: ' + e.message));
  page.on('console', (m) => { if (m.type() === 'error' && !/favicon|net::ERR|the server responded with a status of 4|WebSocket connection/.test(m.text())) issues.push(current + ' console: ' + m.text().slice(0, 200)); });
  page.on('response', (r) => { if (r.status() >= 500) issues.push(current + ' HTTP ' + r.status() + ' ' + r.url().replace(base, '')); });
  // Leaving the Server Maker with a draft asks first (beforeunload): expected; any other native dialog is an issue.
  page.on('dialog', (d) => { if (d.type() === 'beforeunload') return d.accept(); issues.push(current + ' native dialog'); d.dismiss(); });
  const settle = async () => { await page.waitForLoadState('networkidle').catch(() => {}); await sleep(500); };
  const layout = async (label) => { for (const p of await page.evaluate(require('./layout'))) issues.push(label + ' layout: ' + p); };
  const shot = async (name) => { await page.screenshot({ path: path.join(out, name + '.png') }); await layout(name); };
  const visible = (sel) => page.locator(sel).first().isVisible().catch(() => false);
  const text = (sel) => page.locator(sel).first().innerText().catch(() => '');
  const closeSheet = async () => { const close = page.locator('.ps-modal button[aria-label^="Close"]:visible').first(); if (await close.count()) await close.click().catch(() => {}); else await page.keyboard.press('Escape'); await sleep(400); };
  // A control is reachable when the person can scroll to it (only up and down, like a wheel or a swipe in a list) and
  // it is then inside the screen and the topmost element there: a tab pushed into a clipped second column is not.
  const reachable = (locator) => locator.evaluate((el) => {
    const w = el.ownerDocument.defaultView;
    for (let a = el.parentElement; a; a = a.parentElement) {
      const cs = w.getComputedStyle(a);
      if (!/(auto|scroll)/.test(cs.overflowY) || a.scrollHeight <= a.clientHeight + 1) continue;
      const r = el.getBoundingClientRect(), box = a.getBoundingClientRect();
      if (r.bottom > box.bottom) a.scrollTop += r.bottom - box.bottom + 4; else if (r.top < box.top) a.scrollTop -= box.top - r.top + 4;
    }
    let r = el.getBoundingClientRect();
    if (r.bottom > w.innerHeight) { w.scrollBy(0, r.bottom - w.innerHeight + 4); r = el.getBoundingClientRect(); }
    if (r.width < 2 || r.height < 2 || r.bottom > w.innerHeight + 1 || r.top < -1 || r.left < -1 || r.right > w.innerWidth + 1) return false;
    const hit = el.ownerDocument.elementFromPoint(r.left + Math.min(r.width / 2, 12), r.top + r.height / 2);
    return !!hit && (el === hit || el.contains(hit));
  }).catch(() => false);

  await page.goto('/login'); await settle();
  await page.fill('#form\\.login', 'lab@example.com'); await page.fill('#form\\.password', 'LabPassword123!');
  await page.click('button[type="submit"]'); await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 30000 }).catch(() => {});
  ok('login as admin', !page.url().includes('/login'), page.url());
  state('explain-key', 'on'); state('installing', installing); state('free-ports', '5');

  const stands = [['desktop', { width: 1440, height: 900 }, ['light', 'dark']], ['laptop-720', { width: 1280, height: 720 }, ['light']],
    ['phone-390', { width: 390, height: 844 }, ['light', 'dark']], ['landscape-844', { width: 844, height: 390 }, ['light']]]
    .filter(([vp]) => !arg('stands') || arg('stands').split(',').includes(vp));
  try {
    for (const [vp, size, themes] of stands) for (const theme of themes) {
      try {
        const tag = vp + '-' + theme, phone = size.width <= 600, full = vp === 'desktop' && theme === 'light';
        console.log(`[${new Date().toISOString().slice(11, 19)}] ${tag}: ${checks.length} checks, ${issues.length} issues so far`);
        fs.writeFileSync(path.join(out, 'result.json'), JSON.stringify({ version: info.version, base, partial: tag, checks, issues }, null, 2));
        await page.setViewportSize(size);
        await page.goto(overview); await page.evaluate((t) => { localStorage.setItem('pelicanSimplify.theme', t); localStorage.setItem('theme', t); }, theme);
        await page.goto(overview); await settle();
        ok(`theme ${theme} applied (${tag})`, await page.evaluate(() => document.documentElement.classList.contains('dark')) === (theme === 'dark'));

        // 1. Menus: Admin tools grouped by task, Settings under Panel, no separate Pelican settings; phones list the same under a Panel heading.
        current = `admin tools ${tag}`;
        const menuTab = page.locator('button.ps-tab[aria-controls="ps-navigation-items"]');
        if (phone && await menuTab.isVisible().catch(() => false)) { await menuTab.click(); await sleep(500); await shot(`menu-phone-${tag}`); ok(`phone menu has the Panel heading (${tag})`, /Panel/.test(await text('#ps-navigation-items'))); }
        await page.locator('button[aria-label="Admin tools"]').first().click().catch(() => {}); await sleep(500);
        const admin = await text('#ps-admin-menu');
        ok(`Admin tools opens with its groups (${tag})`, ['Servers', 'Nodes', 'People', 'Announcements', 'Tools', 'Integrations', 'Panel'].every((g) => admin.includes(g)), admin.replace(/\n+/g, ' · ').slice(0, 160));
        ok(`Admin tools: Settings under Panel, no separate Pelican settings (${tag})`, /Panel\s*\n\s*Settings/.test(admin) && !/Pelican settings/.test(admin));
        await shot(`admin-tools-${tag}`);

        // 1b. Pelican's own admin pages from Admin tools in Simplify's popup (beta.12: Simplify style, buttons in the header).
        if (full || vp === 'phone-390' && theme === 'light') {
          for (const name of ['Eggs', 'Nodes', 'People', 'Roles', 'API Keys', 'Webhooks', 'Mounts', 'Database Hosts']) {
            current = `pelican page ${name} ${tag}`;
            await page.goto(overview); await settle();
            if (phone && await menuTab.isVisible().catch(() => false)) { await menuTab.click(); await sleep(400); }
            await page.locator('button[aria-label="Admin tools"]').first().click().catch(() => {}); await sleep(400);
            const item = page.locator('#ps-admin-menu .ps-admin-item').filter({ hasText: new RegExp('^\\s*' + name + '\\s*$') }).first();
            if (!(await item.count())) { ok(`Admin tools lists ${name} (${tag})`, false); continue; }
            await item.click(); await sleep(3000);
            const frame = page.frames().find((f) => f !== page.mainFrame() && /\/admin\//.test(f.url()));
            const body = frame ? await frame.locator('body').innerText().catch(() => '') : '';
            ok(`Pelican page ${name} opens in the popup frame (${tag})`, !!frame && body.trim().length > 8 && !/Server Error|404|403/.test(body.slice(0, 200)), frame ? frame.url().replace(base, '') : 'no frame');
            await shot(`pelican-${name.toLowerCase().replace(/ /g, '-')}-${tag}`);
            await closeSheet();
          }
        }
        // 2. Settings in one place: every tab reachable, Pelican's tabs load Pelican's form, Save reachable.
        current = `settings ${tag}`;
        if (!(await page.locator('#ps-admin-menu').isVisible().catch(() => false))) {
          await page.goto(overview); await settle();
          if (phone && await menuTab.isVisible().catch(() => false)) { await menuTab.click(); await sleep(400); }
          await page.locator('button[aria-label="Admin tools"]').first().click().catch(() => {}); await sleep(500);
        }
        await page.locator('#ps-admin-menu').getByRole('button', { name: 'Settings', exact: true }).click().catch(() => {}); await sleep(2500);
        const frame = page.frames().find((f) => /pelican-simplify-settings/.test(f.url()));
        ok(`Settings opens in a popup frame (${tag})`, !!frame && await visible('.ps-modal'));
        if (frame) {
          await frame.waitForLoadState('networkidle').catch(() => {});
          await shot(`settings-${tag}`);
          const tabs = frame.locator('.fi-tabs-item');
          const names = (await tabs.allInnerTexts()).map((t) => t.trim().split('\n')[0].trim());
          ok(`Settings lists Simplify and Pelican tabs (${tag})`, names.length >= 15 && ['Captcha', 'Mail', 'Backup', 'OAuth', 'Misc'].every((n) => names.includes(n)), names.length + ' tabs');
          const unreachable = [];
          for (let i = 0; i < names.length; i++) if (!(await reachable(tabs.nth(i)))) unreachable.push(names[i]);
          ok(`every settings tab can be reached (${tag})`, !unreachable.length, unreachable.join(', '));
          const pelicanStart = names.lastIndexOf('General');
          for (let i = 0; i < names.length; i++) {
            if (!full && i !== 0 && i !== pelicanStart && i !== names.length - 1) continue;
            const name = names[i], key = (i >= pelicanStart ? 'pelican-' : '') + name.toLowerCase().replace(/[^a-z]+/g, '-');
            current = `settings ${key} ${tag}`;
            await tabs.nth(i).click().catch(() => {}); await sleep(1500); await frame.waitForLoadState('networkidle').catch(() => {});
            const panelText = await frame.locator('.fi-sc-tabs-tab.fi-active, [role=tabpanel]:visible').first().innerText().catch(() => '');
            ok(`settings tab ${key} shows its settings (${tag})`, panelText.trim().length > 40, panelText.length + ' characters');
            // Every button in the open tab, and Save, must be reachable (0.6.0-beta.7: buttons hidden under the frame edge).
            const buttons = frame.locator('.fi-sc-tabs-tab.fi-active button:visible, .fi-sc-tabs-tab.fi-active ~ * button:visible, form .fi-form-actions button:visible, button:has-text("Save"):visible');
            const hidden = [];
            for (let b = 0, n = Math.min(await buttons.count(), 40); b < n; b++) if (!(await reachable(buttons.nth(b)))) hidden.push((await buttons.nth(b).innerText().catch(() => '')).trim().slice(0, 30) || (await buttons.nth(b).getAttribute('aria-label')) || 'button');
            ok(`settings tab ${key}: every button reachable (${tag})`, !hidden.length, hidden.join(', '));
            const save = frame.locator('button:visible').filter({ hasText: /^\s*Save/ }).last();
            if (await save.count()) ok(`settings tab ${key}: Save reachable (${tag})`, await reachable(save));
            await page.screenshot({ path: path.join(out, `settings-${key}-${tag}.png`) });
          }
        }
        await closeSheet();

        // 3. Customize: sections fold open and closed.
        current = `customize ${tag}`;
        await page.goto(overview); await settle();
        if (phone && await menuTab.isVisible().catch(() => false)) { await menuTab.click(); await sleep(400); }
        const customize = page.locator('[aria-label="Customize overview"]:visible').first();
        if (await customize.count()) {
          await customize.click(); await sleep(500);
          const head = page.locator('#ps-overview-appearance-menu .ps-customize-head.is-toggle').nth(1);
          const before = await head.getAttribute('aria-expanded').catch(() => null);
          await head.click().catch(() => {}); await sleep(400);
          ok(`Customize section folds open/closed (${tag})`, before !== null && await head.getAttribute('aria-expanded').catch(() => null) !== before);
          await shot(`customize-${tag}`); await page.keyboard.press('Escape'); await sleep(300);
        } else ok(`Customize overview button visible (${tag})`, false);

        // 4. Notifications: the bell, ✕ on one, Show all, Mark all read, Clear read and Archived.
        current = `notifications ${tag}`;
        state('notify', '4');
        await page.goto(overview); await settle();
        if (phone && await menuTab.isVisible().catch(() => false)) { await menuTab.click(); await sleep(400); }
        const bell = page.locator('.ps-notify button[aria-label="Notifications"]').first();
        ok(`bell counts 5 unread (4 notifications, 1 announcement) (${tag})`, (await bell.locator('.ps-notify-count').innerText().catch(() => '')).trim() === '5');
        await bell.click().catch(() => {}); await sleep(600);
        ok(`bell lists the notifications (${tag})`, /Lab notification 1/.test(await text('#ps-notify-menu')));
        await shot(`bell-${tag}`);
        await page.locator('#ps-notify-menu button[aria-label="Clear Lab notification 4"]').click().catch(() => {});
        await page.waitForFunction(() => !/Lab notification 4/.test(document.querySelector('#ps-notify-menu')?.innerText || ''), null, { timeout: 5000 }).catch(() => {});
        ok(`✕ clears one notification (${tag})`, !/Lab notification 4/.test(await text('#ps-notify-menu')));
        await page.locator('#ps-notify-menu button[aria-label="Show all notifications"]').click().catch(() => {}); await sleep(1200);
        await page.waitForLoadState('networkidle').catch(() => {});
        ok(`Show all opens the full list (${tag})`, /Lab notification 1/.test(await text('.ps-modal')));
        await page.locator('.ps-modal button:visible').filter({ hasText: 'Mark all read' }).first().click().catch(() => {}); await sleep(1000);
        ok(`Mark all read (${tag})`, !/\d+ unread/.test(await text('.ps-modal')));
        await shot(`notifications-all-${tag}`);
        // 0.6.0-beta.8 (#170): Mark as unread on a read one counts it again.
        const unread = page.locator('.ps-modal button:visible').filter({ hasText: 'Mark as unread' }).first();
        if (await unread.count()) {
          await unread.click(); await sleep(1000);
          ok(`Mark as unread counts it again (${tag})`, /1 unread/.test(await text('.ps-modal')), (await text('.ps-modal')).split('\n').slice(0, 3).join(' · '));
          await page.locator('.ps-modal button:visible').filter({ hasText: 'Mark all read' }).first().click().catch(() => {}); await sleep(800);
        }
        const clear = page.locator('.ps-modal button:visible').filter({ hasText: 'Clear read' }).first();
        if (await clear.count()) { await clear.click(); await sleep(1200); }
        const archived = page.locator('.ps-modal button:visible').filter({ hasText: /Archived/ }).first();
        ok(`Clear read moves them to Archived (${tag})`, await archived.count() > 0);
        if (await archived.count()) {
          await archived.click(); await sleep(800);
          ok(`Archived lists the cleared ones (${tag})`, /Lab notification/.test(await text('.ps-modal')));
          // #170: an archived announcement has no pin button (it would do nothing visible there).
          const archivedPin = await page.locator('.ps-modal .ps-notify-pin:visible').count();
          if (await page.locator('.ps-modal button:visible').filter({ hasText: 'Mark as unread' }).count()) ok(`no pin button under Archived (${tag})`, archivedPin === 0, archivedPin + ' pin buttons');
          await shot(`notifications-archived-${tag}`);
        }
        await closeSheet();

        // 4b. Host mode (0.6.0-beta.8, #169): one menu with an admin tree and a Servers tab, health strip at the bottom,
        // pages and servers in the frame beside it; on phones the menu slides in. Skipped on builds without the tree.
        current = `host mode ${tag}`;
        await page.evaluate((k) => localStorage.setItem(k, '1'), 'pelicanSimplify.hostMode.lab-admin');
        await page.goto(overview); await settle();
        if (await page.locator('.ps-host-tree').count()) {
          const toggle = page.locator('.ps-host-menu-toggle:visible').first();
          if (await toggle.count()) { await toggle.click(); await sleep(500); }
          ok(`host mode: admin tree and health strip (${tag})`, await visible('.ps-host-tree') && await visible('.ps-host-health-bar'));
          await shot(`host-tree-${tag}`);
          const leaf = page.locator('.ps-host-leaf:visible').first();
          if (await leaf.count()) {
            const label = (await leaf.innerText()).trim();
            await leaf.click({ timeout: 8000 }); await sleep(2500);
            ok(`host mode: a page opens in the frame (${tag})`, await visible('.ps-host-frame:not(.is-server) iframe') || await visible('.ps-host-frame:not(.is-server)'), label);
            await shot(`host-page-${tag}`);
          }
          if (await toggle.count()) { await toggle.click(); await sleep(500); }
          await page.locator('.ps-host-tabs button').filter({ hasText: 'Servers' }).first().click().catch(() => {}); await sleep(500);
          ok(`host mode: Servers tab lists every server (${tag})`, await page.locator('.ps-host-server:visible').count() === info.total, await page.locator('.ps-host-server:visible').count() + ' of ' + info.total);
          await shot(`host-servers-${tag}`);
          await page.locator('.ps-host-server:visible').filter({ hasText: info.server_name }).first().click().catch(() => {}); await sleep(3000);
          ok(`host mode: a server opens beside the menu (${tag})`, await visible('.ps-host-frame.is-server') && (await text('.ps-host-server-name')).includes(info.server_name));
          await shot(`host-server-${tag}`);
        } else ok(`host mode shows the host layout (${tag})`, await visible('.ps-host'));
        await page.evaluate((k) => localStorage.setItem(k, '0'), 'pelicanSimplify.hostMode.lab-admin');

        // 5. Installation view of a server that is still installing: progress steps and Kill.
        current = `installation view ${tag}`;
        await page.goto(serverUrl(installing)); await settle();
        ok(`installation view with steps and Kill (${tag})`, await visible('.ps-install') && /Installing|Preparing/.test(await text('.ps-install')) && await visible('.ps-install-kill'));
        await shot(`install-${tag}`);

        // 6. Server page: Explain this error and the plugins page.
        current = `explain ${tag}`;
        await page.goto(serverUrl(info.server)); await settle();
        const explain = page.locator('.ps-console-explain:visible').first();
        if (await explain.count()) {
          await explain.click(); await sleep(8000);
          const answer = await text('.ps-explain-layer');
          ok(`Explain answers with a readable message (fake key) (${tag})`, /API key is not valid|could not|Everything seems|went wrong/i.test(answer), answer.replace(/\n+/g, ' · ').slice(0, 140));
          await shot(`explain-${tag}`);
          await page.locator('.ps-explain-layer button:visible').filter({ hasText: 'Done' }).first().click().catch(() => page.keyboard.press('Escape')); await sleep(400);
        } else ok(`Explain button on the console (${tag})`, phone, 'not visible');
        // Workspaces one after the other: after closing one with ✕ the next must dock beside the console again, as in
        // 0.5.2 (0.6.0-beta.7 and beta.8: it opened as a centred popup).
        if (!phone && size.height > 500) {
          current = `workspace after workspace ${tag}`;
          const openWidget = (l) => page.locator(`.ps-widget[aria-label^="${l}"]`).first().click({ timeout: 8000 }).then(() => true).catch(() => false);
          const dockedOnly = () => page.evaluate(() => { const shown = [...document.querySelectorAll('.ps-modal')].filter((e) => e.checkVisibility()); return shown.length > 0 && shown.every((e) => e.closest('.ps-dock')); });
          if (await openWidget('Game settings')) {
            await sleep(1200);
            await page.locator('.ps-modal button[aria-label^="Close"]:visible').first().click().catch(() => {}); await sleep(800);
            await openWidget('Player management'); await sleep(1200);
            ok(`second workspace docks beside the console (${tag})`, await dockedOnly());
            await shot(`workspace-second-${tag}`);
            await closeSheet();
          }
        }
        current = `plugins page ${tag}`;
        const pluginsEntry = phone ? null : page.locator('.ps-widget[aria-label^="Plugins"]').first();
        if (pluginsEntry && await pluginsEntry.isVisible().catch(() => false)) await pluginsEntry.click();
        else { const more = page.locator('button.ps-phone-section[aria-controls="ps-phone-more-menu"]'); if (await more.count()) { await more.click(); await sleep(300); await page.locator('#ps-phone-more-menu button').filter({ hasText: /^\s*Plugins/ }).first().click().catch(() => {}); } }
        await sleep(2500);
        const plugins = await text('.ps-modal-layer[data-open="true"], .ps-server-workspace.has-dock');
        ok(`plugins page shows Installed and Add plugin (${tag})`, /Installed/.test(plugins) && /Add plugin|Search|Modrinth/i.test(plugins), plugins.replace(/\n+/g, ' · ').slice(0, 120));
        await shot(`plugins-${tag}`); await closeSheet();

        // 7. Server Maker: Minecraft types, every step to Review, another game.
        current = `server maker ${tag}`;
        await page.goto(overview); await settle();
        const create = phone ? page.locator('.ps-tab-create > button.ps-tab') : page.locator('button[aria-label="Create server"]:visible');
        await create.first().click().catch(() => {}); await sleep(600);
        const createItem = page.locator('.ps-tab-create-menu .ps-menu-item:visible').first(); if (await createItem.count()) { await createItem.click(); }
        await sleep(3000);
        const maker = page.frames().find((f) => /simple-server-creator/.test(f.url()));
        ok(`Server Maker opens (${tag})`, !!maker);
        // Start from a clean Server Maker: a draft from an earlier stand resumes at a later step.
        const discardDraft = async (f) => { const d = f.locator('.ssc-discard:visible').first(); if (!(await d.count())) return; await d.click().catch(() => {}); await sleep(600); // The confirmation can sit in the frame or, when the Server Maker runs in Simplify's popup, in the page around it.
      for (const where of [f, page]) { const yes = where.locator('button:visible').filter({ hasText: /^\s*Discard draft\s*$/ }).last(); if (await yes.count()) { await yes.click().catch(() => {}); break; } }
      await sleep(2500); };
        if (maker) {
          await maker.waitForLoadState('networkidle').catch(() => {});
          await discardDraft(maker);
          const types = await maker.locator('.ssc-minecraft-type').allInnerTexts();
          ok(`Server Maker lists the Minecraft types (${tag})`, ['Vanilla', 'Plugins', 'Mods', 'Modpack', 'Bedrock'].every((t) => types.some((x) => x.includes(t))), types.map((t) => t.split('\n')[0]).join(', '));
          await maker.locator('.ssc-game').nth(9).waitFor({ state: 'attached', timeout: 8000 }).catch(() => {});
          ok(`Server Maker lists other games (${tag})`, await maker.locator('.ssc-game').count() >= 10);
          await shot(`maker-game-${tag}`);
          await maker.locator('.ssc-minecraft-type').filter({ hasText: 'Vanilla' }).first().click().catch(() => {}); await sleep(800); await maker.waitForLoadState('networkidle').catch(() => {}); await sleep(1500);
          const bar = maker.locator('.ssc-next-button:visible').first();
          ok(`Server Maker bar: Next reachable (${tag})`, await reachable(bar));
          let steps = 0;
          for (; steps < 6; steps++) {
            if (!(await bar.isVisible().catch(() => false))) break;
            // Required text fields on the way (the server name): filled like a person would.
            const name = maker.locator('input[id="form.name"]:visible');
            if (await name.count() && !(await name.inputValue().catch(() => 'x'))) await name.fill('Lab maker test').catch(() => {});
            await bar.click().catch(() => {}); await sleep(1800);
            await page.screenshot({ path: path.join(out, `maker-step${steps + 2}-${tag}.png`) });
          }
          const review = await maker.locator('body').innerText().catch(() => '');
          ok(`Server Maker reaches Review with Create server (${tag})`, /Review/.test(review) && await maker.locator('.ssc-create-button, button:has-text("Create server")').first().isVisible().catch(() => false), steps + ' steps');
          await shot(`maker-review-${tag}`);
          await discardDraft(maker);
        }
        await closeSheet();

        // 8. Browse Hub in the plugin manager (live Pelican Hub; the first visit fills the cache).
        if (!vp.startsWith('landscape')) {
          current = `browse hub ${tag}`;
          await page.goto(info.pages['ManagePlugins (admin)']); await settle();
          await page.locator('.fi-tabs-item').filter({ hasText: 'Browse Hub' }).first().click().catch(() => {});
          const started = Date.now();
          await page.waitForFunction(() => !/Loading the Pelican Hub/.test(document.querySelector('main')?.innerText || ''), null, { timeout: 45000 }).catch(() => {});
          const hub = await text('main');
          ok(`Browse Hub lists themes and plugins (${tag})`, !/Loading the Pelican Hub/.test(hub) && (/Install on Hub|Themes|Plugins/.test(hub) || /could not be reached/.test(hub)), `${Date.now() - started} ms`);
          await shot(`hub-${tag}`);
        }
        // 9. Setup wizard, run again from Simplify settings: every step until the last, then Cancel (nothing changes).
        if (vp === 'desktop' && theme === 'light' || vp === 'phone-390' && theme === 'light') {
          current = `setup wizard ${tag}`;
          await page.goto(info.pages['SimplifySettings (admin)']); await settle();
          await page.locator('button:visible').filter({ hasText: 'Setup wizard' }).first().click().catch(() => {}); await sleep(600);
          await page.getByRole('button', { name: 'Start setup' }).click().catch(() => {}); await sleep(2500);
          ok(`setup wizard starts with the system check (${tag})`, /SYSTEM CHECK|System check/i.test(await text('main')));
          await shot(`wizard-1-${tag}`);
          const forward = () => page.locator('main button:visible').filter({ hasText: /^\s*(Go to first setup|Next|Continue)\s*$/ }).last();
          let n = 1;
          for (; n < 10 && await forward().count(); n++) { await forward().click().catch(() => {}); await sleep(1500); await page.screenshot({ path: path.join(out, `wizard-${n + 1}-${tag}.png`) }); await layout(`wizard step ${n + 1} ${tag}`); }
          ok(`setup wizard walks to its last step (${tag})`, n >= 3 && /Finish/i.test(await page.locator('main button:visible').allInnerTexts().then((t) => t.join(' '))), n + ' steps');
          await page.locator('.ps-rerun-banner button').filter({ hasText: 'Cancel' }).first().click().catch(() => {}); await sleep(1500);
        }
      } catch (e) {
        // A stand that breaks off is a failed check; the next stand starts from a fresh page.
        ok(`stand ${vp}-${theme} runs to the end`, false, current + ': ' + e.message.split('\n')[0].slice(0, 200));
        await page.screenshot({ path: path.join(out, `crash-${vp}-${theme}.png`) }).catch(() => {});
      }
    }
    // A full node: the Server Maker must say why it cannot go on (beta.7 stayed on step 3 without a message).
    current = 'server maker without a free port';
    state('free-ports', '0');
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(info.pages['CreateServer (app)'] + '?simplify_embed=1'); await settle();
    const discard = page.locator('.ssc-discard:visible').first();
    if (await discard.count()) { await discard.click(); await sleep(600); await page.locator('button:visible').filter({ hasText: /^\s*Discard draft\s*$/ }).last().click().catch(() => {}); await sleep(2500); }
    await page.locator('.ssc-minecraft-type').filter({ hasText: 'Vanilla' }).first().click().catch(() => {}); await sleep(1500);
    for (let i = 0; i < 3; i++) {
      const name = page.locator('input[id="form.name"]:visible'); if (await name.count() && !(await name.inputValue())) await name.fill('Lab full node');
      await page.locator('.ssc-next-button:visible').first().click().catch(() => {}); await sleep(2000);
    }
    const stuck = /3\s*Server/.test(await text('[class*=is-current]'));
    const said = await page.locator('main').innerText().then((t) => /port|allocation/i.test(t)).catch(() => false);
    ok('Server Maker on a full node explains why it cannot go on', !stuck || said, stuck ? 'stays on step 3 (Server), no visible message' : 'moved on');
    await shot('maker-full-node');
    await discard.click().catch(() => {}); await sleep(600); await page.locator('button:visible').filter({ hasText: /^\s*Discard draft\s*$/ }).last().click().catch(() => {}); await sleep(1500);
    state('free-ports', '5');
    // Website embed (0.6.0-beta.12): the public list is empty until a server is switched on; one server, one game and
    // a group of servers each show only those. Read as a visitor would: the JSON and the iframe page, no session.
    current = 'website embed';
    const visitor = await browser.newContext({ viewport: { width: 1100, height: 800 }, baseURL: base });
    const vpage = await visitor.newPage();
    vpage.on('pageerror', (e) => issues.push('website embed page error: ' + e.message));
    const embedJson = async () => (await (await visitor.request.get('/simplify/embed/servers.json')).json().catch(() => ({}))).servers;
    const cards = async (query) => { await vpage.goto('/simplify/embed?' + query); await vpage.waitForLoadState('networkidle').catch(() => {}); await sleep(800); return vpage.evaluate(() => { const host = document.querySelector('#simplify-servers'); const root = host?.shadowRoot || host; return [...(root?.querySelectorAll('.card') || [])].map((c) => c.innerText.split('\n').map((l) => l.trim()).filter(Boolean).slice(0, 3).join(' ')); }); };
    state('embed', 'none');
    const none = await embedJson();
    ok('website embed: empty list while no server is switched on', Array.isArray(none) && none.length === 0, JSON.stringify(none)?.slice(0, 80));
    const rust = servers.find((s) => s.name === 'Rust weekend').uuid;
    const shown = state('embed', [info.server, rust, info.other_server].join(',')).split(',');
    const list = await embedJson() || [];
    ok('website embed: lists exactly the switched-on servers', list.length === 3, list.map((x) => x.name).join(', '));
    ok('website embed: no loopback or private address published', list.every((x) => !x.address || !/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.)/.test(x.address)), list.map((x) => x.address).join(', '));
    const all = await cards('page=1'); await vpage.screenshot({ path: path.join(out, 'embed-all.png') });
    ok('website embed page shows every switched-on server', all.length === 3, all.join(', '));
    const one = await cards('server=' + shown[0]); await vpage.screenshot({ path: path.join(out, 'embed-one-server.png') });
    ok('website embed: one server', one.length === 1, one.join(', '));
    const game = await cards('game=Rust'); await vpage.screenshot({ path: path.join(out, 'embed-one-game.png') });
    ok('website embed: one game', game.length === 1 && /Rust/.test(game[0]), game.join(', '));
    const group = await cards('servers=' + shown.slice(0, 2).join(',')); await vpage.screenshot({ path: path.join(out, 'embed-group.png') });
    ok('website embed: a group of servers', group.length === 2, group.join(', '));
    await visitor.close();
    state('embed', 'none');
    // Emergency Explain page (signed in as root admin).
    current = 'explain emergency page';
    const res = await page.goto('/simplify/explain'); await settle();
    ok('emergency Explain page renders', res && res.status() === 200 && /Explain/i.test(await text('body')));
    await shot('explain-emergency');
  } finally {
    state('installed', installing); state('explain-key', 'off'); state('notify', '0');
  }
  await browser.close();
  fs.writeFileSync(path.join(out, 'result.json'), JSON.stringify({ version: info.version, base, checks, issues }, null, 2));
  const failed = checks.filter((c) => !c.pass).length;
  console.log(`${info.version} 0.6.0 walk: ${checks.length - failed}/${checks.length} checks passed, ${issues.length} issues`);
  for (const i of issues.slice(0, 60)) console.log('  - ' + i);
  process.exitCode = failed || issues.length ? 1 : 0;
})().catch((e) => { console.error(e); process.exit(1); });
