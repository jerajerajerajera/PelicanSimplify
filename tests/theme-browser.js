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
      return [Math.round(box.width), Math.round(box.height), Math.round(box.top)];
    });
    const before = await size();
    await dark.click('.ps-tabs button:nth-child(2)');
    await dark.click('.ps-tabs button:nth-child(3)');
    assert.deepEqual(await size(), before, 'switching tabs does not resize or move the popup');
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
      fills: document.querySelector('.fme-wrapper').getBoundingClientRect().height > 300,
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

    // 8. Overview by node: one heading per node, compact list rows whatever the card options, host mode.
    const prefs = (o) => `localStorage.setItem('pelicanSimplify.overviewAppearance.jera', ${JSON.stringify(JSON.stringify(o))})`;
    const nodes = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await nodes.addInitScript(prefs({ showQuickActions: false, showCpu: false, showRam: false }));
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

    // 10. Website listing: a switch next to the server color when the website plugin is installed, hidden otherwise.
    const listing = await open('/?mode=server&theme=dark');
    assert.equal(await listing.locator('.ps-web-listing').isVisible(), false, 'no switch without the website plugin');
    await listing.evaluate(() => { window.__wire.saveListing = async (v) => v; Alpine.$data(document.querySelector('.ps')).server.website_listing = { visible: false }; });
    await listing.waitForSelector('.ps-web-listing', { state: 'visible' });
    await listing.click('.ps-web-listing');
    await listing.waitForFunction(() => document.querySelector('.ps-web-listing').getAttribute('aria-checked') === 'true');
    const place = await listing.evaluate(() => { const b = document.querySelector('.ps-web-listing').getBoundingClientRect(), d = document.querySelector('.ps-color-dot').getBoundingClientRect(); return Math.abs((b.top + b.height / 2) - (d.top + d.height / 2)) <= 1 && b.right <= d.left; });
    assert.ok(place, 'the switch sits next to the server color');
    if (process.env.SCREENSHOT_DIR) await listing.locator('.ps-server-info').screenshot({ path: process.env.SCREENSHOT_DIR + '/website-listing.png' });
    await listing.close();
    console.log('PASS website listing switch next to the server color');

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
      const layout = await phone.evaluate(() => {
        const tabs = [...document.querySelectorAll('.ps-phone-sections .ps-phone-section')].filter((b) => b.offsetParent);
        const bar = document.querySelector('.ps-phone-sections').getBoundingClientRect();
        const consoleBox = document.querySelector('.ps-console').getBoundingClientRect();
        const power = [...document.querySelectorAll('.ps-power-tile .ps-btn')].map((b) => b.offsetHeight);
        return {
          tabs: tabs.map((b) => b.textContent.trim()), fit: tabs.every((b) => b.scrollWidth <= b.clientWidth && b.getBoundingClientRect().right <= bar.right + 1),
          small: tabs.filter((b) => b.offsetHeight < 44).length, grid: !!document.querySelector('.ps-widget-grid').offsetParent,
          consoleHeight: Math.round(consoleBox.height), power, fits: document.documentElement.scrollWidth <= innerWidth,
        };
      });
      assert.deepEqual(layout.tabs, ['Console', 'Players', 'Files', 'More'], width + ': four tabs');
      assert.ok(layout.fit && layout.fits, width + ': tabs fit on one row without clipping');
      assert.equal(layout.small, 0, width + ': tabs are touch-sized');
      assert.equal(layout.grid, false, width + ': the section grid makes way for the tabs');
      assert.ok(layout.consoleHeight >= 600, width + ': console fills the screen (' + layout.consoleHeight + ' px)');
      assert.ok(layout.power.length === 4 && layout.power.every((h) => h >= 48), width + ': large power buttons');
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

    assert.deepEqual(errors, []);
  } finally {
    await browser.close();
    server.close();
  }
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
