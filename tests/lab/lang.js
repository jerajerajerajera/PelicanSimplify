// The language walk: every Simplify page in one language pack, on a desktop and a phone. Records HTTP errors, page
// and console errors, raw translation keys, English texts that should have been translated, the page direction
// (rtl for Arabic) and pages wider than the screen, and takes a screenshot of every step.
//   node tests/lab/lang.js --base http://127.0.0.1:8001 --urls urls.json --out shots/lang-nl --locale nl
// Set the admin's language first (Pelican reads it from the user): tests/lab/run-lang.sh does both.
const fs = require('fs'), path = require('path'), { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const arg = (name, fallback) => { const i = process.argv.indexOf('--' + name); return i > 0 ? process.argv[i + 1] : fallback; };
const base = arg('base'), out = arg('out'), locale = arg('locale', 'nl'), urls = JSON.parse(fs.readFileSync(arg('urls'), 'utf8'));
const LANG = path.join(__dirname, '../../lang');
fs.mkdirSync(out, { recursive: true });

// Every text of a group, flattened to key => text.
const load = (loc) => {
  const flat = {};
  for (const file of fs.readdirSync(path.join(LANG, 'en'))) {
    const group = file.replace(/\.php$/, ''), source = path.join(LANG, loc, file);
    if (!fs.existsSync(source)) continue;
    const walk = (node, prefix) => { for (const [k, v] of Object.entries(node)) typeof v === 'object' && v !== null ? walk(v, prefix + k + '.') : (flat[prefix + k] = String(v)); };
    walk(JSON.parse(execFileSync(process.env.PHP_BIN || 'php', ['-r', 'echo json_encode(require $argv[1]);', source])), group + '.');
  }
  return flat;
};
const english = load('en'), local = load(locale);
// English sentences long enough to be unmistakable, that this language translates differently: if one shows on the
// page, that text was not translated.
const leftovers = Object.entries(english)
  .filter(([key, text]) => local[key] && local[key] !== text && !/[:|{\[<]/.test(text) && text.split(' ').length >= 4)
  .map(([key, text]) => [key, text]);

const results = [], issues = [];
const ok = (name, pass, detail = '') => { results.push({ name, pass, detail }); if (!pass) issues.push(name + (detail ? ': ' + detail : '')); console.log((pass ? 'PASS ' : 'FAIL ') + name + (detail && !pass ? ' (' + detail + ')' : '')); };

(async () => {
  const browser = await chromium.launch(process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {});
  for (const [device, viewport] of [['desktop', { width: 1440, height: 900 }], ['phone', { width: 390, height: 844 }]]) {
    const context = await browser.newContext({ baseURL: base, viewport, isMobile: device === 'phone', hasTouch: device === 'phone' });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push('pageerror ' + e.message));
    page.on('console', (m) => { if (m.type() === 'error' && !/favicon|Failed to load resource.*(404|401)/.test(m.text())) errors.push('console ' + m.text()); });
    page.on('response', (r) => { if (r.status() >= 500) errors.push('HTTP ' + r.status() + ' ' + r.url().replace(base, '')); });
    const settle = () => page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
    await page.goto('/login'); await settle();
    await page.fill('#form\\.login', 'lab@example.com'); await page.fill('#form\\.password', 'LabPassword123!');
    await page.click('button[type="submit"]'); await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 30000 }).catch(() => {});
    await settle();
    for (const [name, url] of Object.entries(urls.pages)) {
      errors.length = 0;
      const response = await page.goto(url).catch(() => null); await settle(); await page.waitForTimeout(400);
      const tag = `${name} [${locale}, ${device}]`;
      ok(`${tag} renders`, !!response && response.status() === 200 && !page.url().includes('/login'), response ? 'HTTP ' + response.status() : 'no response');
      const seen = await page.evaluate(() => ({
        dir: document.documentElement.getAttribute('dir') || 'ltr',
        lang: document.documentElement.getAttribute('lang'),
        text: document.body.innerText,
        wide: document.documentElement.scrollWidth - window.innerWidth,
        states: [...document.querySelectorAll('.ps-status')].map((el) => (el.textContent || el.getAttribute('aria-label') || '').trim()).filter(Boolean),
      }));
      ok(`${tag} direction`, seen.dir === (['ar', 'fa', 'he', 'ur'].includes(locale) ? 'rtl' : 'ltr'), seen.dir);
      const raw = seen.text.match(/pelican-simplify::[\w.-]+/g) || [];
      ok(`${tag} no raw keys`, raw.length === 0, raw.slice(0, 3).join(', '));
      const missed = leftovers.filter(([, text]) => seen.text.includes(text)).map(([key]) => key);
      ok(`${tag} no English left`, missed.length === 0, missed.slice(0, 4).join(', '));
      // Short words such as server states slip past the sentence check above.
      const states = locale === 'en' ? [] : seen.states.filter((text) => /^(running|offline|starting|stopping|installing|install failed)$/i.test(text));
      ok(`${tag} server states translated`, states.length === 0, [...new Set(states)].join(', '));
      ok(`${tag} fits the screen`, seen.wide <= 1, seen.wide + 'px wider');
      ok(`${tag} no errors`, errors.length === 0, errors.slice(0, 3).join(' | '));
      await page.screenshot({ path: path.join(out, `${device}-${name.replace(/[^\w]+/g, '-')}.png`), fullPage: false });
    }
    await context.close();
  }
  await browser.close();
  fs.writeFileSync(path.join(out, 'result.json'), JSON.stringify({ locale, results, issues }, null, 2));
  console.log(`${locale}: ${results.filter((r) => r.pass).length}/${results.length} checks passed, ${issues.length} issues`);
  process.exit(issues.length ? 1 : 0);
})();
