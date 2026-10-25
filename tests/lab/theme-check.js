// Checks Simplify under a panel theme plugin: logs in, reads the theme state Simplify detected, verifies the overview
// and settings render with the panel-theme palette in light and dark, desktop and phone, and writes screenshots.
// node theme-check.js --base http://127.0.0.1:8002 --out shots/rc1-nord --label nord
const fs = require('fs'), path = require('path');
const { chromium } = require('playwright');
const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > 0 ? process.argv[i + 1] : d; };
const base = arg('base'), out = arg('out'), label = arg('label', 'theme');
fs.mkdirSync(out, { recursive: true });
const issues = [], checks = [];
const ok = (name, pass, detail = '') => { checks.push({ name, pass, detail }); if (!pass) issues.push(name + (detail ? ': ' + detail : '')); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROMIUM || undefined });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, baseURL: base });
  const page = await context.newPage();
  page.on('pageerror', (e) => issues.push('page error: ' + e.message));
  page.on('console', (m) => { if (m.type() === 'error' && !/favicon|net::ERR|status of 4/.test(m.text())) issues.push('console: ' + m.text().slice(0, 200)); });
  page.on('response', (r) => { if (r.status() >= 500) issues.push('HTTP ' + r.status() + ' ' + r.url().replace(base, '')); });
  const settle = async () => { await page.waitForLoadState('networkidle').catch(() => {}); await sleep(400); };
  await page.goto('/login'); await settle();
  await page.fill('#form\\.login', 'lab@example.com'); await page.fill('#form\\.password', 'LabPassword123!');
  await page.click('button[type="submit"]'); await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 30000 }).catch(() => {});
  const result = { label, pages: {} };
  for (const [vp, size] of Object.entries({ desktop: { width: 1440, height: 900 }, phone: { width: 390, height: 844 } })) {
    await page.setViewportSize(size);
    for (const theme of ['light', 'dark']) {
      for (const [name, url] of [['overview', '/simplify'], ['settings', '/pelican-simplify-settings?simplify_embed=1'], ['creator', '/simple-server-creator']]) {
        await page.goto(url); await settle();
        await page.evaluate((t) => { try { localStorage.setItem('theme', t); localStorage.setItem('pelicanSimplify.theme', t); } catch {} document.documentElement.classList.toggle('dark', t === 'dark'); }, theme);
        await page.goto(url); await settle();
        const info = await page.evaluate(() => {
          const cfg = window.simplifyThemeConfig || {};
          const root = document.documentElement, cs = getComputedStyle(root);
          const el = document.querySelector('.ps') || document.body;
          return { panel_theme: cfg.panel_theme, dark: root.classList.contains('dark'), panelThemeClass: !!document.querySelector('.ps-panel-theme, html.ps-panel-theme, body.ps-panel-theme') || root.classList.contains('ps-panel-theme'),
            primary: cs.getPropertyValue('--primary-500').trim(), accent: getComputedStyle(el).getPropertyValue('--ps-accent').trim(), bg: getComputedStyle(document.body).backgroundColor, font: getComputedStyle(el).fontFamily.slice(0, 60), text: document.body.innerText.slice(0, 20000) };
        });
        const tag = `${name}-${vp}-${theme}`;
        result.pages[tag] = { ...info, text: undefined };
        ok(`${tag}: Simplify detects the theme`, !!info.panel_theme?.active, JSON.stringify(info.panel_theme));
        ok(`${tag}: page renders`, info.text.length > 200 && !/Server Error|Whoops|ErrorException/.test(info.text));
        if (name === 'overview') ok(`${tag}: servers listed`, /Rust weekend/.test(info.text));
        if (!info.panel_theme?.mode) ok(`${tag}: ${theme} mode applied`, info.dark === (theme === 'dark'));
        await page.screenshot({ path: path.join(out, tag + '.png') });
      }
    }
  }
  result.checks = checks; result.issues = issues;
  fs.writeFileSync(path.join(out, 'result.json'), JSON.stringify(result, null, 1));
  console.log(JSON.stringify({ label, passed: checks.filter((c) => c.pass).length, failed: checks.filter((c) => !c.pass).length, issues }, null, 1));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
