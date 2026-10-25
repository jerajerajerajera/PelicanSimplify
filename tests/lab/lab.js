// Browser test of a lab panel with Simplify: logs in as the admin, opens every Simplify page on desktop and phone,
// in light and dark, switches the overview views and filters, opens the server workspaces and the MOTD editor.
// Records HTTP errors, page errors and console errors; writes screenshots and a JSON result.
// node lab.js --base http://127.0.0.1:8001 --urls urls.json --out shots/beta38
const fs = require('fs'), path = require('path');
const { chromium } = require('playwright');
const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > 0 ? process.argv[i + 1] : d; };
const base = arg('base'), out = arg('out'), info = JSON.parse(fs.readFileSync(arg('urls'), 'utf8'));
for (const k of Object.keys(info.pages)) info.pages[k] = info.pages[k].replace(/^https?:\/\/[^/]+/, '');
fs.mkdirSync(out, { recursive: true });
const issues = [], checks = [];
const ok = (name, pass, detail = '') => { checks.push({ name, pass, detail }); if (!pass) issues.push(name + (detail ? ': ' + detail : '')); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROMIUM || undefined });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, baseURL: base });
  const page = await context.newPage();
  const rawGoto = page.goto.bind(page); page.goto = async (u, o) => { for (let i = 0; ; i++) { try { return await rawGoto(u, o); } catch (e) { if (i >= 3 || !/ERR_CONNECTION_REFUSED|ERR_EMPTY_RESPONSE|ERR_CONNECTION_RESET/.test(e.message)) throw e; await sleep(1500); } } };
  const watch = (p, label) => {
    p.on('pageerror', (e) => issues.push(label() + ' page error: ' + e.message));
    p.on('console', (m) => { if (m.type() === 'error' && !/favicon|net::ERR|the server responded with a status of 4|WebSocket connection/.test(m.text())) issues.push(label() + ' console: ' + m.text().slice(0, 200)); });
    p.on('response', (r) => { if (r.status() >= 500) issues.push(label() + ' HTTP ' + r.status() + ' ' + r.url().replace(base, '')); });
    p.on('dialog', (d) => { issues.push(label() + ' native dialog'); d.dismiss(); });
  };
  let current = 'login';
  watch(page, () => current);
  const shot = async (name) => { await page.screenshot({ path: path.join(out, name + '.png'), fullPage: false }); await layout(name); };
  // Phones open workspaces as sheets with their own close button; Escape only closes desktop popups.
  const closeSheet = async () => { const close = page.locator('.ps-modal button[aria-label^="Close"], [role="dialog"] button[aria-label^="Close"]').first(); if (await close.count() && await close.isVisible().catch(() => false)) await close.click().catch(() => {}); else await page.keyboard.press('Escape'); await sleep(300); };
  const settle = async () => { await page.waitForLoadState('networkidle').catch(() => {}); await sleep(400); };
  // 1. Login (Filament login form).
  await page.goto('/login'); await settle();
  await page.fill('#form\\.login', 'lab@example.com'); await page.fill('#form\\.password', 'LabPassword123!');
  await page.click('button[type="submit"]'); await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 30000 }).catch(() => {});
  await settle(); ok('login as admin', !page.url().includes('/login'), page.url());

  const themes = ['light', 'dark'];
  // Desktop, two portrait phones and the same phones turned to landscape (Jeroen: scrolling locked up when tilted).
  const viewports = { desktop: { width: 1440, height: 900 }, 'phone-390': { width: 390, height: 844 }, 'phone-360': { width: 360, height: 780 }, 'landscape-844': { width: 844, height: 390 }, 'landscape-780': { width: 780, height: 360 } };
  // Layout problems a screenshot alone would not flag: sideways overflow, clipped single-line text, content taller than the screen that nothing scrolls.
  const layoutProblems = async () => page.evaluate(() => {
    const problems = [];
    const root = document.documentElement;
    if (root.scrollWidth > innerWidth + 2) problems.push('page is wider than the screen (' + root.scrollWidth + ' > ' + innerWidth + ')');
    const visible = (el) => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 && r.bottom > 0 && r.top < innerHeight && getComputedStyle(el).visibility !== 'hidden'; };
    const clipped = [];
    // A control inside a strip that scrolls sideways on purpose (chips, quick commands) is reachable; one under an open sheet is not on screen.
    const inSideScroller = (el) => { for (let a = el.parentElement; a && a !== document.body; a = a.parentElement) { const cs = getComputedStyle(a); if (/(auto|scroll)/.test(cs.overflowX) && a.scrollWidth > a.clientWidth + 2) return true; } return false; };
    const covered = (el) => { const r = el.getBoundingClientRect(); const top = document.elementFromPoint(Math.min(Math.max(r.left + 8, 0), innerWidth - 1), Math.min(Math.max(r.top + r.height / 2, 0), innerHeight - 1)); return top && !el.contains(top) && !top.contains(el); };
    for (const el of document.querySelectorAll('body *')) {
      if (clipped.length >= 5 || !visible(el) || !el.childElementCount === false) continue;
      const cs = getComputedStyle(el);
      if (!el.matches('.sr-only, .fi-sr-only') && cs.whiteSpace === 'nowrap' && cs.overflow === 'hidden' && cs.textOverflow !== 'ellipsis' && el.scrollWidth > el.clientWidth + 2 && (el.textContent || '').trim()) clipped.push((el.className || el.tagName).toString().slice(0, 40) + ': "' + el.textContent.trim().slice(0, 30) + '"');
      const r = el.getBoundingClientRect();
      if (r.right > innerWidth + 2 && r.width > 40 && el.matches('button, a, input, select, textarea') && !inSideScroller(el) && !covered(el)) problems.push('control sticks out of the screen: ' + (el.getAttribute('aria-label') || el.textContent.trim().slice(0, 30) || el.tagName));
    }
    if (clipped.length) problems.push('text cut off without an ellipsis: ' + clipped.join(' | '));
    // Popups and menus must sit on the screen, not off to one side or below it.
    for (const el of document.querySelectorAll('[role="dialog"], [role="menu"], .ps-modal, .ps-popover, .ps-sheet, [x-ref][class*="popup"]')) {
      if (!visible(el) || el.closest('.ps-server-workspace.has-dock')) continue; // A docked workspace sits in the page and scrolls with it.
      const r = el.getBoundingClientRect();
      const pageScrolls = root.scrollHeight > innerHeight + 20 && r.top >= 0;
      if (r.left < -2 || r.top < -2 || r.right > innerWidth + 2 || (r.bottom > innerHeight + 2 && !pageScrolls)) problems.push('popup partly off screen: ' + (el.getAttribute('aria-label') || el.id || el.className.toString().slice(0, 40)) + ` (${Math.round(r.left)},${Math.round(r.top)} ${Math.round(r.width)}x${Math.round(r.height)} in ${innerWidth}x${innerHeight})`);
    }
    // Scrolling: either the window scrolls, or a visible pane that holds the content does.
    const tall = root.scrollHeight > innerHeight + 20;
    if (tall) {
      const before = scrollY; scrollTo(0, 300); const moved = scrollY !== before; scrollTo(0, before);
      const panes = [...document.querySelectorAll('body *')].filter((el) => { const cs = getComputedStyle(el); return /(auto|scroll)/.test(cs.overflowY) && el.scrollHeight > el.clientHeight + 20 && visible(el) && el.clientHeight > innerHeight * 0.4; });
      if (!moved && !panes.length) problems.push('content is taller than the screen but nothing scrolls');
    }
    return problems;
  });
  const layout = async (label) => { for (const problem of await layoutProblems()) issues.push(label + ' layout: ' + problem); };
  const setTheme = async (theme) => { await page.evaluate((t) => { localStorage.setItem('pelicanSimplify.theme', t); localStorage.setItem('theme', t); }, theme); };
  const user = 'lab-admin';
  for (const [vp, size] of Object.entries(viewports)) {
    await page.setViewportSize(size);
    const phoneKeys = size.width <= 600;
    for (const theme of themes) {
      const tag = vp + '-' + theme;
      await page.goto(info.pages['Dashboard (app)']); await setTheme(theme);
      // Overview views: grid (cards), compact, list.
      for (const view of ['grid', 'compact', 'list']) {
        current = `overview ${view} ${tag}`;
        await page.evaluate(([k, v]) => localStorage.setItem(k, v), ['pelicanSimplify.overviewView.' + (phoneKeys ? 'phone.' : '') + user, view]);
        await page.goto(info.pages['Dashboard (app)']); await settle();
        const cards = await page.locator('.ps-server-card, .ps-compact-row, .ps-list-row, [class*="ps-server-row"]').count();
        const names = await page.locator('main, body').first().innerText().catch(() => '');
        ok(`overview ${view} shows every server (${tag})`, names.includes(info.server_name) && names.includes('Rust weekend'), `${cards} rows`);
        const dark = await page.evaluate(() => document.documentElement.classList.contains('dark'));
        ok(`theme ${theme} applied on overview (${tag})`, dark === (theme === 'dark'));
        await shot(`overview-${view}-${tag}`);
      }
      // Admin owner filter (All / Mine / Others) and the game filter.
      current = `overview filters ${tag}`;
      for (const [scope, expectMine, expectOther] of [['mine', true, false], ['others', false, true], ['all', true, true]]) {
        await page.evaluate(([k, scope]) => { const cur = JSON.parse(localStorage.getItem(k) || '{}'); cur.serverScope = scope; localStorage.setItem(k, JSON.stringify(cur)); }, ['pelicanSimplify.overviewAppearance.' + user, scope]);
        await page.goto(info.pages['Dashboard (app)']); await settle();
        const text = (await page.locator('.ps-server-card, .ps-compact-row, .ps-list-row, [class*="ps-server-row"]').allInnerTexts()).join('\n');
        ok(`owner filter "${scope}" (${tag})`, text.includes(info.server_name) === expectMine && text.includes('Skyblock') === expectOther, scope);
        if (scope === 'others') await shot(`overview-filter-others-${tag}`);
      }
      const gameFilter = page.locator('[aria-label="Filter servers by game"] button').filter({ hasText: /Minecraft/i }).first();
      if (await gameFilter.count()) { await gameFilter.click(); await sleep(300); const text = (await page.locator('.ps-server-card, .ps-compact-row, .ps-list-row, [class*="ps-server-row"]').allInnerTexts()).join('\n'); ok(`game filter Minecraft hides Rust (${tag})`, !text.includes('Rust weekend') && text.includes(info.server_name)); await shot(`overview-filter-minecraft-${tag}`); }
      // A server group with a colour (saved on the account through the overview's Livewire method): the server's card
      // takes the colour; afterwards the group is removed again.
      current = `group colour ${tag}`;
      await page.goto(info.pages['Dashboard (app)']); await settle();
      await page.evaluate(async (uuid) => { await window.Livewire.all().find((c) => /Dashboard$/.test(c.name)).$wire.call('saveOverviewGroups', [{ id: 'lab-colour', name: 'Lab colour group', servers: [uuid], sort: 'overview', color: '#e11d48' }], 'name'); }, info.server);
      await page.goto(info.pages['Dashboard (app)']); await settle();
      const colored = page.locator('.ps-server-card, .ps-compact-row, .ps-list-row, [class*="ps-server-row"]').filter({ hasText: info.server_name }).first();
      const accent = await colored.evaluate((el) => getComputedStyle(el).getPropertyValue('--ps-accent').trim()).catch(() => '');
      ok(`group colour reaches the server card (${tag})`, /#e11d48|225,\s*29,\s*72/i.test(accent), accent || 'no card');
      ok(`group heading shown (${tag})`, (await page.locator('body').innerText()).includes('Lab colour group'));
      await shot(`overview-group-colour-${tag}`);
      await page.evaluate(async () => { await window.Livewire.all().find((c) => /Dashboard$/.test(c.name)).$wire.call('saveOverviewGroups', [], 'name'); });
      // Host mode (admin sections docked next to the server list) on and off again.
      current = `host mode ${tag}`;
      await page.evaluate(([k]) => localStorage.setItem(k, '1'), ['pelicanSimplify.hostMode.' + user]);
      await page.goto(info.pages['Dashboard (app)']); await settle();
      ok(`host mode shows admin sections (${tag})`, /Health/.test(await page.locator('body').innerText()));
      await shot(`hostmode-${tag}`);
      await page.evaluate(([k]) => localStorage.setItem(k, '0'), ['pelicanSimplify.hostMode.' + user]);
      await page.goto(info.pages['Dashboard (app)']); await settle();
      // Server groups popup and Customize popup.
      current = `overview popups ${tag}`;
      const groups = page.locator('[aria-label="Server groups"]').first();
      if (await groups.count() && await groups.isVisible()) { await groups.click(); await sleep(400); await shot(`overview-groups-${tag}`); const add = page.locator('.ps-group-add'); ok(`server groups popup opens (${tag})`, await add.count() > 0); await page.keyboard.press('Escape'); await sleep(200); }
      else ok(`server groups button present (${tag})`, phoneKeys, 'not visible');
      const customize = page.locator('[aria-label="Customize overview"]').first();
      if (await customize.count() && await customize.isVisible()) { await customize.click(); await sleep(400); await shot(`overview-customize-${tag}`); await page.keyboard.press('Escape'); await sleep(200); }
      // Phone: menu tab.
      if (phoneKeys) { const menu = page.locator('button.ps-tab[aria-controls="ps-navigation-items"]'); if (await menu.count()) { await menu.click(); await sleep(400); await shot(`phone-menu-${tag}`); await page.keyboard.press('Escape'); } }
      // Server page and its workspaces.
      current = `server page ${tag}`;
      await page.goto(info.pages['Dashboard (app)'].split('?')[0] + '?server=' + info.server); await settle();
      ok(`server page opens (${tag})`, (await page.locator('body').innerText()).includes(info.server_name));
      await shot(`server-${tag}`);
      // Every workspace: on desktop the section buttons beside the console, on phones the tabs plus the More menu.
      const motd = async (label) => {
        const tab = page.locator('button, [role="tab"]').filter({ hasText: /^\s*MOTD\s*$/ }).first();
        if (!(await tab.count()) || !(await tab.isVisible())) return;
        await tab.click(); await sleep(800);
        const editor = page.locator('.ps-motd-editor, .ps-motd').first();
        ok(`MOTD editor opens (${tag})`, await editor.isVisible().catch(() => false));
        await shot(`server-motd-${label}`);
      };
      if (!phoneKeys) {
        const labels = await page.locator('.ps-widget[aria-label]').evaluateAll((els) => els.filter((e) => e.checkVisibility()).map((e) => e.getAttribute('aria-label')).filter((l) => l && !/console/i.test(l)));
        ok(`server page lists its workspaces (${tag})`, labels.length >= 8, labels.length + ' workspaces');
        for (const label of labels) {
          const key = label.split(/\s[—-]\s/)[0].toLowerCase().replace(/[^a-z]+/g, '-');
          current = `server ${key} ${tag}`;
          await page.locator(`.ps-widget[aria-label="${label}"]`).first().click(); await sleep(1200);
          ok(`workspace ${key} opens (${tag})`, await page.locator('.ps-modal-layer[data-open="true"], .ps-server-workspace.has-dock').first().isVisible().catch(() => false));
          await shot(`server-${key}-${tag}`);
          if (/^game settings/i.test(label)) await motd(tag);
          await closeSheet();
        }
      } else {
        const tabs = page.locator('nav.ps-phone-sections > button.ps-phone-section:not([aria-controls])');
        const count = await tabs.count();
        for (let i = 1; i < count; i++) {
          const name = ((await tabs.nth(i).innerText().catch(() => '')) || 'tab').trim().toLowerCase().replace(/[^a-z]+/g, '-');
          current = `server ${name} ${tag}`;
          await tabs.nth(i).click(); await sleep(1200); await shot(`server-${name}-${tag}`); await closeSheet();
        }
        const more = page.locator('button.ps-phone-section[aria-controls="ps-phone-more-menu"]');
        if (await more.count()) {
          const entries = await (async () => { await more.click(); await sleep(400); const n = await page.locator('#ps-phone-more-menu button').evaluateAll((els) => els.filter((e) => e.checkVisibility()).map((e) => e.textContent.trim())); await page.keyboard.press('Escape'); return n; })();
          ok(`phone More menu lists the other workspaces (${tag})`, entries.length >= 6, entries.length + ' entries');
          for (const entry of entries) {
            const key = entry.toLowerCase().replace(/[^a-z]+/g, '-');
            current = `server ${key} ${tag}`;
            await more.click(); await sleep(300);
            await page.locator('#ps-phone-more-menu button').filter({ hasText: entry }).first().click(); await sleep(1200);
            ok(`workspace ${key} opens on phone (${tag})`, await page.locator('.ps-modal-layer[data-open="true"]').first().isVisible().catch(() => false));
            await shot(`server-${key}-${tag}`);
            if (/^game settings/i.test(entry)) await motd(tag);
            await closeSheet();
          }
        }
      }
      // Tilting the phone while a page is open: the layout must follow and keep scrolling, both ways.
      if (vp.startsWith('phone-') || vp.startsWith('landscape-')) {
        const turned = { width: size.height, height: size.width };
        for (const [where, url] of [['overview', info.pages['Dashboard (app)']], ['server page', info.pages['Dashboard (app)'].split('?')[0] + '?server=' + info.server]]) {
          current = `tilt ${where} ${tag}`;
          await page.goto(url); await settle();
          await page.setViewportSize(turned); await sleep(600); await layout(`tilt ${where} to ${turned.width}x${turned.height} ${tag}`); await shot(`tilt-${where.replace(' ', '-')}-${turned.width}x${turned.height}-${tag}`);
          await page.setViewportSize(size); await sleep(600); await layout(`tilt ${where} back to ${size.width}x${size.height} ${tag}`);
        }
      }
      // Every other Simplify page (creator, settings, plugin manager, people, plugin widgets/actions) on every panel.
      for (const [name, url] of Object.entries(info.pages)) {
        if (name.startsWith('Dashboard')) continue;
        current = `${name} ${tag}`;
        const response = await page.goto(url).catch(() => null); await settle();
        ok(`${name} renders (${tag})`, !!response && response.status() === 200 && !page.url().includes('/login'), response ? 'HTTP ' + response.status() + ' ' + page.url().replace(base, '') : 'no response');
        await shot(`page-${name.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}-${tag}`);
      }
    }
  }
  await browser.close();
  fs.writeFileSync(path.join(out, 'result.json'), JSON.stringify({ version: info.version, base, checks, issues }, null, 2));
  const failed = checks.filter((c) => !c.pass).length;
  console.log(`${info.version}: ${checks.length - failed}/${checks.length} checks passed, ${issues.length} issues`);
  for (const i of issues.slice(0, 40)) console.log('  - ' + i);
  process.exitCode = failed || issues.length ? 1 : 0;
})().catch((e) => { console.error(e); process.exit(1); });
