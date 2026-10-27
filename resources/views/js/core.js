/* Simplify core runtime, loaded on every panel page (and inside embedded workspaces).
   1. Theme: light/dark preference, accent colour and the embedded-workspace marker, applied before paint.
   2. UI: tooltips, toasts, confirm/prompt dialogs and a notice history, all in the Simplify style.
   Embedded pages forward toasts and dialogs to the dashboard so they cover the whole screen. */
(() => {
  if (window.SimplifyTheme) {
    window.SimplifyTheme.configure(window.simplifyThemeConfig || {});
    return;
  }
  const root = document.documentElement;
  const KEY = 'pelicanSimplify.theme';
  const CHOICES = ['light', 'dark', 'system'];
  const media = matchMedia('(prefers-color-scheme: dark)');
  let options = window.simplifyThemeConfig || {};
  let preference = 'system';
  // Texts from lang/<locale>/js.php in the user's Pelican language (English fills the gaps); :name placeholders.
  const t = (key, params = {}) => {
    let text = window.simplifyLang?.[key] ?? key;
    for (const name of Object.keys(params).sort((a, b) => b.length - a.length)) text = text.split(':' + name).join(String(params[name]));
    return text;
  };
  try {
    const saved = localStorage.getItem(KEY);
    if (CHOICES.includes(saved)) preference = saved;
  } catch {}

  // An embedded workspace learns its kind and accent from its own <iframe> element.
  let frameKind = '';
  let frameAccent = '';
  let frameTab = '';
  let frameHost = false;
  try {
    const host = window.frameElement;
    if (host?.dataset?.psFrame) {
      frameKind = host.dataset.psFrame;
      frameAccent = host.dataset.psAccent || '';
      frameTab = host.dataset.psTab || '';
      frameHost = !!host.dataset.psHost;
    }
  } catch {}
  // Simplify settings show one Pelican settings tab per frame: the tab bar is hidden (CSS) and the asked tab opened
  // when the page did not already open it from the URL.
  const focusFrameTab = () => {
    if (!frameTab) return;
    const item = [...document.querySelectorAll('.fi-tabs-item')].find((el) => el.textContent.trim() === frameTab);
    if (item && !item.classList.contains('fi-active')) item.click();
  };

  // A theme plugin can force one mode (Catppuccin is dark only); then the choice in the menu has no effect.
  const forced = () => options.panel_theme?.mode || '';
  const dark = () => (forced() ? forced() === 'dark' : preference === 'dark' || (preference === 'system' && media.matches));
  const hex = (value) => (/^#[0-9a-f]{6}$/i.test(value || '') ? value : '');
  const accentInk = (color) => {
    const [r, g, b] = color.slice(1).match(/../g).map((v) => {
      const x = parseInt(v, 16) / 255;
      return x <= 0.04045 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4;
    });
    return r * 0.2126 + g * 0.7152 + b * 0.0722 > 0.179 ? '#111118' : '#ffffff';
  };

  // Any CSS colour (Filament stores oklch) as #rrggbb, so the accent ink can be worked out from it.
  const toHex = (color) => {
    try {
      const ctx = Object.assign(document.createElement('canvas'), { width: 1, height: 1 }).getContext('2d');
      ctx.fillStyle = color;
      ctx.fillRect(0, 0, 1, 1);
      return '#' + [...ctx.getImageData(0, 0, 1, 1).data.slice(0, 3)].map((v) => v.toString(16).padStart(2, '0')).join('');
    } catch {
      return '';
    }
  };
  // With a panel theme the default (grey) accent becomes the theme's primary colour; a chosen accent stays.
  const themeAccent = () => {
    if (!options.panel_theme?.active || (options.accent_color || '#878787') !== '#878787') return '';
    const value = getComputedStyle(root).getPropertyValue(dark() ? '--primary-400' : '--primary-600').trim();
    return value ? hex(toHex(value)) : '';
  };

  const apply = () => {
    const ownPage = !frameKind && !options.legacy_page;
    const panelTheme = options.panel_theme || {};
    // A theme with its own stylesheet keeps styling Pelican's pages; Simplify only styles its own screens then.
    const legacy = frameKind ? !['create', 'settings'].includes(frameKind) : !ownPage;
    const themed = !!options.global_theme && !(panelTheme.own_css && legacy) && (frameKind ? !!options.legacy_theme || !legacy : ownPage);
    root.classList.toggle('ps-theme', themed);
    root.classList.toggle('ps-panel-theme', !!panelTheme.active);
    if (themed || ownPage) root.classList.toggle('dark', dark());
    const accent = hex(frameAccent) || themeAccent() || hex(options.accent_color);
    if (accent) {
      root.style.setProperty('--ps-accent', accent);
      root.style.setProperty('--ps-on-accent', accentInk(accent));
    }
    root.classList.toggle('ps-console-dark', !!options.console_dark);
    root.classList.toggle('ps-console-adaptive', !options.console_dark);
    if (frameKind) {
      root.classList.add('ps-frame');
      root.dataset.psFrame = frameKind;
      if (frameTab) root.dataset.psTab = frameTab;
      if (frameHost) root.dataset.psHost = '1';
    }
    ui.layer?.classList.toggle('dark', dark());
    // The phone's status bar follows the chosen theme, not only the system one.
    if (!frameKind) {
      const bar = (panelTheme.active && hex(toHex(getComputedStyle(root).getPropertyValue('--ps-bg').trim()))) || (dark() ? '#101012' : '#f4f4f5');
      document.querySelectorAll('meta[name="theme-color"]').forEach((meta) => meta.setAttribute('content', bar));
    }
    window.dispatchEvent(new CustomEvent('simplify:theme', { detail: { preference, dark: dark() } }));
  };

  window.SimplifyTheme = {
    accentInk,
    dark,
    forced,
    preference: () => preference,
    set(value) {
      if (!CHOICES.includes(value)) return;
      preference = value;
      try {
        localStorage.setItem(KEY, value);
        localStorage.setItem('theme', value);
      } catch {}
      apply();
    },
    /** Keeps an already loaded workspace in step with the dashboard (theme and accent). */
    frame(frame, kind, accent) {
      if (!frame) return;
      if (kind) frame.dataset.psFrame = kind;
      if (accent) frame.dataset.psAccent = accent;
      try {
        frame.contentWindow?.SimplifyTheme?.sync(preference, frame.dataset.psAccent || '');
      } catch {}
    },
    sync(value, accent) {
      if (CHOICES.includes(value)) preference = value;
      if (hex(accent)) frameAccent = accent;
      apply();
    },
    configure(value) {
      options = value || {};
      apply();
    },
  };

  media.addEventListener('change', () => preference === 'system' && apply());
  window.addEventListener('storage', (event) => {
    if (event.key === KEY && CHOICES.includes(event.newValue)) {
      preference = event.newValue;
      apply();
    }
  });
  // Filament's own theme switcher dispatches this event. Filament also fires it while starting up with
  // its own stored theme, so early events are ignored; embedded pages always follow the dashboard.
  const loadedAt = Date.now();
  window.addEventListener('theme-changed', (event) => {
    if (frameKind || Date.now() - loadedAt < 2000) return;
    const value = event.detail?.theme ?? event.detail;
    if (CHOICES.includes(value) && value !== preference) window.SimplifyTheme.set(value);
  });
  document.addEventListener('livewire:navigated', () => window.SimplifyTheme.configure(window.simplifyThemeConfig || options));

  /* UI runtime ------------------------------------------------------------------------------ */
  const ui = { layer: null, tooltip: null, toasts: null, history: [], tipTarget: null, tipTimer: 0, dialogs: 0 };
  const icon = (name) => {
    const paths = {
      info: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 8v5m0-9h.01',
      ok: 'm5 12 4 4 10-10',
      warn: 'M12 3 2 21h20Zm0 7v5m0 3h.01',
      danger: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm-3 6 6 6m0-6-6 6',
      close: 'm6 6 12 12M18 6 6 18',
    };
    return '<svg class="ps-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="' + (paths[name] || paths.info) + '"/></svg>';
  };
  const parentUi = () => {
    try {
      if (window.parent !== window && window.parent.Simplify?.ui === true) return window.parent.Simplify;
    } catch {}
    return null;
  };
  // Dialogs open inside the active popup (if any) so its focus trap keeps working.
  const mountPoint = () => {
    const open = [...document.querySelectorAll('[aria-modal="true"]')].filter((el) => el.getClientRects().length);
    return open[open.length - 1] || layer();
  };
  const layer = () => {
    if (ui.layer?.isConnected) return ui.layer;
    ui.layer = document.createElement('div');
    ui.layer.className = 'ps-ui' + (dark() ? ' dark' : '');
    ui.layer.style.setProperty('--ps-accent', getComputedStyle(root).getPropertyValue('--ps-accent').trim() || '#878787');
    document.body.append(ui.layer);
    return ui.layer;
  };

  // Tooltips: every [data-tip] element; stray title attributes inside Simplify become tooltips too.
  const TIP_SCOPE = '.ps, .ps-ui, .ssc-wizard, .ps-settings-page, .ps-legacy-return';
  const placeTip = (target) => {
    const tip = ui.tooltip;
    const box = target.getBoundingClientRect();
    const own = tip.getBoundingClientRect();
    let top = box.bottom + 8;
    if (top + own.height > innerHeight - 8) top = box.top - own.height - 8;
    const left = Math.min(Math.max(8, box.left + box.width / 2 - own.width / 2), innerWidth - own.width - 8);
    tip.style.transform = `translate(${Math.round(left)}px, ${Math.round(Math.max(8, top))}px)`;
  };
  const showTip = (target) => {
    const text = target.getAttribute('data-tip');
    if (!text || !target.isConnected) return;
    if (!ui.tooltip) {
      ui.tooltip = document.createElement('div');
      ui.tooltip.className = 'ps-tooltip';
      ui.tooltip.setAttribute('role', 'tooltip');
      ui.tooltip.id = 'ps-tooltip';
      layer().append(ui.tooltip);
    }
    const label = target.getAttribute('data-tip-label');
    ui.tooltip.replaceChildren();
    if (label) {
      const small = document.createElement('small');
      small.textContent = label;
      ui.tooltip.append(small);
    }
    ui.tooltip.append(document.createTextNode(text));
    ui.tooltip.classList.add('is-visible');
    target.setAttribute('aria-describedby', 'ps-tooltip');
    placeTip(target);
  };
  const hideTip = () => {
    clearTimeout(ui.tipTimer);
    ui.tipTarget?.removeAttribute('aria-describedby');
    ui.tipTarget = null;
    ui.tooltip?.classList.remove('is-visible');
  };
  const tipTargetOf = (node) => {
    if (!(node instanceof Element)) return null;
    const titled = node.closest('[title]');
    if (titled && !titled.matches('iframe, svg, title') && titled.closest(TIP_SCOPE)) {
      titled.setAttribute('data-tip', titled.getAttribute('title'));
      titled.removeAttribute('title');
    }
    return node.closest('[data-tip]');
  };
  const enterTip = (event, delay) => {
    const target = tipTargetOf(event.target);
    if (target === ui.tipTarget) return;
    hideTip();
    if (!target) return;
    ui.tipTarget = target;
    ui.tipTimer = setTimeout(() => showTip(target), delay);
  };
  document.addEventListener('pointerover', (event) => event.pointerType !== 'touch' && enterTip(event, 350), { passive: true });
  document.addEventListener('focusin', (event) => enterTip(event, 0));
  document.addEventListener('pointerout', (event) => {
    if (ui.tipTarget && !ui.tipTarget.contains(event.relatedTarget)) hideTip();
  }, { passive: true });
  document.addEventListener('focusout', hideTip);
  document.addEventListener('pointerdown', hideTip, { passive: true });
  window.addEventListener('scroll', hideTip, { passive: true, capture: true });
  document.addEventListener('keydown', (event) => event.key === 'Escape' && hideTip());

  const toast = (message, tone = 'info', duration) => {
    const text = String(message ?? '').trim();
    if (!text) return;
    const parent = parentUi();
    if (parent) return parent.toast(text, tone, duration);
    tone = ['ok', 'warn', 'danger', 'info'].includes(tone) ? tone : 'info';
    if (!ui.toasts?.isConnected) {
      ui.toasts = document.createElement('div');
      ui.toasts.className = 'ps-toasts';
      ui.toasts.setAttribute('role', 'status');
      ui.toasts.setAttribute('aria-live', 'polite');
      layer().append(ui.toasts);
    }
    const node = document.createElement('div');
    node.className = 'ps-toast is-' + tone;
    node.innerHTML = icon(tone) + '<span></span><button type="button" aria-label="Dismiss">' + icon('close') + '</button>';
    node.querySelector('span').textContent = text;
    const remove = () => node.remove();
    node.querySelector('button').addEventListener('click', remove);
    ui.toasts.append(node);
    while (ui.toasts.children.length > 3) ui.toasts.firstElementChild.remove();
    setTimeout(remove, duration || (tone === 'danger' ? 10000 : 6500));
    const entry = { id: Date.now() + Math.random(), text, tone, time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) };
    ui.history = [entry, ...ui.history].slice(0, 20);
    window.dispatchEvent(new CustomEvent('simplify:notice', { detail: entry }));
  };

  /**
   * A Simplify confirmation dialog. Resolves to true/false, or to the typed text when `input` is set.
   * Options: title, message, confirmLabel, cancelLabel, tone ('danger' | 'primary'), input, inputType ('password'), placeholder, requireText.
   */
  const dialog = (opts = {}) => {
    const parent = parentUi();
    if (parent) return parent.confirm(opts);
    return new Promise((resolve) => {
      const previous = document.activeElement;
      const wrap = document.createElement('div');
      wrap.className = 'ps-modal-layer';
      wrap.dataset.open = 'true';
      wrap.innerHTML =
        '<section class="ps-modal is-sm" role="alertdialog" aria-modal="true" aria-labelledby="ps-dialog-title">' +
        '<header class="ps-modal-head"><h2 id="ps-dialog-title"></h2></header>' +
        '<div class="ps-modal-body"><p class="ps-help" data-message></p></div>' +
        '<footer class="ps-modal-foot"><button type="button" class="ps-btn" data-cancel></button><button type="button" class="ps-btn" data-confirm></button></footer>' +
        '</section>';
      wrap.querySelector('h2').textContent = opts.title || 'Are you sure?';
      const message = wrap.querySelector('[data-message]');
      if (opts.message) message.textContent = opts.message;
      else message.remove();
      const cancel = wrap.querySelector('[data-cancel]');
      const confirm = wrap.querySelector('[data-confirm]');
      cancel.textContent = opts.cancelLabel || t('cancel');
      confirm.textContent = opts.confirmLabel || t('confirm');
      confirm.classList.add(opts.tone === 'danger' ? 'is-danger' : 'is-primary');
      let field = null;
      if (opts.input || opts.requireText) {
        const label = document.createElement('label');
        label.className = 'ps-field';
        label.textContent = opts.inputLabel || (opts.requireText ? 'Type “' + opts.requireText + '” to confirm' : '');
        field = document.createElement('input');
        field.placeholder = opts.placeholder || opts.requireText || '';
        field.autocomplete = 'off';
        if (opts.inputType === 'password') field.type = 'password';
        label.append(field);
        wrap.querySelector('.ps-modal-body').append(label);
        const check = () => (confirm.disabled = !!opts.requireText && field.value !== opts.requireText);
        field.addEventListener('input', check);
        check();
      }
      const finish = (value) => {
        wrap.remove();
        ui.dialogs--;
        if (!ui.dialogs) root.classList.remove('ps-ui-locked');
        try { previous?.focus?.(); } catch {}
        resolve(value);
      };
      cancel.addEventListener('click', () => finish(field ? null : false));
      confirm.addEventListener('click', () => !confirm.disabled && finish(field ? field.value : true));
      wrap.addEventListener('click', (event) => event.target === wrap && finish(field ? null : false));
      wrap.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') { event.stopPropagation(); finish(field ? null : false); }
        if (event.key === 'Enter' && event.target === field && !confirm.disabled) finish(field.value);
        if (event.key === 'Tab') {
          const items = [...wrap.querySelectorAll('button:not(:disabled), input')];
          const index = items.indexOf(document.activeElement);
          const next = items[(index + (event.shiftKey ? -1 : 1) + items.length) % items.length];
          event.preventDefault();
          next?.focus();
        }
      });
      ui.dialogs++;
      root.classList.add('ps-ui-locked');
      const host = mountPoint();
      if (host !== ui.layer && !host.closest('.ps')) wrap.classList.toggle('dark', dark());
      host.append(wrap);
      (field || confirm).focus();
    });
  };

  window.Simplify = {
    ui: true,
    t,
    toast,
    confirm: dialog,
    prompt: (opts) => dialog({ ...opts, input: true }),
    history: () => ui.history.slice(),
    hideTooltip: hideTip,
  };

  // Embedded pages hand Filament's flash notifications ("File saved", errors) to the dashboard toast.
  const forwardNotifications = () => {
    if (!frameKind || !parentUi() || !window.MutationObserver) return;
    root.dataset.psForwardNotices = '';
    const tones = { success: 'ok', warning: 'warn', danger: 'danger', info: 'info' };
    const seen = new WeakSet();
    const forward = (node) => {
      if (seen.has(node) || node.closest('.fi-no-database')) return;
      seen.add(node);
      const title = node.querySelector('.fi-no-notification-title')?.textContent.trim() || '';
      const body = node.querySelector('.fi-no-notification-body')?.textContent.trim() || '';
      const status = [...node.classList].find((c) => c.startsWith('fi-status-'))?.slice(10);
      const text = [title, body].filter(Boolean).join(' — ');
      if (text) toast(text, tones[status] || 'info');
    };
    new MutationObserver((records) => {
      for (const record of records) for (const added of record.addedNodes) {
        if (added.nodeType !== 1) continue;
        if (added.matches('.fi-no-notification')) forward(added);
        else added.querySelectorAll?.('.fi-no-notification').forEach(forward);
      }
    }).observe(document.documentElement, { childList: true, subtree: true });
  };
  /* Header tools: a Pelican page in a Simplify popup (or host mode) shows its search field and its own buttons
     (New, Import, Save, …) in the Simplify header, so the page reads as part of the popup instead of a page inside it.
     The buttons there press the page's real buttons; dropdown groups and bulk actions stay on the page. */
  let unbridgeHeadTools = null;
  const bridgeHeadTools = () => {
    unbridgeHeadTools?.();
    unbridgeHeadTools = null;
    if (frameKind !== 'native' || frameTab || !window.MutationObserver || !document.body) return;
    let head;
    try {
      const host = window.frameElement;
      head = host.closest('.ps-modal')?.querySelector(':scope > .ps-modal-head') || host.closest('.ps-host-main')?.querySelector(':scope > .ps-host-head.is-page');
    } catch {}
    if (!head) return;
    const pdoc = head.ownerDocument;
    head.querySelectorAll(':scope > .ps-frame-tools').forEach((el) => el.remove());
    const bar = pdoc.createElement('div');
    bar.className = 'ps-frame-tools';
    head.insertBefore(bar, head.querySelector(':scope > .ps-close'));
    const tooltip = (el) => el.getAttribute('x-tooltip')?.match(/content:\s*'((?:[^'\\]|\\.)*)'/)?.[1]?.replace(/\\'/g, "'") || '';
    const label = (el) => el.getAttribute('aria-label') || el.getAttribute('title') || tooltip(el) || el.textContent.trim();
    // The page's own table, not one inside a form, tab or relation manager (those keep their toolbar).
    const mainTable = () => [...document.querySelectorAll('.fi-ta-ctn')].find((el) => !el.parentElement.closest('.fi-resource-relation-managers, .fi-resource-relation-manager, .fi-sc-tabs, .fi-section, .fi-modal, .fi-ta-ctn'));
    const sourceButtons = (table) => [...document.querySelectorAll('.fi-header-actions-ctn .fi-ac > :is(button, a)'), ...(table?.querySelectorAll('.fi-ta-header-toolbar > .fi-ta-actions > :is(button, a)') || [])];
    let filtersTimer = 0;
    const openFilters = (trigger) => {
      // The filter panel belongs to the page's toolbar: that row shows while the panel is open.
      root.classList.add('ps-tools-open');
      requestAnimationFrame(() => {
        const panel = trigger.closest('.fi-dropdown')?.querySelector('.fi-dropdown-panel');
        trigger.closest('.fi-dropdown-trigger')?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, button: 0 }));
        setTimeout(() => !panel?.offsetParent && trigger.click(), 60);
        clearInterval(filtersTimer);
        filtersTimer = setInterval(() => {
          if (panel?.isConnected && panel.offsetParent) return;
          clearInterval(filtersTimer);
          root.classList.remove('ps-tools-open');
        }, 400);
      });
    };
    const button = (source, text, primary) => {
      const btn = pdoc.createElement('button');
      btn.type = 'button';
      btn.className = 'ps-btn is-sm' + (primary ? ' is-primary' : ' is-icon is-subtle') + (source.classList.contains('fi-color-danger') ? ' is-danger' : '');
      btn.setAttribute('aria-label', text);
      // The page's tooltip says more ("Save changes", why Delete is off); the primary button shows it as its text.
      if (!primary) btn.dataset.tip = tooltip(source) || text;
      btn.disabled = source.disabled || source.getAttribute('aria-disabled') === 'true';
      const icon = source.querySelector('svg');
      if (icon) btn.append(pdoc.importNode(icon, true));
      if (primary) btn.append(pdoc.createTextNode(text));
      return btn;
    };
    let signature = null;
    const build = () => {
      const visible = pdoc.defaultView.getComputedStyle(head).display !== 'none';
      const table = visible ? mainTable() : null;
      const sources = visible ? sourceButtons(table) : [];
      const search = table?.querySelector('.fi-ta-search-field input') || null;
      const filters = table?.querySelector('.fi-ta-filters-dropdown .fi-dropdown-trigger > :is(button, a)') || null;
      const heading = document.querySelector('.fi-header-heading')?.textContent.trim() || '';
      const titles = [...head.querySelectorAll(':scope > :is(h2, small)')].map((el) => el.textContent.trim().toLowerCase()).filter(Boolean);
      const crumb = heading && titles.length && !titles.some((t) => heading.toLowerCase().includes(t) || t.includes(heading.toLowerCase())) ? heading : '';
      const next = JSON.stringify([crumb, !!search, !!filters, sources.map((s) => [label(s), s.disabled || s.getAttribute('aria-disabled')])]);
      if (next === signature) return;
      signature = next;
      bar.replaceChildren();
      const bridged = !!(sources.length || search || filters);
      root.toggleAttribute('data-ps-head-tools', bridged);
      bar.hidden = !bridged && !crumb;
      if (crumb) {
        const span = pdoc.createElement('span');
        span.className = 'ps-frame-crumb';
        span.textContent = crumb;
        bar.append(span);
      }
      if (search) {
        const field = pdoc.createElement('label');
        field.className = 'ps-frame-search';
        field.innerHTML = '<svg aria-hidden="true"><use href="#ps-search"/></svg>';
        const input = pdoc.createElement('input');
        input.type = 'search';
        input.placeholder = search.placeholder || t('search');
        input.setAttribute('aria-label', input.placeholder);
        input.value = search.value;
        input.addEventListener('input', () => {
          search.value = input.value;
          search.dispatchEvent(new Event('input', { bubbles: true }));
        });
        field.append(input);
        bar.append(field);
      }
      const primaries = [];
      const seen = new Set();
      for (const source of sources) {
        if (source.closest('.fi-dropdown')) continue;
        const text = /^save$/i.test(label(source)) ? tooltip(source) || label(source) : label(source);
        if (seen.has(text)) continue;
        seen.add(text);
        const primary = /\/create(\?|$)/.test(source.getAttribute('href') || '') || /^(save|create)\b/i.test(source.getAttribute('aria-label') || '');
        const btn = button(source, text, primary);
        btn.addEventListener('click', () => source.click());
        if (primary) primaries.push(btn);
        else bar.append(btn);
      }
      if (filters) {
        const btn = button(filters, label(filters) || t('filter'), false);
        btn.querySelectorAll('.fi-icon-btn-badge-ctn').forEach((el) => el.remove());
        btn.addEventListener('click', () => openFilters(filters));
        bar.append(btn);
      }
      bar.append(...primaries);
    };
    let queued = 0;
    const queue = () => {
      if (queued) return;
      queued = requestAnimationFrame(() => {
        queued = 0;
        build();
      });
    };
    build();
    const observer = new MutationObserver(queue);
    observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['aria-disabled', 'disabled'] });
    pdoc.defaultView.addEventListener('resize', queue);
    unbridgeHeadTools = () => {
      observer.disconnect();
      clearInterval(filtersTimer);
      pdoc.defaultView.removeEventListener('resize', queue);
      root.removeAttribute('data-ps-head-tools');
      bar.remove();
    };
  };
  if (document.body) bridgeHeadTools();
  else document.addEventListener('DOMContentLoaded', bridgeHeadTools, { once: true });
  // Pelican links inside the page (New Egg, Edit) navigate without a reload: the header follows the new page.
  document.addEventListener('livewire:navigated', bridgeHeadTools);
  window.addEventListener('pagehide', () => unbridgeHeadTools?.());
  /* Save bar: the same floating bar on every form. It slides in once something changed; when saving
     worked the button shows a check and the bar slides away again. */
  let pendingSaves = [];
  const settleSaves = (ok) => {
    const waiting = pendingSaves;
    pendingSaves = [];
    // Let Livewire paint validation messages first, then judge the result by them.
    setTimeout(() => waiting.forEach(({ api, scope }) => (ok && !scope.querySelector('.fi-fo-field-wrp-error-message, [data-validation-error]') ? api.saved() : api.failed())), 60);
  };
  const hookLivewire = () => {
    if (hookLivewire.done || !window.Livewire?.hook) return;
    hookLivewire.done = true;
    window.Livewire.hook('commit', ({ succeed, fail }) => {
      if (!pendingSaves.length) return;
      succeed(() => settleSaves(true));
      fail(() => settleSaves(false));
    });
  };
  document.addEventListener('livewire:init', hookLivewire);
  const saveBar = ({ mount, scope = mount, within = null, fixed = false, label = t('save_changes'), onSave, onDiscard = null, discardMessage = t('discard_message') }) => {
    hookLivewire();
    const bar = document.createElement('div');
    bar.className = 'ps-savebar' + (fixed ? ' is-fixed' : '');
    bar.setAttribute('role', 'region');
    bar.setAttribute('aria-label', t('save_changes'));
    bar.innerHTML = '<span class="ps-savebar-status" role="status"><i aria-hidden="true"></i><span class="ps-savebar-text">Unsaved changes</span></span>'
      + (onDiscard ? '<button type="button" class="ps-savebar-discard">Discard</button>' : '')
      + '<button type="button" class="ps-savebar-save"><svg class="ps-savebar-check" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7"/></svg><span class="ps-savebar-label"></span></button>';
    const text = bar.querySelector('.ps-savebar-text');
    const button = bar.querySelector('.ps-savebar-save');
    bar.querySelector('.ps-savebar-label').textContent = label;
    let timer = 0;
    const api = {
      el: bar,
      dirty(on = true) {
        if (bar.classList.contains('is-saving')) return;
        clearTimeout(timer);
        bar.classList.remove('is-saved');
        bar.classList.toggle('is-dirty', on);
        button.disabled = !on;
        button.removeAttribute('aria-disabled');
        text.textContent = t('unsaved_changes');
      },
      saving() {
        bar.classList.add('is-saving');
        button.disabled = true;
      },
      saved() {
        bar.classList.remove('is-saving', 'is-dirty');
        bar.classList.add('is-saved');
        api.rebase?.();
        // Nothing left to save: the check is a status, not a button.
        button.disabled = true;
        button.setAttribute('aria-disabled', 'true');
        text.textContent = t('all_changes_saved');
        clearTimeout(timer);
        timer = setTimeout(() => bar.classList.remove('is-saved'), 1600);
      },
      failed() {
        bar.classList.remove('is-saving');
        button.disabled = false;
        text.textContent = 'Not saved — check the highlighted fields';
      },
      // Waits for the Livewire request that saving starts.
      expectSave() {
        api.saving();
        pendingSaves.push({ api, scope });
      },
    };
    button.addEventListener('click', () => {
      if (button.disabled || bar.classList.contains('is-saved') || bar.classList.contains('is-saving')) return;
      onSave(api);
    });
    bar.querySelector('.ps-savebar-discard')?.addEventListener('click', () => {
      dialog({ title: t('discard_title'), message: discardMessage, confirmLabel: t('discard'), tone: 'danger' }).then((ok) => ok && onDiscard(api));
    });
    // Changed means: the values differ from the last saved state. Switching tabs, focusing or clicking
    // around never counts, and putting a value back hides the bar again.
    const controls = () => [...scope.querySelectorAll('input, select, textarea, [role="switch"], button[role="combobox"]')].filter((el) =>
      !el.closest('.ps-savebar, .fi-modal, [role="dialog"], [role="tablist"]')
      && (!within || el.closest(within))
      && !el.matches('input[type="search"], input[type="file"], .fi-sc-tabs > input')
      && !(el.type === 'hidden' && !el.name));
    // Each control gets a stable key, so controls that render later (lazy parts of a page) are new,
    // not changed. Values are compared per key against the last saved state.
    const keyOf = (el, index) => {
      const option = ['checkbox', 'radio'].includes(el.type) ? ':' + el.value : '';
      if (el.id) return 'id:' + el.id + option;
      if (el.name) return 'name:' + el.name + option;
      const wrapper = el.closest('[data-field-wrapper]');
      const anchor = wrapper?.querySelector('[id]')?.id || wrapper?.querySelector('label')?.textContent?.trim();
      return anchor ? 'field:' + anchor + option : 'index:' + index;
    };
    // Filament 5.10 (Pelican rc.1) renders a select as a button that shows the chosen option, without an input.
    const valueOf = (el) => (el.getAttribute('role') === 'switch' ? el.getAttribute('aria-checked')
      : el.getAttribute('role') === 'combobox' && el.tagName === 'BUTTON' ? el.textContent.trim()
      : ['checkbox', 'radio'].includes(el.type) ? String(el.checked) : el.value);
    const snapshot = () => new Map(controls().map((el, index) => [keyOf(el, index), valueOf(el)]));
    let baseline = null;
    const remember = () => { baseline = snapshot(); };
    api.rebase = remember;
    const check = () => {
      if (baseline === null) remember();
      let changed = false;
      for (const [key, value] of snapshot()) {
        if (!baseline.has(key)) baseline.set(key, value);
        else if (baseline.get(key) !== value) changed = true;
      }
      if (changed !== bar.classList.contains('is-dirty')) api.dirty(changed);
    };
    const later = (event) => {
      if (event.target.closest?.('.ps-savebar, .fi-modal, [role="dialog"]') || (within && !event.target.closest?.(within))) return;
      setTimeout(check, 30);
    };
    ['input', 'change', 'click'].forEach((type) => scope.addEventListener(type, later, true));
    // Select options can open outside the form; picking one is a change of its select.
    document.addEventListener('click', (event) => { if (event.target.closest?.('[role="option"]')) setTimeout(check, 30); }, true);
    // Values that settle after the page loads (switches, pickers) must not count: while nothing is
    // changed, the state just before each press or key is the saved state.
    const settle = (event) => {
      if (bar.classList.contains('is-dirty') || bar.classList.contains('is-saving') || event.target.closest?.('.ps-savebar')) return;
      remember();
    };
    ['pointerdown', 'keydown'].forEach((type) => scope.addEventListener(type, settle, true));
    setTimeout(remember, 400);
    mount.append(bar);
    return api;
  };

  // Pelican's own edit pages (users, nodes, settings…) keep their Save action in the page header.
  const enhanceEditPages = () => {
    const PAGE = '.fi-page.fi-resource-edit-record-page, .fi-page.fi-page-settings';
    const page = () => document.querySelector(PAGE);
    if (!page() || root.dataset.psSavebar) return;
    const saveButton = () => [...(page()?.querySelectorAll('.fi-header-actions-ctn :is(button, a)') || [])].find((el) => /^save\b/i.test((el.getAttribute('aria-label') || el.textContent || '').trim()));
    if (!saveButton()) return;
    root.dataset.psSavebar = '1';
    // Mounted outside the page: Livewire re-renders the page after saving and would remove it.
    const bar = saveBar({ mount: document.body, scope: document, within: PAGE, fixed: true, onSave: (api) => { api.expectSave(); saveButton()?.click(); } });
    // The header's own Save button reports to the same bar.
    document.addEventListener('click', (event) => {
      const header = event.target.closest?.('.fi-header-actions-ctn :is(button, a)');
      if (header && header === saveButton() && !bar.el.classList.contains('is-saving')) bar.expectSave();
    }, true);
  };
  const scheduleEnhance = () => setTimeout(enhanceEditPages, 300);
  document.addEventListener('DOMContentLoaded', scheduleEnhance);
  if (frameTab) window.addEventListener('load', () => setTimeout(focusFrameTab, 60));
  document.addEventListener('livewire:navigated', scheduleEnhance);
  window.Simplify.saveBar = saveBar;
  // Inline style for the panel name in the top-left corner; the font stack comes from the server's whitelist.
  window.Simplify.brandTextStyle = (brand, stack, accent) => {
    const hex = (value) => /^#[0-9a-f]{6}$/i.test(value || '') ? value : null;
    const ink = hex(brand?.color1) || 'var(--ps-ink, currentColor)';
    const style = {
      'font-family': stack || 'inherit',
      'font-weight': String(Number(brand?.weight) || 700),
      ...(Number(brand?.spacing) ? { 'letter-spacing': Number(brand.spacing) / 100 + 'em' } : {}),
      'text-transform': brand?.uppercase ? 'uppercase' : 'none',
    };
    if (brand?.color_style !== 'gradient') return { ...style, background: 'none', color: ink, '-webkit-text-fill-color': ink };
    const end = hex(brand?.color2) || hex(accent) || 'var(--ps-brand, var(--ps-accent, #2563eb))';
    return { ...style, 'background-image': `linear-gradient(110deg, ${ink} 40%, ${end} 100%)`, 'background-clip': 'text', '-webkit-background-clip': 'text', color: 'transparent', '-webkit-text-fill-color': 'transparent' };
  };

  /* Web app ------------------------------------------------------------------------------------
     The panel can be added to a phone's home screen. The service worker only shows an offline page when the
     panel cannot be reached; Simplify offers "Install app" where the browser allows it (Android, desktop
     Chrome and Edge) and explains the Share menu on iPhone and iPad. */
  const webApp = {
    prompt: null,
    standalone: () => matchMedia('(display-mode: standalone)').matches || navigator.standalone === true,
    ios: () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1),
    /** 'prompt' (the browser can install), 'ios' (Share → Add to Home Screen) or '' (installed or not possible). */
    installable() {
      if (webApp.standalone()) return '';
      if (webApp.prompt) return 'prompt';
      return webApp.ios() && 'serviceWorker' in navigator ? 'ios' : '';
    },
    async install() {
      if (webApp.prompt) {
        const prompt = webApp.prompt;
        webApp.prompt = null;
        await prompt.prompt();
        const choice = await prompt.userChoice.catch(() => null);
        window.dispatchEvent(new CustomEvent('simplify:installable'));
        return choice?.outcome === 'accepted';
      }
      if (webApp.installable() === 'ios') await window.Simplify.confirm({ title: t('home_screen_title'), message: t('home_screen_message'), confirmLabel: t('ok'), cancelLabel: t('close') });
      return false;
    },
  };
  window.SimplifyApp = webApp;
  if (!frameKind && window.top === window) {
    // Installed: the app draws up to the screen edges and keeps clear of the notch and home bar itself.
    if (webApp.standalone()) {
      root.classList.add('ps-standalone');
      const viewport = document.querySelector('meta[name="viewport"]');
      if (viewport && !/viewport-fit/.test(viewport.content)) viewport.content += ', viewport-fit=cover';
    }
    window.addEventListener('beforeinstallprompt', (event) => {
      event.preventDefault();
      webApp.prompt = event;
      window.dispatchEvent(new CustomEvent('simplify:installable'));
    });
    window.addEventListener('appinstalled', () => {
      webApp.prompt = null;
      window.dispatchEvent(new CustomEvent('simplify:installable'));
    });
    const worker = options.web_app;
    if (worker?.worker && 'serviceWorker' in navigator && window.isSecureContext) {
      const register = () => navigator.serviceWorker.register(worker.worker, { scope: worker.scope || '/' }).catch(() => {});
      if (document.readyState === 'complete') register();
      else window.addEventListener('load', register, { once: true });
    }
  }

  apply();
  forwardNotifications();
})();
