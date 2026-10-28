// Browser check for right-to-left languages: with the Arabic pack the dashboard mirrors (logical CSS),
// while console lines and numbers with units stay left-to-right. Run with SIMPLIFY_LOCALE=ar.
const assert = require('assert');
const { chromium } = require('playwright');
const { start } = require('./browser-harness');

(async () => {
  const server = await start();
  const base = 'http://127.0.0.1:' + server.address().port;
  const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROMIUM || undefined });
  const open = async (url) => {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.goto(base + url);
    await page.waitForFunction(() => window.Alpine && document.querySelector('.ps') && !document.querySelector('.ps').hasAttribute('x-cloak'));
    return page;
  };
  try {
    const overview = await open('/?mode=overview&theme=light');
    assert.equal(await overview.evaluate(() => document.documentElement.dir), 'rtl');
    const card = await overview.evaluate(() => {
      const entry = document.querySelector('.ps-server-entry:has(h3):has(.ps-card-badges)');
      const box = (sel) => entry.querySelector(sel).getBoundingClientRect();
      return { title: box('h3').right, badges: box('.ps-card-badges').left, card: entry.getBoundingClientRect(), count: getComputedStyle(entry.querySelector('.ps-card-bottom > span')).direction, chevron: getComputedStyle(entry.querySelector('.ps-card-bottom use')).transform };
    });
    assert.ok(card.title > card.card.left + card.card.width / 2, 'the server name starts on the right');
    assert.ok(card.badges < card.card.left + card.card.width / 2, 'the game and status badges sit on the left');
    assert.equal(card.count, 'ltr', 'player count stays left-to-right');
    assert.match(card.chevron, /^matrix\(-1/, 'the open chevron points left');
    const page = await open('/?mode=server&theme=dark');
    assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('.ps-console-output')).direction), 'ltr', 'console lines stay left-to-right');
    console.log('PASS right-to-left: mirrored cards, left-pointing chevrons, console and numbers left-to-right');
  } finally {
    await browser.close();
    server.close();
  }
})().catch((e) => { console.error(e); process.exit(1); });
