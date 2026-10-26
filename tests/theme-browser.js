// Browser checks for the shared Simplify look: one component style everywhere, popups that never
// jump, single scroll containers in embedded workspaces and no native browser dialogs or hover text.
const assert = require('assert');
const { chromium } = require('playwright');
const { start } = require('./browser-harness');

(async () => {
  const server = await start();
  const base = 'http://127.0.0.1:' + server.address().port;
  const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROMIUM || undefined });
  const errors = [];
  const open = async (url, viewport = { width: 1440, height: 900 }) => {
    const page = await browser.newPage({ viewport });
    page.on('pageerror', (e) => errors.push(url + ': ' + e.message));
    // Native dialogs must never appear.
    page.on('dialog', (dialog) => {
      errors.push(url + ': native ' + dialog.type() + ' dialog');
      dialog.dismiss();
    });
    await page.goto(base + url);
    await page.waitForFunction(() => window.Alpine && document.querySelector('.ps') && !document.querySelector('.ps').hasAttribute('x-cloak'));
    return page;
  };
  const data = (page, fn, arg) => page.evaluate(([source, value]) => new Function('d', 'arg', 'return (' + source + ')(d, arg)')(Alpine.$data(document.querySelector('.ps')), value), [fn.toString(), arg]);
  try {
    // 1. Theme tokens drive the page in both themes.
    const light = await open('/?mode=overview&theme=light');
    assert.equal(await light.evaluate(() => getComputedStyle(document.body).backgroundColor), 'rgb(244, 244, 245)');
    assert.equal(await light.evaluate(() => document.documentElement.classList.contains('dark')), false);
    const dark = await open('/?mode=server&theme=dark');
    assert.equal(await dark.evaluate(() => getComputedStyle(document.body).backgroundColor), 'rgb(16, 16, 18)');
    assert.equal(await dark.evaluate(() => getComputedStyle(document.querySelector('.ps-console')).backgroundColor), 'rgb(9, 9, 11)', 'console stays black by default');
    console.log('PASS light and dark tokens, always-dark console');

    // 2. No browser hover text: titles only remain on iframes (accessible names).
    const titled = await dark.evaluate(() => [...document.querySelectorAll('[title]')].filter((el) => el.tagName !== 'IFRAME').map((el) => el.outerHTML.slice(0, 80)));
    assert.deepEqual(titled, []);
    await dark.hover('.ps-alias-button');
    await dark.waitForSelector('.ps-tooltip.is-visible');
    assert.equal(await dark.textContent('.ps-tooltip'), 'Change alias');
    console.log('PASS custom tooltips instead of browser hover text');

    // 3. One button style: dashboard buttons and embedded Filament buttons share height, radius and border.
    await data(dark, (d) => d.open('schedules'));
    const frame = await dark.waitForSelector('iframe[title="Scheduled tasks"]');
    const native = await frame.contentFrame();
    await native.waitForFunction(() => document.documentElement.classList.contains('ps-frame') && getComputedStyle(document.querySelector('.fi-btn')).borderTopLeftRadius === '10px');
    const style = (el) => ({ radius: getComputedStyle(el).borderTopLeftRadius, height: el.offsetHeight, border: getComputedStyle(el).borderTopColor });
    const outer = await dark.evaluate((fn) => new Function('return ' + fn)()(document.querySelector('.ps-back-overview')), style.toString());
    const inner = await native.evaluate((fn) => new Function('return ' + fn)()(document.querySelector('.fi-btn:not(.fi-color-danger)')), style.toString());
    assert.deepEqual(inner, outer, 'embedded buttons match dashboard buttons');
    assert.equal(await native.evaluate(() => document.documentElement.classList.contains('dark')), true, 'embedded page follows the dark theme');
    console.log('PASS shared button style inside embedded workspaces');

    // 4. Popups keep their size while content changes, and only the body scrolls.
    await data(dark, (d) => d.close());
    await data(dark, (d) => d.open('settings'));
    await dark.waitForSelector('.ps-tabs');
    await dark.waitForTimeout(300); // opening fade
    const size = () => dark.evaluate(() => {
      const box = document.querySelector('.ps-modal').getBoundingClientRect();
      return [Math.round(box.width), Math.round(box.height), Math.round(box.top + scrollY)]; // page position: a click may scroll the page
    });
    const before = await size();
    await dark.click('.ps-tabs button:nth-child(2)');
    await dark.click('.ps-tabs button:nth-child(3)');
    const afterTabs = await size();
    assert.deepEqual(afterTabs, before, 'switching tabs does not resize or move the popup: ' + JSON.stringify({ before, afterTabs, scale: await data(dark, (d) => d.uiScale) }));
    await dark.evaluate(() => (document.querySelector('.ps-tab-panels').style.height = '3000px'));
    assert.deepEqual(await size(), before, 'long content scrolls inside the popup');
    assert.equal(await dark.evaluate(() => getComputedStyle(document.querySelector('.ps-modal-body')).overflowY), 'auto');
    console.log('PASS fixed popup size with a scrolling body');

    // 5. Files: the page fills the popup; only the file list scrolls (toolbar and pagination stay put).
    await data(dark, (d) => d.close());
    await data(dark, (d) => d.open('files'));
    const filesFrame = await (await dark.waitForSelector('iframe[title="Server file manager"]')).contentFrame();
    await filesFrame.waitForFunction(() => document.documentElement.classList.contains('ps-frame'));
    const scroll = await filesFrame.evaluate(() => {
      const list = document.querySelector('.fi-ta-content-ctn');
      return {
        html: getComputedStyle(document.documentElement).overflowY,
        body: getComputedStyle(document.body).overflowY,
        bodyScrolls: document.body.scrollHeight > document.body.clientHeight + 1,
        listScrolls: list.scrollHeight > list.clientHeight && getComputedStyle(list).overflowY === 'auto',
        heading: getComputedStyle(document.querySelector('.fi-header-heading')).display,
      };
    });
    assert.deepEqual(scroll, { html: 'hidden', body: 'hidden', bodyScrolls: false, listScrolls: true, heading: 'none' });
    const outerScroll = await dark.evaluate(() => {
      const body = document.querySelector('.ps-modal-body');
      return body.scrollHeight > body.clientHeight + 1;
    });
    assert.equal(outerScroll, false, 'the popup itself never scrolls around the file manager');
    // The editor opens beside the list with Simplify's own header; only its code area grows.
    // Playwright mis-computes click points inside a scaled (zoomed) page's iframe; a real click lands correctly.
    await filesFrame.$eval('a[href*="/edit/file3.yml"]', (a) => a.click());
    const editorFrame = await (await dark.waitForSelector('.ps-file-editor iframe')).contentFrame();
    await editorFrame.waitForFunction(() => document.documentElement.dataset.psEditorActions === 'external');
    const editor = await editorFrame.evaluate(() => ({
      footer: getComputedStyle(document.querySelector('.fi-section-footer')).display,
      fills: (() => { const box = document.querySelector('.fme-wrapper').getBoundingClientRect(); return box.height > 200 && box.bottom >= innerHeight - 48; })(), // the code area reaches the bottom of the editor
      bodyScrolls: document.body.scrollHeight > document.body.clientHeight + 1,
    }));
    assert.deepEqual(editor, { footer: 'none', fills: true, bodyScrolls: false });
    assert.equal(await dark.textContent('.ps-file-editor-path'), 'file3.yml');
    console.log('PASS file workspace and editor scroll only their lists');

    // 6. Confirmations use the Simplify dialog; Cancel keeps the server running.
    await data(dark, (d) => d.close());
    await dark.waitForSelector('.ps-modal-layer[data-open="false"]', { state: 'hidden' });
    await data(dark, (d) => { d.status = 'running'; d.power('kill'); });
    await dark.waitForSelector('[role="alertdialog"]');
    assert.equal(await dark.textContent('#ps-dialog-title'), 'Force stop this server?');
    await dark.keyboard.press('Escape');
    await dark.waitForSelector('[role="alertdialog"]', { state: 'detached' });
    await data(dark, (d) => d.toast('Saved', 'ok'));
    await dark.waitForSelector('.ps-toast.is-ok');
    console.log('PASS Simplify confirmation dialog and toast');

    // 7. Phones: no horizontal scrolling on overview or server pages.
    for (const url of ['/?mode=overview&theme=light', '/?mode=server&theme=dark']) {
      const phone = await open(url, { width: 390, height: 844 });
      assert.equal(await phone.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, url + ' fits a phone');
      await phone.close();
    }
    console.log('PASS phone layouts without horizontal scrolling');

    // 7b. Full screen width: the overview and server pages each have their own switch (Customize overview / Customize server page).
    for (const url of ['/?mode=overview&theme=dark', '/?mode=server&theme=dark']) {
      const wide = await open(url, { width: 2400, height: 1100 });
      const server = url.includes('server');
      const width = () => wide.evaluate((selector) => document.querySelector(selector).getBoundingClientRect().width / innerWidth, server ? '.ps-server-dashboard' : '.ps-server-stage');
      const before = await width();
      await data(wide, (d) => d.setOverviewPreference('fullWidth', true));
      await wide.waitForTimeout(200);
      const overviewOnly = await width();
      await data(wide, (d) => d.setOverviewPreference('serverFullWidth', true));
      await wide.waitForTimeout(200);
      const both = await width();
      if (server) {
        assert.ok(Math.abs(overviewOnly - before) < 0.01, 'the overview switch leaves server pages alone: ' + before + ' -> ' + overviewOnly);
        assert.ok(before < 0.7 && both > 0.9, 'server page uses the full width when switched on: ' + before + ' -> ' + both);
        assert.equal(await wide.evaluate(() => document.querySelector('[x-ref=overviewAppearance]').getAttribute('aria-label')), 'Customize server page');
        assert.equal(await wide.evaluate(() => document.querySelector('[x-ref=overviewAppearance]').offsetParent !== null), true, 'Customize button shown on server pages');
      } else {
        assert.ok(before < 0.7 && overviewOnly > 0.9, 'overview uses the full width when switched on: ' + before + ' -> ' + overviewOnly);
        await data(wide, (d) => d.setOverviewPreference('fullWidth', false));
        await wide.waitForTimeout(200);
        assert.ok(await width() < 0.7, 'the server page switch leaves the overview alone');
      }
      await wide.close();
    }
    // 7b2. Bigger console and the log in the mini console are remembered for the next server page.
    const remember = await open('/?mode=server&theme=dark');
    await remember.click('[x-ref=overviewAppearance]');
    await remember.locator('.ps-customize-switch', { hasText: 'Bigger console' }).click();
    await remember.locator('.ps-customize-switch', { hasText: 'Log in the mini console' }).click();
    assert.equal(await remember.evaluate(() => document.querySelector('.ps-server-workspace').classList.contains('is-focus')), true, 'bigger console right away');
    await remember.reload();
    await remember.waitForFunction(() => window.Alpine && document.querySelector('.ps') && !document.querySelector('.ps').hasAttribute('x-cloak'));
    assert.deepEqual(await data(remember, (d) => [d.consoleFocus, d.consolePeek]), [true, true], 'remembered after a reload');
    await remember.locator('.ps-console-grow').click();
    assert.equal(await data(remember, (d) => d.overviewPreferences.serverBiggerConsole), false, 'the console button changes the same preference');
    await data(remember, (d) => { d.setConsolePeek(false); });
    await remember.close();
    const narrow = await open('/?mode=overview&theme=light', { width: 390, height: 844 });
    await data(narrow, (d) => d.setOverviewPreference('fullWidth', true));
    assert.equal(await narrow.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'full width still fits a phone');
    await narrow.close();
    console.log('PASS full screen width switches for the overview and server pages, each on its own, remembered bigger console and mini console log, phones fit');

    // 7c. Layout, columns and card width: a column count caps each row; compact cards are smaller; phones show two compact cards per row.
    const columns = await open('/?mode=overview&theme=dark', { width: 1600, height: 1000 });
    const perRow = () => columns.evaluate(() => { const tops = [...document.querySelectorAll('.ps-server-card')].map((c) => Math.round(c.getBoundingClientRect().top)); return tops.filter((t) => t === tops[0]).length; });
    const cardHeight = () => columns.evaluate(() => document.querySelector('.ps-server-card').getBoundingClientRect().height);
    await data(columns, (d) => d.setOverviewPreference('columns', '2'));
    await columns.waitForTimeout(200);
    assert.equal(await perRow(), 2, 'two columns');
    const big = await cardHeight();
    await data(columns, (d) => d.setOverviewView('compact'));
    await columns.waitForTimeout(200);
    assert.ok(await cardHeight() < big * 0.7, 'compact cards are smaller');
    await columns.close();
    const compactPhone = await open('/?mode=overview&theme=light', { width: 390, height: 844 });
    await data(compactPhone, (d) => d.setOverviewView('compact'));
    await compactPhone.waitForTimeout(200);
    assert.equal(await compactPhone.evaluate(() => { const [a, b] = document.querySelectorAll('.ps-server-card'); return Math.round(a.getBoundingClientRect().top) === Math.round(b.getBoundingClientRect().top); }), true, 'two compact cards per row on a phone');
    assert.equal(await compactPhone.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'compact fits a phone');
    await compactPhone.close();
    console.log('PASS layout cards/compact/list, column count and compact cards on phones');

    // 8. Overview by node: one heading per node, compact list rows whatever the card options, host mode.
    const prefs = (o) => `localStorage.setItem('pelicanSimplify.overviewAppearance.jera', ${JSON.stringify(JSON.stringify(o))})`;
    const nodes = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await nodes.addInitScript(prefs({ quickActions: 'off', showCpu: false, showRam: false }));
    await nodes.goto(base + '/?mode=overview&theme=dark');
    await nodes.waitForFunction(() => window.Alpine && document.querySelector('.ps-node-heading'));
    assert.equal(await nodes.locator('.ps-node-heading').count(), 2, 'one heading per node');
    await nodes.evaluate(() => Alpine.$data(document.querySelector('.ps')).setOverviewView('list'));
    await nodes.waitForTimeout(300);
    const rows = await nodes.evaluate(() => [...document.querySelectorAll('.ps-server-entry:not(.is-node-heading)')].map((e) => Math.round(e.getBoundingClientRect().height)));
    assert.ok(rows.length && rows.every((h) => h <= 80), 'list rows stay compact without quick actions or metrics: ' + rows);
    await nodes.evaluate(() => { const d = Alpine.$data(document.querySelector('.ps')); d.nodeFilter = 2; });
    assert.equal(await nodes.locator('.ps-server-entry:not(.is-node-heading)').count(), 2, 'node filter limits the list');
    await nodes.evaluate(() => Alpine.$data(document.querySelector('.ps')).setHostMode(true));
    await nodes.waitForSelector('.ps-host iframe[title="Health"]');
    assert.equal(await nodes.locator('.ps-host-server').count(), 4, 'host mode lists every server');
    await nodes.close();
    console.log('PASS servers grouped per node, compact list rows and host mode');

    // 8b. Things only shrink when they would not fit: the list MOTD keeps its width when quick actions
    // slide in, and quick actions set to On stay visible and make grid cards taller instead of squeezing them.
    const fit = await browser.newPage({ viewport: { width: 1600, height: 900 } });
    await fit.addInitScript(prefs({ showMotd: true, fullWidth: true, quickActions: 'hover' }));
    await fit.goto(base + '/?mode=overview&theme=dark');
    await fit.waitForFunction(() => window.Alpine && document.querySelector('.ps-card-motd'));
    await fit.evaluate(() => Alpine.$data(document.querySelector('.ps')).setOverviewView('list'));
    await fit.waitForTimeout(300);
    const motdWidth = () => fit.evaluate(() => document.querySelector('.ps-server-entry.has-card-motd .ps-card-motd').offsetWidth);
    const restingMotd = await motdWidth();
    await fit.hover('.ps-server-entry.has-card-motd .ps-card-title');
    await fit.waitForTimeout(400);
    assert.equal(await motdWidth(), restingMotd, 'MOTD keeps its width while the row has room');
    await fit.evaluate(() => Alpine.$data(document.querySelector('.ps')).setOverviewPreference('quickActions', 'on'));
    await fit.mouse.move(5, 5);
    await fit.waitForTimeout(400);
    assert.equal(await fit.locator('.ps-server-entry.has-power .ps-overview-power-wrap').first().evaluate((e) => getComputedStyle(e).visibility), 'visible', 'On shows the buttons without pointing');
    const card = () => fit.evaluate(() => { const e = document.querySelector('.ps-server-entry.has-power'); return { height: e.querySelector('.ps-server-open').offsetHeight, graph: e.querySelector('.ps-card-graph').getBoundingClientRect().height }; });
    await fit.evaluate(() => { const d = Alpine.$data(document.querySelector('.ps')); d.setOverviewView('grid'); d.setOverviewPreference('quickActions', 'hover'); });
    await fit.waitForTimeout(900); // graph heights animate
    const hoverCard = await card();
    await fit.evaluate(() => Alpine.$data(document.querySelector('.ps')).setOverviewPreference('quickActions', 'on'));
    await fit.waitForTimeout(900);
    const onCard = await card();
    assert.ok(onCard.height > hoverCard.height && Math.abs(onCard.graph - hoverCard.graph) < 1, 'On grows the card, graphs keep their size: ' + JSON.stringify({ hoverCard, onCard }));
    await fit.evaluate(() => Alpine.$data(document.querySelector('.ps')).setOverviewPreference('quickActions', 'off'));
    assert.equal(await fit.locator('.ps-server-entry.has-power').count(), 0, 'Off hides quick actions');
    assert.equal(await data(fit, (d) => d.uiScale), 1, 'interface keeps its normal size when everything fits');
    const longName = await fit.evaluate(() => { const h = [...document.querySelectorAll('.ps-card-title-row h3')].find((e) => e.textContent.startsWith('Creative')); return { size: parseFloat(h.style.fontSize), lines: Math.round(h.clientHeight / parseFloat(getComputedStyle(h).lineHeight)), clipped: h.scrollHeight > h.clientHeight + 1 }; });
    assert.ok(longName.lines === 2 && !longName.clipped && longName.size >= 14, 'a long grid name wraps onto two lines before it shrinks: ' + JSON.stringify(longName));
    await fit.close();
    console.log('PASS MOTD only gives way when the row is full, quick actions off/on/hover, normal interface size, two-line names');

    // 9. Network: the subdomain sits in one input row with its domain, and the status uses the shared badge.
    const network = await open('/?mode=server&theme=light');
    await network.evaluate(() => { window.__wire.connectionData = async () => ({ subdomains: true, requested_subdomain: 'oak', subdomain: { name: 'oak', status: 'active', message: '', domain: 'example.com' }, address: 'oak.example.com' }); });
    await data(network, (d) => d.open('connection'));
    await network.waitForFunction(() => document.querySelector('.ps-input-suffix')?.textContent === '.example.com');
    const row = await network.evaluate(() => { const r = document.querySelector('.ps-input-suffix').parentElement; const items = [...r.children].map((e) => e.getBoundingClientRect()); return { centers: items.map((b) => Math.round(b.top + b.height / 2)), overflow: r.scrollWidth > r.clientWidth }; });
    assert.ok(Math.max(...row.centers) - Math.min(...row.centers) <= 1 && !row.overflow, 'name, domain and Save share one row: ' + JSON.stringify(row));
    assert.equal(await network.locator('.ps-badge.is-ok').filter({ hasText: 'Connected' }).count(), 1, 'connected subdomain shows the ok badge');
    if (process.env.SCREENSHOT_DIR) await network.screenshot({ path: process.env.SCREENSHOT_DIR + '/network-subdomain.png' });
    await network.close();
    console.log('PASS network subdomain row and status badge');

    // 10. Website listing: a switch in the server's more menu when the website plugin is installed, hidden otherwise.
    const listing = await open('/?mode=server&theme=dark');
    await listing.click('.ps-info-more-button');
    await listing.waitForSelector('#ps-info-menu', { state: 'visible' });
    // The card lets the menu overflow while it is open, but the blurred background stays inside the card.
    const decor = await listing.evaluate(() => { const card = document.querySelector('.ps-server-info').getBoundingClientRect(), layer = document.querySelector('.ps-info-decor'), box = layer.getBoundingClientRect(); return { clips: getComputedStyle(layer).overflow === 'hidden', banners: [...document.querySelectorAll('.ps-server-info .ps-server-banner')].every((b) => layer.contains(b)), inside: box.left >= card.left && box.right <= card.right && box.top >= card.top && box.bottom <= card.bottom }; });
    assert.deepEqual(decor, { clips: true, banners: true, inside: true }, 'the background stays inside the card while the menu is open');
    assert.equal(await listing.locator('.ps-web-listing').isVisible(), false, 'no switch without the website plugin');
    await listing.evaluate(() => { window.__wire.saveListing = async (v) => v; Alpine.$data(document.querySelector('.ps')).server.website_listing = { visible: false }; });
    await listing.waitForSelector('.ps-web-listing', { state: 'visible' });
    await listing.click('.ps-web-listing');
    await listing.waitForFunction(() => document.querySelector('.ps-web-listing').getAttribute('aria-checked') === 'true');
    const place = await listing.evaluate(() => { const b = document.querySelector('.ps-web-listing').getBoundingClientRect(), menu = document.querySelector('#ps-info-menu').getBoundingClientRect(), kill = document.querySelector('#ps-info-menu .is-danger').getBoundingClientRect(); return b.top >= menu.top && b.bottom <= kill.top && !!document.querySelector('.ps-web-listing .ps-switch.is-on'); });
    assert.ok(place, 'the switch sits in the more menu above Force stop');
    if (process.env.SCREENSHOT_DIR) await listing.locator('.ps-server-info').screenshot({ path: process.env.SCREENSHOT_DIR + '/website-listing.png' });
    await listing.close();
    console.log('PASS website listing switch in the server menu, background stays inside the card');

    // 10b. Modpack: a link in the header line, no separate card under the console.
    for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
      const pack = await open('/?mode=server&theme=dark', viewport);
      await data(pack, (d) => { d.server.pack = { ...d.server.pack, modpack_name: 'FTB Evolution 2', modpack_version: '1.5.1', modpack_url: 'https://example.com/pack' }; });
      await pack.waitForSelector('a.ps-info-modpack', { state: 'visible' });
      assert.equal(await pack.getAttribute('a.ps-info-modpack', 'href'), 'https://example.com/pack');
      assert.equal((await pack.textContent('a.ps-info-modpack')).trim(), 'FTB Evolution 2 1.5.1');
      assert.equal(await pack.locator('.ps-pack-summary').count(), 0, 'no modpack card under the console');
      await pack.close();
    }
    console.log('PASS modpack link in the server header');

    // 11. MOTD editor: the lines are edited in place, and the toolbar writes clean codes into the settings.
    const motd = await open('/?mode=server&theme=dark');
    await data(motd, (d) => d.open('settings'));
    await motd.waitForFunction(() => Alpine.$data(document.querySelector('.ps')).settings?.motd !== undefined);
    await data(motd, (d) => { d.settingsTab = 'motd'; });
    const line = motd.locator('[aria-label="MOTD line 1"]');
    await line.waitFor({ state: 'visible' });
    assert.equal(await line.inputValue(), 'Welcome to Survival');
    await line.evaluate((el) => { el.focus(); el.setSelectionRange(0, 7); });
    await motd.click('.ps-motd [aria-label="Colour: Red"]');
    await motd.click('.ps-motd [aria-label="Bold"]');
    assert.equal(await data(motd, (d) => d.settings.motd), '§c§lWelcome §ato §lSurvival');
    assert.equal(await motd.locator('.ps-motd [aria-label="Bold"]').getAttribute('aria-pressed'), 'true');
    await line.press('End');
    await line.type('!');
    assert.equal(await data(motd, (d) => d.settings.motd), '§c§lWelcome §ato §lSurvival!');
    // The coloured mirror lines up with the transparent input, bold included.
    const aligned = await motd.evaluate(async () => {
      await document.fonts.ready;
      const input = document.querySelector('[aria-label="MOTD line 1"]'), mirror = input.previousElementSibling;
      const spans = [...mirror.children].filter((e) => e.tagName === 'SPAN').reduce((sum, e) => sum + e.getBoundingClientRect().width, 0);
      const probe = Object.assign(document.createElement('span'), { textContent: input.value });
      probe.style.cssText = 'position:absolute;visibility:hidden;white-space:pre;font:' + getComputedStyle(input).font;
      mirror.parentElement.append(probe);
      const width = probe.getBoundingClientRect().width;
      probe.remove();
      return Math.abs(spans - width) <= 1;
    });
    assert.ok(aligned, 'mirror and input have the same text width');
    if (process.env.SCREENSHOT_DIR) await motd.locator('.ps-motd-editor').screenshot({ path: process.env.SCREENSHOT_DIR + '/motd-editor.png' });
    await motd.close();
    console.log('PASS MOTD editor with in-place colours, clean codes and an aligned preview');

    // 12. Performance: the creator's resource presets, with a custom value, are saved as limits.
    const performance = await open('/?mode=server&theme=light');
    await performance.evaluate(() => { window.__saved = null; window.__wire.saveResourceLimits = async (limits) => { window.__saved = limits; return { pending: true, changed: true }; }; });
    await data(performance, (d) => d.open('performance'));
    await performance.click('[role=radiogroup][aria-label=RAM] >> text=8 GB');
    assert.equal(await performance.locator('[role=radiogroup][aria-label="CPU allocation"] [aria-checked=true]').innerText().then((t) => t.split('\n')[0]), '200%');
    await performance.click('[role=radiogroup][aria-label="Disk allocation"] >> text=Custom');
    await performance.fill('label:has-text("Custom disk") input', '30000');
    await performance.click('text=Save limits');
    await performance.waitForFunction(() => window.__saved);
    assert.deepEqual(await performance.evaluate(() => window.__saved), { memory_gb: 8, cpu: 200, disk_mb: 30000 });
    if (process.env.SCREENSHOT_DIR) await performance.screenshot({ path: process.env.SCREENSHOT_DIR + '/performance.png' });
    await performance.close();
    console.log('PASS performance presets like the creator, with custom values');

    // 13. Phone overview: the page scrolls as a whole, one filter row, compact rows with one large power
    // button, a one-line health summary and a tab bar (360, 390 and 430 px wide).
    for (const width of [360, 390, 430]) {
      const phone = await open('/?mode=overview&theme=dark', { width, height: 800 });
      const layout = await phone.evaluate(() => {
        const nested = [...document.querySelectorAll('.ps *')].filter((el) => {
          const s = getComputedStyle(el);
          return /(auto|scroll)/.test(s.overflowY) && el.scrollHeight > el.clientHeight + 4 && el.offsetParent;
        }).map((el) => el.className.toString());
        const toolbar = document.querySelector('.ps-overview-toolbar');
        const tabbar = document.querySelector('.ps-tabbar').getBoundingClientRect();
        const power = [...document.querySelectorAll('.ps-overview-power > .ps-btn')].filter((b) => b.offsetParent);
        const small = [...document.querySelectorAll('.ps-tabbar button, .ps-overview-toolbar .ps-chip')].filter((b) => b.offsetParent && b.offsetHeight < 40).length;
        return {
          nested, small, pageScrolls: document.scrollingElement.scrollHeight > innerHeight, fits: document.documentElement.scrollWidth <= innerWidth,
          filterRows: new Set([...toolbar.querySelectorAll('.ps-chip')].filter((c) => c.offsetParent).map((c) => Math.round(c.getBoundingClientRect().top))).size,
          tabbarAtBottom: Math.round(tabbar.bottom) === innerHeight && tabbar.height >= 60,
          powerPerCard: [...document.querySelectorAll('.ps-server-entry.has-power')].map((e) => [...e.querySelectorAll('.ps-overview-power > .ps-btn')].filter((b) => b.offsetParent).length),
          powerSize: power.map((b) => b.offsetHeight),
          healthHeight: document.querySelector('.ps-host-health').offsetHeight,
        };
      });
      assert.deepEqual(layout.nested, [], width + ': no scroll areas inside the page');
      assert.ok(layout.pageScrolls && layout.fits, width + ': the page itself scrolls and fits');
      assert.equal(layout.filterRows, 1, width + ': game and node filters share one row');
      assert.ok(layout.tabbarAtBottom, width + ': tab bar at the bottom');
      assert.ok(layout.powerPerCard.length && layout.powerPerCard.every((n) => n === 1), width + ': one power button per card: ' + layout.powerPerCard);
      assert.ok(layout.powerSize.every((h) => h >= 44), width + ': power buttons are touch-sized');
      assert.equal(layout.small, 0, width + ': tabs and filters are touch-sized');
      assert.ok(layout.healthHeight <= 60, width + ': host health is one line');
      if (width === 390) {
        // Stop asks for confirmation inside the row; Menu opens the panel menu above the tab bar; New opens a choice of creators.
        await phone.click('.ps-server-entry.has-power [data-power="stop"]');
        await phone.waitForSelector('.ps-power-confirm:visible');
        await data(phone, (d) => { d.powerConfirmation = null; });
        await phone.click('.ps-tabbar .ps-tab[aria-controls="ps-navigation-items"]');
        const menu = await phone.evaluate(() => { const r = document.querySelector('.ps-nav-items').getBoundingClientRect(); return { visible: r.height > 0, above: r.bottom <= document.querySelector('.ps-tabbar').getBoundingClientRect().top }; });
        assert.deepEqual(menu, { visible: true, above: true }, 'panel menu opens above the tab bar');
        if (process.env.SCREENSHOT_DIR) await phone.screenshot({ path: process.env.SCREENSHOT_DIR + '/phone-overview.png' });
        await phone.click('.ps-tabbar .ps-tab[aria-controls="ps-navigation-items"]');
        assert.equal(await data(phone, (d) => d.navMenu), false, 'Menu closes again');
        await phone.click('.ps-tab-create > .ps-tab');
        await phone.waitForSelector('.ps-tab-create-menu .ps-menu-item:visible');
        assert.equal(await phone.locator('.ps-tab-create-menu .ps-menu-item:visible').count(), 2);
        await phone.click('.ps-tab-create-menu .ps-menu-item >> text=Minecraft server');
        await phone.waitForSelector('.ps-modal');
        assert.equal(await data(phone, (d) => d.createGame), 'minecraft');
      }
      await phone.close();
    }
    const desktop = await open('/?mode=overview&theme=dark');
    assert.equal(await desktop.locator('.ps-tabbar').isVisible(), false, 'no tab bar on desktop');
    await desktop.close();
    console.log('PASS phone overview: page scroll, one filter row, compact rows, tab bar');

    // 14. Phone server page: four tabs that fit, More lists the other sections, the console fills the screen
    // and quick commands fill the command bar.
    for (const width of [360, 390, 430]) {
      const phone = await open('/?mode=server&theme=dark', { width, height: 800 });
      await data(phone, (d) => { d.connected = true; });
      await phone.waitForFunction(() => document.documentElement.scrollHeight <= innerHeight); // the work area is measured again after fonts and the connection settle
      const layout = await phone.evaluate(() => {
        const tabs = [...document.querySelectorAll('.ps-phone-sections .ps-phone-section')].filter((b) => b.offsetParent);
        const bar = document.querySelector('.ps-phone-sections').getBoundingClientRect();
        const consoleBox = document.querySelector('.ps-console').getBoundingClientRect();
        const power = [...document.querySelectorAll('.ps-info-power > .ps-btn')].map((b) => b.offsetHeight);
        const workHeight = parseInt(getComputedStyle(document.querySelector('.ps-server-workspace')).getPropertyValue('--ps-work-height'), 10);
        return {
          tabs: tabs.map((b) => b.textContent.trim()), fit: tabs.every((b) => b.scrollWidth <= b.clientWidth && b.getBoundingClientRect().right <= bar.right + 1),
          small: tabs.filter((b) => b.offsetHeight < 44).length, grid: !!document.querySelector('.ps-widget-grid').offsetParent,
          consoleHeight: Math.round(consoleBox.height), workHeight, consoleAboveBar: consoleBox.bottom <= bar.top + 1, power, fits: document.documentElement.scrollWidth <= innerWidth,
          pageFits: document.documentElement.scrollHeight <= innerHeight,
        };
      });
      assert.deepEqual(layout.tabs, ['Console', 'Players', 'Files', 'More'], width + ': four tabs');
      assert.ok(layout.fit && layout.fits, width + ': tabs fit on one row without clipping');
      assert.equal(layout.small, 0, width + ': tabs are touch-sized');
      assert.equal(layout.grid, false, width + ': the section grid makes way for the tabs');
      assert.ok(layout.consoleHeight >= 260 && layout.consoleHeight === layout.workHeight && layout.consoleAboveBar && layout.pageFits, width + ': console fills the rest of the screen without scrolling (' + JSON.stringify(layout) + ')');
      assert.ok(layout.power.length === 3 && layout.power.every((h) => h >= 44), width + ': three touch-sized power buttons');
      if (width === 390) {
        await phone.click('.ps-quick-command >> text=say …');
        assert.equal(await data(phone, (d) => d.command), 'say ');
        await phone.waitForFunction(() => document.activeElement === document.querySelector('.ps-command input')); // quick command focuses the command bar
        await phone.click('.ps-phone-more > .ps-phone-section');
        await phone.waitForSelector('#ps-phone-more-menu .ps-menu-item:visible');
        const more = await phone.locator('#ps-phone-more-menu .ps-menu-item:visible').allInnerTexts();
        assert.ok(more.includes('Game settings') && !more.includes('Files'), 'More lists the other sections: ' + more);
        await phone.click('#ps-phone-more-menu .ps-menu-item >> text=Game settings');
        await phone.waitForSelector('.ps-tabs');
        assert.equal(await phone.getAttribute('.ps-phone-more > .ps-phone-section', 'aria-current'), 'page', 'More shows the open section');
        if (process.env.SCREENSHOT_DIR) await phone.screenshot({ path: process.env.SCREENSHOT_DIR + '/phone-server.png' });
        // 15. Workspaces open as a sheet over the page (not docked) with the save bar at the bottom; dragging the header down closes it.
        await phone.waitForTimeout(400); // slide-in
        const sheet = await phone.evaluate(() => {
          const d = Alpine.$data(document.querySelector('.ps')), modal = document.querySelector('.ps-modal').getBoundingClientRect(), foot = document.querySelector('.ps-modal-foot').getBoundingClientRect();
          return { docked: d.docked(), top: Math.round(modal.top), bottom: Math.round(modal.bottom) === innerHeight, foot: Math.round(foot.bottom) === innerHeight, locked: getComputedStyle(document.documentElement).overflow === 'hidden' };
        });
        assert.deepEqual(sheet, { docked: false, top: 12, bottom: true, foot: true, locked: true }, 'workspace sheet with the save bar at the bottom: ' + JSON.stringify(sheet));
        const head = await phone.locator('.ps-modal-head').boundingBox();
        const touch = await phone.context().newCDPSession(phone);
        const drag = (type, y) => touch.send('Input.dispatchTouchEvent', { type, touchPoints: type === 'touchEnd' ? [] : [{ x: head.x + 60, y }] });
        await drag('touchStart', head.y + 20);
        for (let i = 1; i <= 8; i++) await drag('touchMove', head.y + 20 + i * 25);
        await drag('touchEnd');
        await phone.waitForFunction(() => !Alpine.$data(document.querySelector('.ps')).modal);
      }
      await phone.close();
    }
    const desk = await open('/?mode=server&theme=dark');
    assert.deepEqual(await desk.evaluate(() => [!!document.querySelector('.ps-phone-sections').offsetParent, !!document.querySelector('.ps-quick-commands').offsetParent, !!document.querySelector('.ps-widget-grid').offsetParent]), [false, false, true], 'desktop keeps its section menu');
    await desk.close();
    console.log('PASS phone server page: four tabs, More menu, full-height console, quick commands, sheets');

    // 16. Phone creator: "Step 1 of 4" with a progress bar, choices as full-width rows, Back / Next at the bottom.
    for (const width of [360, 430]) {
      const wizard = await browser.newPage({ viewport: { width, height: 640 } });
      await wizard.goto(base + '/frame/creator');
      await wizard.waitForSelector('.ssc-progress');
      const layout = await wizard.evaluate(() => {
        const visible = (el) => !!el.offsetParent;
        const cards = [...document.querySelectorAll('.ssc-card-content')].map((c) => c.getBoundingClientRect());
        const list = document.querySelector('.ssc-cards').getBoundingClientRect();
        const foot = document.querySelector('.fi-sc-wizard-footer');
        foot.scrollIntoView({ block: 'nearest' });
        return {
          steps: [...document.querySelectorAll('.ssc-progress > button')].filter(visible).map((b) => b.textContent.trim()),
          count: document.querySelector('.ssc-progress-count').textContent.trim(),
          rows: cards.every((c) => Math.round(c.width) === Math.round(list.width)) && cards.every((c, i) => i === 0 || c.top > cards[i - 1].bottom),
          sticky: getComputedStyle(foot).position, buttons: [...foot.querySelectorAll('.fi-btn')].map((b) => b.offsetHeight >= 48),
        };
      });
      assert.deepEqual(layout, { steps: ['1Server Type'], count: 'Step 1 of 4', rows: true, sticky: 'sticky', buttons: [true, true] }, width + ': phone creator layout ' + JSON.stringify(layout));
      if (process.env.SCREENSHOT_DIR && width === 430) await wizard.screenshot({ path: process.env.SCREENSHOT_DIR + '/phone-creator.png' });
      await wizard.close();
    }
    console.log('PASS phone creator: step count with a progress bar, choice rows, Back / Next at the bottom');

    // 15. Account-level pages from other plugins (such as Legal Pages) open from the account menu.
    for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
      const account = await open('/?mode=overview&theme=light', viewport);
      const pluginButton = account.locator('#ps-account-actions [aria-label="Plugin pages"]');
      assert.equal(await pluginButton.count(), 1);
      await data(account, (d) => { d.boot.plugin_pages = [{ key: 'ext-imprint', label: 'Imprint', url: '/frame/native', plugin: 'Legal Pages' }, { key: 'ext-termsofservice', label: 'Terms of Service', url: '/frame/native', plugin: 'Legal Pages' }]; });
      if (viewport.width < 600) await data(account, (d) => { d.navMenu = true; });
      await account.click('.ps-account-button');
      await pluginButton.waitFor({ state: 'visible' });
      await pluginButton.click();
      await account.waitForSelector('#ps-plugin-pages', { state: 'visible' });
      assert.deepEqual(await account.locator('#ps-plugin-pages .ps-notify-item strong').allTextContents(), ['Imprint', 'Terms of Service']);
      assert.equal(await account.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, viewport.width + ': no horizontal scroll with the plugin pages open');
      if (process.env.SCREENSHOT_DIR) await account.screenshot({ path: process.env.SCREENSHOT_DIR + '/plugin-pages-' + viewport.width + '.png' });
      await account.locator('#ps-plugin-pages .ps-notify-item').first().click();
      await account.waitForFunction(() => Alpine.$data(document.querySelector('.ps')).modal === 'admin-page');
      assert.equal(await data(account, (d) => d.adminPage.label), 'Imprint');
      await account.waitForSelector('#ps-plugin-pages', { state: 'hidden' });
      await account.close();
    }
    console.log('PASS plugin pages in the account menu, opened in a popup');

    // 16. Widgets from other plugins: a tile sized to its content above the server workspace and the server list; none without widgets.
    const widgets = await open('/?mode=server&theme=dark');
    await data(widgets, (d) => { d.server = { ...d.server, plugin_widgets_url: '/frame/widgets' }; });
    await widgets.waitForFunction(() => (Alpine.$data(document.querySelector('.ps')).pluginWidgetHeights['11111111-1111-1111-1111-111111111111'] || Object.values(Alpine.$data(document.querySelector('.ps')).pluginWidgetHeights)[0] || 0) > 20);
    const tile = await widgets.evaluate(() => { const box = document.querySelector('.ps-server-dashboard .ps-plugin-widgets'), frame = box.querySelector('iframe'), inner = frame.contentDocument.querySelector('.simplify-plugin-widgets'); return { frame: frame.offsetHeight, inner: Math.ceil(inner.getBoundingClientRect().height), scroll: frame.contentDocument.documentElement.scrollHeight <= frame.clientHeight + 1, before: !!(box.compareDocumentPosition(document.querySelector('.ps-server-workspace')) & Node.DOCUMENT_POSITION_FOLLOWING) }; });
    assert.ok(tile.frame === tile.inner && tile.scroll && tile.before, 'server widget tile: ' + JSON.stringify(tile));
    if (process.env.SCREENSHOT_DIR) await widgets.screenshot({ path: process.env.SCREENSHOT_DIR + '/plugin-widgets-server.png' });
    await data(widgets, (d) => { d.server = { ...d.server, uuid: 'empty-widgets', plugin_widgets_url: '/frame/widgets?empty=1' }; });
    await widgets.waitForFunction(() => Alpine.$data(document.querySelector('.ps')).pluginWidgetHeights['empty-widgets'] === 0);
    assert.equal(await widgets.evaluate(() => document.querySelector('.ps-server-dashboard .ps-plugin-widgets').getBoundingClientRect().height), 0, 'no widgets: no tile');
    await widgets.close();
    const listWidgets = await open('/?mode=overview&theme=light');
    await data(listWidgets, (d) => { d.boot.plugin_widgets_url = '/frame/widgets'; });
    await listWidgets.waitForFunction(() => (Alpine.$data(document.querySelector('.ps')).pluginWidgetHeights.app || 0) > 20);
    if (process.env.SCREENSHOT_DIR) await listWidgets.screenshot({ path: process.env.SCREENSHOT_DIR + '/plugin-widgets-list.png' });
    await listWidgets.close();
    console.log('PASS plugin widgets as tiles sized to their content, hidden when a server has none');

    // 17. Phone on its side (wide but low): the overview scrolls as one page instead of leaving a sliver
    // for the server list, the console fits the screen, and turning the phone back keeps the page scrolling.
    for (const viewport of [{ width: 740, height: 360 }, { width: 844, height: 390 }, { width: 932, height: 430 }]) {
      const side = await open('/?mode=overview&theme=light', viewport);
      // The last server card can be scrolled fully into view (it used to sit in a 24 px high list).
      const reachable = () => side.evaluate(() => {
        const cards = document.querySelectorAll('.ps-server-entry'), last = cards[cards.length - 1];
        last.scrollIntoView({ block: 'end' });
        const box = last.getBoundingClientRect();
        scrollTo(0, 0);
        return box.top >= 0 && box.bottom <= innerHeight + 1 ? true : JSON.stringify({ top: box.top, bottom: box.bottom, height: innerHeight });
      });
      assert.equal(await reachable(), true, 'landscape overview at ' + viewport.width);
      await side.setViewportSize({ width: viewport.height, height: viewport.width });
      await side.waitForFunction(() => Alpine.$data(document.querySelector('.ps')).phone);
      assert.equal(await reachable(), true, 'portrait after turning');
      await side.setViewportSize(viewport);
      await side.waitForFunction(() => !Alpine.$data(document.querySelector('.ps')).phone);
      assert.equal(await reachable(), true, 'landscape after turning back');
      await side.close();
      const console_ = await open('/?mode=server&theme=dark', viewport);
      const low = await console_.evaluate(() => { const box = document.querySelector('.ps-console').getBoundingClientRect(); return { height: Math.round(box.height), bottom: Math.round(box.bottom), scroll: document.documentElement.scrollHeight <= innerHeight }; });
      assert.ok(low.height >= 120 && low.bottom <= viewport.height && low.scroll, 'console fits a low screen without scrolling: ' + JSON.stringify(low));
      await data(console_, (d) => d.open('settings'));
      await console_.waitForFunction(() => Alpine.$data(document.querySelector('.ps')).docked());
      const dock = await console_.evaluate(() => document.querySelector('.ps-dock').getBoundingClientRect().height);
      assert.ok(dock <= viewport.height, 'docked workspace fits a low screen: ' + dock);
      await console_.close();
    }
    // Sideways strips show which side hides more items.
    const strip = await open('/?mode=overview&theme=light', { width: 390, height: 844 });
    await strip.waitForFunction(() => document.querySelector('.ps-overview-toolbar').dataset.psMore === 'end');
    await strip.evaluate(() => { const bar = document.querySelector('.ps-overview-toolbar'); bar.scrollLeft = bar.scrollWidth; });
    await strip.waitForFunction(() => document.querySelector('.ps-overview-toolbar').dataset.psMore === 'start');
    // A long name gets a second line on phones instead of "…".
    const name = await strip.evaluate(() => { const h = [...document.querySelectorAll('.ps-card-title-row h3')].find((el) => el.textContent.includes('very long name')); return h.scrollHeight <= h.clientHeight + 1 && h.getBoundingClientRect().height > 30; });
    assert.ok(name, 'long name on two lines in the phone list');
    await strip.close();
    console.log('PASS phone on its side: page scroll, console and workspace fit, turning back, swipe hints, two-line names');

    assert.deepEqual(errors, []);
  } finally {
    await browser.close();
    server.close();
  }
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
