// Speed walk of a lab panel: every Simplify page opened cold (empty browser cache) and warm (reload), with the time
// to first byte, to DOMContentLoaded, to load and to a quiet network, the number of requests, the bytes sent,
// Livewire round trips, requests made twice and the slowest requests. Also lists every Simplify asset with its
// size, gzip size and cache header. Writes perf.json next to the screenshots.
// node perf.js --base http://127.0.0.1:8001 --urls urls.json --out shots/beta38-perf
const fs = require('fs'), path = require('path'), zlib = require('zlib');
const { chromium } = require('playwright');
const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > 0 ? process.argv[i + 1] : d; };
const base = arg('base'), out = arg('out'), info = JSON.parse(fs.readFileSync(arg('urls'), 'utf8'));
const rounds = Number(arg('rounds', 3));
for (const k of Object.keys(info.pages)) info.pages[k] = info.pages[k].replace(/^https?:\/\/[^/]+/, '');
fs.mkdirSync(out, { recursive: true });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const median = (xs) => { const s = [...xs].sort((a, b) => a - b); return s.length ? s[Math.floor(s.length / 2)] : null; };
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROMIUM || undefined });
  const login = await browser.newContext({ viewport: { width: 1440, height: 900 }, baseURL: base });
  const lp = await login.newPage();
  await lp.goto('/login'); await lp.waitForLoadState('networkidle').catch(() => {});
  await lp.fill('#form\\.login', 'lab@example.com'); await lp.fill('#form\\.password', 'LabPassword123!');
  await lp.click('button[type="submit"]'); await lp.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 30000 }).catch(() => {});
  const state = await login.storageState(); await login.close();
  const overview = info.pages['Dashboard (app)'];
  const targets = { overview, 'server page': overview.split('?')[0] + '?server=' + info.server };
  for (const [name, url] of Object.entries(info.pages)) if (!name.startsWith('Dashboard')) targets[name] = url;
  const assets = new Map();
  const results = {};
  // One visit: every request with its timing and size, until the network is quiet for a moment.
  const visit = async (page, url, reload) => {
    const requests = [];
    const onFinished = async (req) => {
      const res = await req.response().catch(() => null); const timing = req.timing();
      const sizes = await req.sizes().catch(() => ({ responseBodySize: 0 }));
      requests.push({ url: req.url().replace(base, ''), method: req.method(), type: req.resourceType(), status: res?.status() || 0,
        ms: Math.round(timing.responseEnd > 0 ? timing.responseEnd : 0), bytes: sizes.responseBodySize || 0 });
      if (res && /\/simplify\/assets\//.test(req.url()) && !assets.has(req.url().split('?')[0])) {
        const body = await res.body().catch(() => null);
        if (body && body.length) assets.set(req.url().split('?')[0], { url: req.url().replace(base, '').split('?')[0], bytes: body.length, gzip: zlib.gzipSync(body).length, cache: await res.headerValue('cache-control').catch(() => null) });
      }
    };
    page.on('requestfinished', onFinished);
    const started = Date.now();
    if (reload) await page.reload({ waitUntil: 'load' }); else await page.goto(url, { waitUntil: 'load' });
    const loadMs = Date.now() - started;
    await page.waitForLoadState('networkidle').catch(() => {});
    const quietMs = Date.now() - started;
    await sleep(300);
    page.off('requestfinished', onFinished);
    const nav = await page.evaluate(() => { const n = performance.getEntriesByType('navigation')[0]; return n ? { ttfb: Math.round(n.responseStart), dcl: Math.round(n.domContentLoadedEventEnd), load: Math.round(n.loadEventEnd), html: n.transferSize || n.encodedBodySize } : {}; });
    const counts = {}; for (const r of requests) if (r.method === 'GET') counts[r.url] = (counts[r.url] || 0) + 1;
    return { loadMs, quietMs, ...nav, requests: requests.length, bytes: requests.reduce((a, r) => a + r.bytes, 0),
      livewire: requests.filter((r) => /livewire\/update/.test(r.url)).length, doubles: Object.entries(counts).filter(([, n]) => n > 1).map(([u, n]) => u.slice(0, 120) + ' ×' + n),
      slowest: [...requests].sort((a, b) => b.ms - a.ms).slice(0, 5).map((r) => `${r.ms} ms ${r.method} ${r.url.slice(0, 100)}`) };
  };
  for (const [name, url] of Object.entries(targets)) {
    const cold = [], warm = [];
    for (let i = 0; i < rounds; i++) {
      const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, baseURL: base, storageState: state, serviceWorkers: 'block' });
      const page = await ctx.newPage();
      cold.push(await visit(page, url, false));
      warm.push(await visit(page, url, true));
      await ctx.close();
    }
    const sum = (xs) => ({ ttfb: median(xs.map((x) => x.ttfb)), dcl: median(xs.map((x) => x.dcl)), load: median(xs.map((x) => x.loadMs)), quiet: median(xs.map((x) => x.quietMs)), requests: median(xs.map((x) => x.requests)), kb: Math.round(median(xs.map((x) => x.bytes)) / 1024), livewire: median(xs.map((x) => x.livewire)) });
    results[name] = { url, cold: sum(cold), warm: sum(warm), doubles: cold[0].doubles, slowest: cold[0].slowest };
    console.log(`${name}: cold ttfb ${results[name].cold.ttfb} ms, load ${results[name].cold.load} ms, quiet ${results[name].cold.quiet} ms, ${results[name].cold.requests} req, ${results[name].cold.kb} KB, ${results[name].cold.livewire} livewire | warm load ${results[name].warm.load} ms, ${results[name].warm.kb} KB`);
  }
  await browser.close();
  const list = [...assets.values()].sort((a, b) => b.bytes - a.bytes);
  fs.writeFileSync(path.join(out, 'perf.json'), JSON.stringify({ version: info.version, base, pages: results, assets: list }, null, 2));
  for (const a of list.slice(0, 12)) console.log(`  asset ${a.url}: ${Math.round(a.bytes / 1024)} KB (gzip ${Math.round(a.gzip / 1024)} KB), cache ${a.cache}`);
})().catch((e) => { console.error(e); process.exit(1); });
