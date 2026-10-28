// Layout problems a screenshot alone would not flag: sideways overflow, clipped single-line text, controls and popups
// off the screen, content taller than the screen that nothing scrolls. Runs in the page: page.evaluate(layoutProblems).
module.exports = () => {
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
  // An open popup locks the page under it on purpose; the popup itself is checked above.
  const popupOpen = [...document.querySelectorAll('.ps-modal, .fi-modal-window')].some((el) => el.checkVisibility() && !el.closest('.ps-dock'));
  const tall = root.scrollHeight > innerHeight + 20 && !popupOpen;
  if (tall) {
    const before = scrollY; scrollTo(0, 300); const moved = scrollY !== before; scrollTo(0, before);
    const panes = [...document.querySelectorAll('body *')].filter((el) => { const cs = getComputedStyle(el); return /(auto|scroll)/.test(cs.overflowY) && el.scrollHeight > el.clientHeight + 20 && visible(el) && el.clientHeight > innerHeight * 0.4; });
    if (!moved && !panes.length) problems.push('content is taller than the screen but nothing scrolls');
  }
  return problems;
};
