/* Simplify dashboard (Alpine component). Server data arrives through Livewire ($wire) calls;
   everything else is local UI state. Dialogs, toasts and tooltips come from core.js (window.Simplify). */
(() => {
  /** Returned by run() when an action was skipped (busy) or failed (already reported). */
  const SKIPPED = Symbol('skipped');
  const MINECRAFT_VERSION = /(?:^|[^0-9])((?:1\.[0-9]+(?:\.[0-9]+)?|2[6-9]\.[0-9]+(?:\.[0-9]+)?))(?![0-9])/;
  const OVERVIEW_DEFAULTS = { showMotd: false, largeIcon: 'server', largeName: 'alias', groupByNode: 'auto', showCpu: true, showRam: true, quickActions: 'hover', minecraftAlwaysGreen: true, fullWidth: false, serverFullWidth: false, serverBiggerConsole: false, serverConsoleLog: false, showGroupsButton: true, columns: 'auto', cardWidth: 'normal', serverScope: 'all', halloween: true };
  const OVERVIEW_SWITCHES = ['showMotd', 'showCpu', 'showRam', 'minecraftAlwaysGreen', 'fullWidth', 'serverFullWidth', 'serverBiggerConsole', 'serverConsoleLog', 'showGroupsButton', 'halloween'];
  // Overview settings that pick one of a fixed set of values.
  const OVERVIEW_CHOICES = { largeIcon: ['server', 'egg'], largeName: ['alias', 'name'], groupByNode: ['auto', 'always', 'off'], quickActions: ['off', 'on', 'hover'], columns: ['auto', '2', '3', '4', '5', '6'], cardWidth: ['narrow', 'normal', 'wide'], serverScope: ['all', 'mine', 'others'] };
  const OVERVIEW_VIEWS = ['grid', 'compact', 'list'];
  // Own server groups in the overview: at most this many groups, names at most this long.
  const GROUP_LIMIT = 30;
  const GROUP_NAME_LENGTH = 40;
  // Server order for the overview, and per group (Overview follows the overview's order, Manual uses the arrows).
  const SERVER_ORDERS = [{ key: 'name', label: 'Name' }, { key: 'created', label: 'Created' }, { key: 'port', label: 'Port' }];
  const GROUP_ORDERS = [{ key: 'overview', label: 'Same as the overview' }, { key: 'manual', label: 'My own order' }, ...SERVER_ORDERS.map((o) => ({ ...o, label: { name: 'Name (A to Z)', created: 'Oldest first', port: 'Port (lowest first)' }[o.key] }))];
  const POWER_LABELS = { start: 'Start', stop: 'Stop', restart: 'Restart', kill: 'Force stop' };
  // Phones: the sections that get their own tab next to Console (the rest sit under More), and the console's quick commands.
  const PHONE_SECTIONS = ['players', 'files'];
  const QUICK_COMMANDS = ['list', 'say ', 'time set day', 'weather clear', 'save-all'];
  // core.js (loaded in <head> on every panel page) owns the contrast calculation.
  const accentInk = (color) => window.SimplifyTheme.accentInk(color);
  const seconds = (value, fallback, min) => Math.max(min, Number.isFinite(Number(value)) && Number(value) > 0 ? Number(value) : fallback) * 1000;

  window.pelicanSimplify = function (wire, boot) {
    const performance = boot.performance || {};
    return {
      boot,
      wire,
      server: boot.server,
      intervals: {
        overview: seconds(performance.overview_seconds, 30, 5),
        server: seconds(performance.server_seconds, 20, 5),
        restart: seconds(performance.restart_seconds, 3, 2),
      },
      // Overview
      overviewDockObserver: null,
      appearanceMenu: false,
      notifyMenu: false,
      noticeHistory: [],
      panelNotices: [],
      // Every Pelican notification (read ones too), loaded a page at a time when the bell or the full list opens.
      panelUnread: 0,
      feed: { items: [], page: 0, more: false, loading: false, loaded: false },
      // Notifications that "Clear read" archived: a closed section of the full list, loaded when it opens.
      archive: { open: false, items: [], page: 0, more: false, loading: false, loaded: false, any: false },
      // "What's new" of an update: three lines, or every line once opened (by notification id).
      openNotes: {},
      announcements: [],
      // App notifications on this device (Web Push): endpoint of this browser's subscription, the account's devices and choices.
      push: { checked: false, endpoint: null, settings: null, busy: false, error: '', optInHidden: false },
      incomingNotice: null,
      incomingShown: false,
      incomingQueue: [],
      adminMenu: false,
      hostMode: false,
      hostPage: null,
      hostSection: null,
      hostServersOpen: false,
      hostServer: null,
      statusBarVisible: false,
      embedded: /[?&]simplify_embed=/.test(globalThis.location?.search || ''),
      // The console alone, in its own browser window (opened from the console's title bar).
      consoleWindow: /[?&]simplify_console=/.test(globalThis.location?.search || ''),
      consolePeek: false,
      // "Explain this error": null, or { loading, error, answer } while its popup is open.
      explain: null,
      consoleFocus: false,
      // Installation view: lines and completion seen on this page, a clock for the elapsed time, the ready banner.
      installLines: 0,
      installCompleted: false,
      installNow: Date.now(),
      installReady: false,
      installNotify: false,
      installMenu: false,
      // The Minecraft EULA still needs accepting: after an installation (from finishInstall) or when a start stopped on it.
      eulaNeeded: false,
      infoMenu: false,
      adminPage: null,
      overviewCompact: false,
      phone: false,
      sheetDrag: null,
      overviewPreferences: { ...OVERVIEW_DEFAULTS },
      overviewGroups: [],
      overviewGroupsTimer: null,
      overviewServerOrder: 'name',
      groupsMenu: false,
      groupEditing: null,
      powerConfirmation: null,
      healthExpanded: false,
      healthOpenNodes: [],
      hostMetrics: [
        { key: 'cpu', label: 'CPU', icon: 'cpu' },
        { key: 'memory', label: 'RAM', icon: 'ram' },
        { key: 'disk', label: 'Storage', icon: 'disk' },
        { key: 'network', label: 'Network', icon: 'traffic' },
      ],
      overviewPowerBusy: null,
      overviewNetwork: {},
      overviewHistory: {},
      overviewRefreshing: false,
      gameFilter: 'all',
      nodeFilter: 'all',
      nodeSearch: '',
      overviewView: typeof innerWidth !== 'undefined' && innerWidth <= 600 ? 'list' : 'grid',
      // Shell
      accountMenu: false,
      createMenu: false,
      phoneMore: false,
      PHONE_SECTIONS,
      SERVER_ORDERS,
      GROUP_ORDERS,
      QUICK_COMMANDS,
      navMenu: false,
      theme: 'system',
      themeDark: false,
      themeForced: false,
      themeListener: null,
      installable: '',
      installListener: null,
      pluginActionListener: null,
      noticeListener: null,
      started: false,
      destroyed: false,
      busy: false,
      notice: '',
      modal: null,
      loading: false,
      closingCreator: false,
      modalRequest: 0,
      createGame: 'server',
      settingsSection: 'settings',
      settingsChanged: false,
      // Server page
      aliasDraft: '',
      sftpOpen: false,
      fileEditorPath: '',
      fileEditorRevision: null,
      fileEditorUrl: null,
      fileEditorDirty: false,
      fileEditorSaving: false,
      fileEditorChecking: false,
      fileEditorExternal: false,
      uploadedBackgrounds: boot.uploaded_backgrounds || [],
      backgroundDraft: 'auto',
      colorMenu: false,
      colorDraft: '#878787',
      colorMenuStyle: {},
      networkRates: { rx_per_second: null, tx_per_second: null },
      connected: false,
      ws: null,
      reconnect: null,
      reconnectDelay: 0,
      eulaPrompted: false,
      refreshTimer: null,
      pendingTimer: null,
      refreshingInfo: false,
      logs: [],
      command: '',
      history: [],
      historyIndex: -1,
      status: boot.server?.status || 'offline',
      stats: { cpu_absolute: null, memory_bytes: null, disk_bytes: null },
      series: { cpu: [], ram: [], disk: [], network: [] },
      metrics: [
        { key: 'cpu', label: 'CPU load', icon: 'cpu' },
        { key: 'ram', label: 'Memory', icon: 'ram' },
        { key: 'disk', label: 'Storage', icon: 'disk' },
        { key: 'network', label: 'Network usage', icon: 'traffic' },
      ],
      widgets: [
        { key: 'settings', title: 'Game settings', description: 'Gameplay, MOTD & server appearance', icon: 'settings' },
        { key: 'players', title: 'Player management', description: 'Whitelist, operators & moderation', icon: 'players' },
        { key: 'plugins', title: 'Plugins', description: 'Install and manage server plugins', icon: 'plugins' },
        { key: 'mods', title: 'Mods', description: 'Install and manage server mods', icon: 'mods' },
        { key: 'files', title: 'Files', description: 'Browse, upload & connect with SFTP', icon: 'files' },
        { key: 'backups', title: 'Backups', description: 'Recovery points & automatic backups', icon: 'backups' },
        { key: 'schedules', title: 'Scheduled tasks', description: 'Planned restarts & console commands', icon: 'clock' },
        { key: 'software', title: 'Server software', description: 'Minecraft version, loader & modpack', icon: 'packages' },
        { key: 'connection', title: 'Network', description: 'Server address & subdomain', icon: 'link' },
        { key: 'performance', title: 'Performance', description: 'CPU, memory & storage limits', icon: 'cpu' },
        { key: 'web', title: 'Website', description: 'Visibility & website presentation', icon: 'web' },
        { key: 'activity', title: 'Activity log', description: 'Review recent server management actions', icon: 'activity' },
        { key: 'advanced', title: 'Advanced', description: 'Runtime details & server management', icon: 'advanced' },
      ],
      settings: {},
      settingsTab: 'general',
      name: boot.server?.name || '',
      playerName: '',
      playerLists: {},
      website: {},
      packageQuery: '',
      packageVersion: '',
      packageResults: [],
      installed: {},
      advanced: {},
      software: { server_type: 'vanilla', egg_id: '', minecraft_version: '', modpack_project: '', modpack_version: '' },
      softwareConfirm: '',
      deleteConfirm: '',
      requestedSubdomain: '',
      packQuery: '',
      packs: [],
      packReleases: {},
      backupSettings: { ...boot.server?.backup_options },
      backups: { items: [] },
      restartMinutes: 15,

      async init() {
        if (this.started) return;
        this.started = true;
        this.applyScale();
        // Measure again once the page has its content.
        this.$nextTick?.(() => this.applyScale());
        this.widgets = this.widgets.filter((w) => {
          const feature = { performance: 'resources', plugins: 'packages', mods: 'packages', web: 'web' }[w.key] || w.key;
          return feature === 'web' ? this.boot.web : this.boot.features?.[feature] !== false;
        });
        if (this.consoleWindow && this.server && typeof document !== 'undefined') document.title = (this.server.alias || this.server.name) + ' · Console';
        this.restoreOverviewView();
        this.restoreOverviewPreferences();
        this.consoleFocus = this.overviewPreferences.serverBiggerConsole;
        this.consolePeek = this.overviewPreferences.serverConsoleLog;
        this.restoreOverviewGroups();
        this.restoreHostMode();
        this.initTheme();
        this.initNotices();
        this.applyFavicon();
        this.enhanceCopyright();
        if (this.$nextTick) this.$nextTick(() => this.initOverviewDock());
        if (this.$nextTick) this.$nextTick(() => this.openFromLink());
        if (!this.server) {
          setTimeout(() => this.releaseBats(), 600);
          this.recordOverview(this.boot.servers);
          this.refreshTimer = setInterval(() => this.modal || this.refreshOverview(), this.intervals.overview);
          return;
        }
        this.fillSettings();
        // Opened halfway through an installation: show what already happened.
        if (this.server.install_log?.length) {
          this.logs = [...this.server.install_log];
          this.installLines = this.logs.length;
          this.$nextTick?.(() => this.renderLogs());
        }
        await this.connect();
        if (this.destroyed) return;
        this.startServerTimers();
      },
      /** Links from notifications: ?open=<workspace> on a server page, ?admin=<path> for an admin page. */
      openFromLink() {
        let params;
        try {
          params = new URLSearchParams(location.search);
        } catch {
          return;
        }
        const admin = params.get('admin');
        const open = params.get('open');
        if (admin && admin.startsWith('/')) {
          const known = this.adminPages().flatMap((group) => group.items).find((item) => new URL(item.url, location.origin).pathname === admin);
          this.openAdminPage(known || { key: 'link', label: 'Admin', url: admin });
        } else if (open && this.server && this.widgets.some((widget) => widget.key === open)) {
          this.open(open);
        } else return;
        try {
          params.delete('admin');
          params.delete('open');
          history.replaceState(null, '', location.pathname + (params.toString() ? '?' + params : ''));
        } catch {}
      },
      startServerTimers() {
        // The elapsed time on the installation view.
        this.installTimer = setInterval(() => this.serverSetup() === 'installing' && (this.installNow = Date.now()), 1000);
        // A restart that is due is followed closely; otherwise the page refreshes at the normal pace.
        this.pendingTimer = setInterval(() => this.restartDue() && this.refreshServerInfo(), this.intervals.restart);
        this.refreshTimer = setInterval(() => this.modal || this.refreshServerInfo(), this.intervals.server);
        // While Pelican installs the server, check every 5 seconds so it unlocks right away.
        this.setupTimer = setInterval(() => this.serverSetup() === 'installing' && this.refreshServerInfo(), 5000);
      },
      stopServerTimers() {
        clearInterval(this.refreshTimer);
        clearInterval(this.pendingTimer);
        clearInterval(this.setupTimer);
        clearInterval(this.installTimer);
      },
      restartDue() {
        const pending = this.server?.pending;
        if (!pending?.restart_at && !pending?.restart_requested) return false;
        return !!pending.restart_requested || Number(pending.restart_at) * 1000 <= Date.now();
      },
      destroy() {
        this.overviewDockObserver?.disconnect();
        this.restoreFavicon();
        this.destroyed = true;
        window.removeEventListener?.('simplify:theme', this.themeListener);
        window.removeEventListener?.('simplify:notice', this.noticeListener);
        window.removeEventListener?.('simplify:installable', this.installListener);
        window.removeEventListener?.('message', this.pluginActionListener);
        clearTimeout(this.pluginActionTimer);
        clearInterval(this.noticeTimer);
        clearTimeout(this.incomingTimer);
        clearTimeout(this.reconnect);
        this.stopServerTimers();
        if (this.ws) {
          this.ws.onclose = null;
          this.ws.close();
        }
      },

      /* Halloween ---------------------------------------------------------------------------- */
      /** 25 October to 2 November, every year. */
      halloweenSeason(date = new Date()) {
        const month = date.getMonth(), day = date.getDate();
        return (month === 9 && day >= 25) || (month === 10 && day <= 2);
      },
      /** The Halloween touches show in season, unless the panel or this person turned them off. */
      halloween(date = new Date()) {
        return this.boot.appearance?.seasonal !== false && this.overviewPreferences.halloween !== false && this.halloweenSeason(date);
      },
      /** A few bats leave one server card when the overview opens, once per visit. */
      releaseBats() {
        if (this.server || !this.halloween() || typeof document === 'undefined' || !document.body.animate) return;
        if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;
        try {
          if (sessionStorage.getItem('pelicanSimplify.bats')) return;
          sessionStorage.setItem('pelicanSimplify.bats', '1');
        } catch {}
        const card = [...this.$root.querySelectorAll('.ps-server-card')].map((el) => el.getBoundingClientRect()).find((r) => r.width > 80 && r.top >= 0 && r.bottom <= innerHeight);
        if (!card) return;
        const ink = getComputedStyle(this.$root).getPropertyValue('--ps-ink').trim();
        [[0, .62, -1], [180, .5, .3], [340, .72, 1]].forEach(([delay, across, side], i) => {
          const bat = document.createElement('span');
          bat.className = 'ps-hw-bat';
          bat.setAttribute('aria-hidden', 'true');
          bat.style.color = ink;
          bat.innerHTML = '<svg viewBox="0 0 44 22"><path d="M22 6c1-2 2-3 2-3l1 3c2 0 3 1 3 2 3-3 8-5 14-4-3 2-4 5-4 8-2-2-5-2-7 0-1-2-3-3-5-2l-2 6-2-6c-2-1-4 0-5 2-2-2-5-2-7 0 0-3-1-6-4-8 6-1 11 1 14 4 0-1 1-2 3-2l1-3s1 1 2 3z"/></svg>';
          document.body.appendChild(bat);
          const x = card.left + card.width * across - 20, y = card.top + card.height * .35;
          const dx = side * (120 + i * 40) + 60, dy = -(y + 60);
          bat.animate([
            { transform: `translate(${x}px, ${y}px) scale(.4)`, opacity: 0 },
            { transform: `translate(${x + dx * .15}px, ${y - 40}px) scale(.9)`, opacity: .85, offset: .15 },
            { transform: `translate(${x + dx * .55}px, ${y + dy * .5 + 20}px)`, opacity: .85, offset: .55 },
            { transform: `translate(${x + dx}px, ${y + dy}px) scale(.8)`, opacity: 0 },
          ], { duration: 2400 + i * 250, delay: delay + 300, easing: 'cubic-bezier(.3, .6, .4, 1)', fill: 'both' }).finished.then(() => bat.remove(), () => bat.remove());
        });
      },

      /* Colours ---------------------------------------------------------------------------- */
      creationAccent(game) {
        if (game === 'minecraft' && this.overviewPreferences.minecraftAlwaysGreen) {
          const color = this.themeDark ? '#74d69a' : '#16803d';
          return { '--ps-accent': color, '--ps-on-accent': accentInk(color) };
        }
        return this.accentStyle('overview');
      },
      accentStyle(id) {
        // A colour on one of your own groups wins for its servers (and its heading), over the common and the server's own colour.
        const group = String(id).startsWith('group-') ? this.overviewGroups.find((g) => 'group-' + g.id === id) : this.serverGroup(id);
        if (group?.color) return { '--ps-accent': group.color, '--ps-on-accent': accentInk(group.color) };
        if (String(id).startsWith('group-')) return {};
        const appearance = this.boot.appearance || {};
        const common = appearance.accent_color;
        // Halloween turns the overview's neutral accent orange; a panel accent of your own stays.
        if (id === 'overview' && this.halloween() && (common || '#878787').toLowerCase() === '#878787')
          return { '--ps-accent': this.themeDark ? '#f0a35a' : '#c2621a', '--ps-on-accent': this.themeDark ? '#111118' : '#ffffff' };
        if (/^#[0-9a-f]{6}$/i.test(common || '') && (id === 'overview' || appearance.individual_colors === false))
          return { '--ps-accent': common, '--ps-on-accent': accentInk(common) };
        if (id === 'overview') return { '--ps-accent': this.themeDark ? '#c0c0c5' : '#55555f', '--ps-on-accent': this.themeDark ? '#111118' : '#ffffff' };
        const color = (this.server?.uuid === id ? this.server : this.boot.servers?.find((s) => s.uuid === id))?.color;
        if (typeof color === 'string' && /^#[0-9a-f]{6}$/i.test(color)) return { '--ps-accent': color, '--ps-on-accent': accentInk(color) };
        let hash = 2166136261;
        for (const c of String(id)) hash = Math.imul(hash ^ c.charCodeAt(0), 16777619) >>> 0;
        return { '--ps-accent': `hsl(${hash % 360} 68% 76%)`, '--ps-on-accent': '#111118' };
      },
      /** Panel name gradient: the common accent, else the page accent (blue on an uncoloured overview). */
      /**
       * Interface size: normal size whenever it fits. Only when the page would get wider than the
       * window is it scaled down, just enough to fit (never below 75%). Phones keep their own layout.
       * Inside the host mode pane nothing extra is applied: the frame is scaled with the page around it.
       */
      uiScale: 1,
      applyScale() {
        if (typeof window === 'undefined' || !this.$root?.style) return;
        const root = this.$root;
        root.style.zoom = '';
        let scale = 1;
        if (!this.embedded && window.innerWidth > 900 && root.scrollWidth > root.clientWidth + 1)
          scale = Math.max(0.75, Math.floor((root.clientWidth / root.scrollWidth) * 100) / 100);
        this.uiScale = scale;
        root.style.zoom = scale === 1 ? '' : String(scale);
        document.documentElement.style.setProperty('--ps-ui-scale', String(scale));
      },
      /** Shows the status bar while the server card is scrolled out of view. */
      watchServerInfo(el) {
        if (typeof IntersectionObserver !== 'function') return;
        new IntersectionObserver(([entry]) => {
          this.statusBarVisible = !entry.isIntersecting && entry.boundingClientRect.bottom < 0;
        }).observe(el);
      },
      /** The section menu and the console (or open section) share the height left on the screen: what sits
       *  above them is measured, and whatever still ends up below (footer, page padding) is taken off too,
       *  so the page never scrolls just for the work area. Phones keep the console the height of the screen. */
      fitWorkspace(el) {
        const fit = () => {
          if (!this.server || typeof window === 'undefined') return;
          const scale = this.uiScale || 1;
          const bar = this.$root.querySelector('.ps-phone-sections');
          const barHeight = bar && getComputedStyle(bar).position === 'fixed' ? bar.offsetHeight : 0;
          const top = el.getBoundingClientRect().top + window.scrollY;
          const min = window.innerHeight < 500 ? 120 : window.innerWidth <= 600 ? 220 : 300;
          let height = Math.max(min, Math.floor((window.innerHeight - top - barHeight) / scale - (this.embedded ? 0 : 16)));
          el.style.setProperty('--ps-work-height', height + 'px');
          for (let pass = 0; pass < 2; pass++) {
            const over = Math.ceil(document.documentElement.scrollHeight - window.innerHeight);
            const next = Math.max(min, height - over);
            if (over <= 0 || next === height) break;
            height = next;
            el.style.setProperty('--ps-work-height', height + 'px');
          }
        };
        this.fitWorkspaceNow = fit;
        this.$nextTick?.(fit);
        document.fonts?.ready?.then(fit); // the header is measured again once the web font has swapped in
        window.addEventListener('resize', fit);
        if (typeof ResizeObserver === 'function') {
          const watch = new ResizeObserver(fit);
          for (const part of ['.ps-server-info', '.ps-server-stats', '.ps-pending']) {
            const node = this.$root.querySelector(part);
            if (node) watch.observe(node);
          }
        }
      },
      /** Measures the work area again after a layout change (console size, workspace open or closed). */
      refitWorkspace() {
        this.$nextTick?.(() => this.fitWorkspaceNow?.());
      },
      /** The power button that fits the state: Stop while the server runs, Start otherwise. */
      mainPower() {
        return ['running', 'starting'].includes(this.status) ? { action: 'stop', label: 'Stop', icon: 'stop' } : { action: 'start', label: 'Start', icon: 'play' };
      },
      /** "Paper 1.21.4", "Valheim" or "Not reported" for the line under the server name. */
      softwareLabel() {
        const software = this.server?.software || 'Not reported';
        if (!this.server?.minecraft) return software;
        const version = this.minecraftVersion();
        return version === 'Not reported' ? software : software + ' ' + version;
      },
      modpackLabel() {
        const pack = this.server?.pack || {};
        return (pack.modpack_name || '') + (pack.modpack_version ? ' ' + pack.modpack_version : '');
      },
      /** The console in a window of its own: the same page with only the console showing. */
      openConsoleWindow() {
        const url = new URL(location.href);
        url.searchParams.set('simplify_console', '1');
        let popup = null;
        try {
          popup = window.open(url.toString(), 'simplify-console-' + (this.server?.uuid || ''), 'popup,width=960,height=640');
        } catch {}
        if (!popup) this.toast('The browser blocked the new window. Allow pop-ups for this panel to open the console separately.', 'warn');
      },
      openHostServer(s) {
        this.hostServer = s;
        this.hostServersOpen = false;
      },
      closeHostServer() {
        this.hostServer = null;
      },
      brand() {
        return this.boot.appearance?.brand || {};
      },
      brandStyle() {
        const common = this.boot.appearance?.accent_color;
        if (/^#[0-9a-f]{6}$/i.test(common || '')) return { '--ps-brand': common };
        return this.server ? {} : { '--ps-brand': this.themeDark ? '#60a5fa' : '#2563eb' };
      },
      currentAccentHex() {
        const accent = this.accentStyle(this.server?.uuid || 'overview')['--ps-accent'];
        if (/^#[0-9a-f]{6}$/i.test(accent)) return accent;
        const match = /hsl\((\d+) 42% (\d+)%\)/.exec(accent);
        if (!match) return '#878787';
        const h = +match[1] / 360, s = 0.42, l = +match[2] / 100;
        const channel = (n) => {
          const k = (n + h * 12) % 12, a = s * Math.min(l, 1 - l);
          return Math.round(255 * (l - a * Math.max(-1, Math.min(k - 3, 9 - k, 1)))).toString(16).padStart(2, '0');
        };
        return '#' + channel(0) + channel(8) + channel(4);
      },

      /* Theme, notices and page chrome --------------------------------------------------------- */
      initTheme() {
        this.theme = window.SimplifyTheme?.preference() || 'system';
        this.themeListener = (e) => {
          this.theme = e.detail.preference;
          this.themeDark = e.detail.dark;
        };
        window.addEventListener('simplify:theme', this.themeListener);
        this.applyTheme();
        // "Install app" in the panel menu on phones and tablets, only while the browser can add the panel to the home screen.
        this.pluginActionListener = (event) => {
          if (event.origin === location.origin && event.data?.type === 'simplify:plugin-action') this.pluginActionMessage(event.data);
        };
        window.addEventListener('message', this.pluginActionListener);
        this.installListener = () => { this.installable = matchMedia('(pointer: coarse)').matches ? window.SimplifyApp?.installable() || '' : ''; };
        window.addEventListener('simplify:installable', this.installListener);
        if (!this.embedded) this.installListener();
      },
      async installApp() {
        this.navMenu = false;
        await window.SimplifyApp?.install();
        this.installListener?.();
      },
      setTheme(value) {
        window.SimplifyTheme?.set(value);
        this.theme = value;
        this.applyTheme();
      },
      applyTheme() {
        this.themeDark = window.SimplifyTheme?.dark() ?? false;
        // A panel theme that only comes in light or dark (such as Catppuccin) hides the theme choice.
        this.themeForced = !!window.SimplifyTheme?.forced?.();
        this.$root?.querySelectorAll?.('iframe').forEach((frame) => window.SimplifyTheme?.frame(frame, frame.dataset.psFrame, this.currentAccentHex()));
      },
      initNotices() {
        this.noticeHistory = window.Simplify?.history?.() || [];
        this.panelNotices = Array.isArray(this.boot.panel_notifications) ? this.boot.panel_notifications : [];
        this.announcements = Array.isArray(this.boot.announcements) ? this.boot.announcements : [];
        this.panelUnread = Number.isFinite(Number(this.boot.panel_unread)) ? Number(this.boot.panel_unread) : this.panelNotices.length;
        this.archive.any = !!this.boot.notifications_archived;
        this.checkPush();
        this.seenNoticeKeys = new Set(this.panelNotices.map((n) => n.key));
        // New Pelican notifications drop down from the top; one small query, skipped while the tab is hidden.
        if (typeof wire.panelNotifications === 'function') this.noticeTimer = setInterval(() => this.pollPanelNotices(), Math.max(15000, Number(this.intervals?.overview) || 30000));
        this.noticeListener = (event) => (this.noticeHistory = [event.detail, ...this.noticeHistory].slice(0, 20));
        window.addEventListener?.('simplify:notice', this.noticeListener);
      },
      toast(message, tone = 'info') {
        this.notice = String(message || '');
        window.Simplify?.toast(this.notice, tone);
      },
      ask(options) {
        return window.Simplify?.confirm ? window.Simplify.confirm(options) : Promise.resolve(false);
      },
      /** Active alerts for the bell menu: pending changes, host health and plugin updates. */
      notifications() {
        const items = [];
        const pending = this.server?.pending;
        if (pending) {
          items.push({
            id: 'pending',
            tone: pending.error ? 'danger' : 'warn',
            title: pending.error ? 'Changes need attention' : 'Changes are waiting for a restart',
            text: pending.error || (pending.restart_at ? 'A restart is scheduled to apply your changes.' : 'Restart the server to apply your saved changes.'),
            action: this.showWidget('restart') && !pending.restart_requested ? { label: 'Restart now', url: '#', run: () => this.restartChanges(0) } : null,
          });
        }
        const health = this.boot.host_health;
        if (!this.server && ['warning', 'critical'].includes(health?.state)) {
          items.push({ id: 'health', tone: health.state === 'critical' ? 'danger' : 'warn', title: 'Host health: ' + (health.label || 'Attention needed'), text: (health.available ?? 0) + ' of ' + (health.hosts ?? 0) + ' hosts reporting.', action: null });
        }
        // Announcements (also the Panel's update announcements); unpinned ones stay here until they end.
        for (const item of this.announcements) {
          items.push({ id: 'announcement-' + item.key, key: item.key, tone: item.tone, title: item.title, text: item.text, time: [item.from, item.time].filter(Boolean).join(' · '), action: item.action || null, announcement: true, dismissed: !!item.dismissed, pinned: !!item.pinned, unread: item.unread ?? !item.dismissed, archived: !!item.archived, notes: Array.isArray(item.notes) ? item.notes : [], notesUrl: item.notes_url || null });
        }
        for (const notice of this.boot.notices || []) items.push({ action: null, ...notice });
        // Pelican's notifications: the full history once loaded, until then the unread ones from the page load.
        for (const notice of this.feed.loaded ? this.feed.items : this.panelNotices) items.push({ action: null, unread: true, ...notice, pelican: true });
        return items;
      },
      /** Announcements an admin pinned to everyone's notifications: always on top, apart from the rest. */
      pinnedNotifications() {
        return this.notifications().filter((item) => item.pinned);
      },
      otherNotifications() {
        return this.notifications().filter((item) => !item.pinned && !item.archived);
      },
      /** What "Clear read" moved away: archived announcements and the loaded pages of archived Pelican notifications. */
      archivedNotifications() {
        const announcements = this.notifications().filter((item) => item.archived);
        return [...announcements, ...this.archive.items.map((notice) => ({ action: null, ...notice, unread: false, pelican: true }))];
      },
      hasArchive() {
        return this.archive.any || this.notifications().some((item) => item.archived);
      },
      /** "Clear read" shows when the list has read notifications or announcements that are not pinned. */
      canClearRead() {
        return this.otherNotifications().some((item) => (item.pelican || item.announcement) && item.unread === false);
      },
      async toggleArchive() {
        this.archive.open = !this.archive.open;
        if (this.archive.open && !this.archive.loaded) await this.loadArchivePage(true);
      },
      async loadArchivePage(reset = false) {
        if (typeof wire.notificationPage !== 'function' || (this.archive.loading && !reset)) return;
        const page = reset ? 1 : this.archive.page + 1;
        this.archive.loading = true;
        try {
          const result = await wire.notificationPage(page, true);
          if (!Array.isArray(result?.items)) return;
          const known = new Set(reset ? [] : this.archive.items.map((n) => n.key));
          this.archive = { ...this.archive, items: reset ? result.items : [...this.archive.items, ...result.items.filter((n) => !known.has(n.key))], page, more: !!result.more, loaded: true };
        } catch {
          this.toast('Archived notifications could not be loaded.', 'warn');
        } finally {
          this.archive.loading = false;
        }
      },
      /** Moves every read notification and announcement (not an admin's pinned ones) to Archived, for this account only. */
      async clearReadNotifications() {
        const before = { announcements: this.announcements, feed: this.feed, archive: this.archive };
        const read = this.feed.items.filter((n) => n.unread === false);
        this.announcements = this.announcements.map((item) => (!item.pinned && (item.unread ?? !item.dismissed) === false ? { ...item, archived: true } : item));
        this.feed = { ...this.feed, items: this.feed.items.filter((n) => n.unread !== false) };
        this.archive = { ...this.archive, any: true, items: this.archive.loaded ? [...read, ...this.archive.items] : this.archive.items };
        if (typeof wire.clearReadNotifications !== 'function') return;
        try {
          const items = await wire.clearReadNotifications();
          if (Array.isArray(items)) this.announcements = items;
          // The next page of current notifications may hold more read ones; start the list again.
          if (this.feed.more) this.loadNotificationPage(true);
        } catch {
          Object.assign(this, before);
          this.toast('Read notifications could not be cleared.', 'warn');
        }
      },
      /** The bell's count: alerts, unread announcements and every unread Pelican notification (also the ones not loaded). */
      unreadNotifications() {
        return this.notifications().filter((item) => !item.pelican && (item.announcement ? item.unread : true)).length + this.panelUnread;
      },
      /** "What's new" lines to show: the first three, all of them once opened. */
      noteLines(item) {
        return this.openNotes[item.id] ? item.notes : item.notes.slice(0, 3);
      },
      toggleNotes(item) {
        this.openNotes = { ...this.openNotes, [item.id]: !this.openNotes[item.id] };
      },
      /** Loads the first (reset) or next page of Pelican's notifications, read ones included. */
      async loadNotificationPage(reset = false) {
        if (typeof wire.notificationPage !== 'function' || (this.feed.loading && !reset)) return;
        const page = reset ? 1 : this.feed.page + 1;
        this.feed.loading = true;
        try {
          const result = await wire.notificationPage(page);
          if (!Array.isArray(result?.items)) return;
          const known = new Set(reset ? [] : this.feed.items.map((n) => n.key));
          this.feed = { items: reset ? result.items : [...this.feed.items, ...result.items.filter((n) => !known.has(n.key))], page, more: !!result.more, loading: false, loaded: true };
          this.panelUnread = Number(result.unread) || 0;
        } catch {
          if (!reset) this.toast('Older notifications could not be loaded.', 'warn');
        } finally {
          this.feed.loading = false;
        }
      },
      /** Every notification in a popup (a full-screen sheet on phones), with "Show more" for older ones. */
      openAllNotifications() {
        this.archive = { ...this.archive, open: false, items: [], page: 0, more: false, loaded: false };
        this.openPopup('inbox');
        this.refreshAnnouncements();
        this.loadNotificationPage(true);
      },
      /** Root admins: the announcement form, straight from the notifications; closing it comes back to the list. */
      announcementsPage() {
        return this.adminPages().flatMap((group) => group.items).find((item) => item.key === 'manageannouncements') || null;
      },
      newAnnouncement() {
        const page = this.announcementsPage();
        if (!page) return;
        const url = new URL(page.url, location.origin);
        url.searchParams.set('action', 'addAnnouncement');
        try {
          url.searchParams.set('tz', Intl.DateTimeFormat().resolvedOptions().timeZone || '');
        } catch {}
        this.openAdminPage({ ...page, url: url.pathname + url.search, returnTo: this.modal === 'inbox' ? 'inbox' : null });
      },
      /** Marks everything read on the server: all Pelican notifications and announcements, not only the loaded ones. */
      async markAllNotificationsRead() {
        const before = { announcements: this.announcements, feed: this.feed, panelNotices: this.panelNotices, panelUnread: this.panelUnread };
        this.announcements = this.announcements.map((item) => ({ ...item, unread: false }));
        this.feed = { ...this.feed, items: this.feed.items.map((n) => ({ ...n, unread: false })) };
        this.panelNotices = [];
        this.panelUnread = 0;
        if (typeof wire.markAllNotificationsRead !== 'function') return;
        try {
          const items = await wire.markAllNotificationsRead();
          if (Array.isArray(items)) this.announcements = items;
        } catch {
          Object.assign(this, before);
          this.toast('Notifications could not be marked as read.', 'warn');
        }
      },
      /** Announcement bars above the overview and server pages: live and pinned (not unpinned by this account). */
      announcementBars() {
        return this.announcements.filter((item) => !item.dismissed);
      },
      announcementMeta(item) {
        const details = [item.source === 'panel' ? 'Panel' : '', item.audience === 'admins' ? 'admins only' : '', this.announcementUntil(item)].filter(Boolean);
        // The category only as context for other details; the colour already says it.
        const category = { info: 'Info', warning: 'Warning', success: 'Success' }[item.category];
        return (details.length && category ? [category, ...details] : details).join(' · ');
      },
      /** "until Sun 26 Oct 03:00" in this browser's time zone (the panel's own time zone can differ). */
      announcementUntil(item) {
        if (!Number.isFinite(item.ends_at) || typeof Intl === 'undefined') return item.until || '';
        try {
          const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(new Date(item.ends_at * 1000)).map((p) => [p.type, p.value]));
          return 'until ' + parts.weekday + ' ' + parts.day + ' ' + parts.month + ' ' + parts.hour + ':' + parts.minute;
        } catch {
          return item.until || '';
        }
      },
      externalLink(url) {
        try {
          return new URL(url, location.origin).origin !== location.origin;
        } catch {
          return false;
        }
      },
      /** Unpins an announcement for this account (kept with the account, so every device follows); it stays under the bell. */
      dismissAnnouncement(key) {
        return this.pinAnnouncement(key, false);
      },
      /** Pins an unpinned announcement above the overview and server pages again. */
      restoreAnnouncement(key) {
        return this.pinAnnouncement(key, true);
      },
      async pinAnnouncement(key, pinned) {
        const before = this.announcements;
        this.announcements = before.map((item) => (item.key === key ? { ...item, dismissed: !pinned } : item));
        const call = pinned ? wire.restoreAnnouncement : wire.dismissAnnouncement;
        if (typeof call !== 'function') return;
        try {
          const items = await call.call(wire, key);
          if (Array.isArray(items)) this.announcements = items;
        } catch {
          this.announcements = before;
          this.toast(pinned ? 'The announcement could not be pinned.' : 'The announcement could not be unpinned.', 'warn');
        }
      },
      /** Push needs a service worker, the Push API and https; iPhones and iPads only in the installed app. */
      pushSupported() {
        return !!(globalThis.isSecureContext && globalThis.navigator?.serviceWorker && 'PushManager' in globalThis && 'Notification' in globalThis);
      },
      pushNeedsInstall() {
        const nav = globalThis.navigator || {};
        const ios = /iPad|iPhone|iPod/.test(nav.userAgent || '') || (nav.platform === 'MacIntel' && nav.maxTouchPoints > 1);
        const standalone = nav.standalone === true || !!globalThis.matchMedia?.('(display-mode: standalone)').matches;
        return ios && !standalone;
      },
      pushBlocked() {
        return globalThis.Notification?.permission === 'denied';
      },
      /** The bell offers notifications once per device, until they are on or the offer is dismissed. */
      pushOffer() {
        return this.push.checked && !this.push.endpoint && !this.push.optInHidden && !this.pushBlocked() && (this.pushSupported() || this.pushNeedsInstall());
      },
      hidePushOffer() {
        this.push.optInHidden = true;
        try {
          localStorage.setItem('pelicanSimplify.pushOffer', 'hidden');
        } catch {}
      },
      async pushRegistration() {
        const worker = globalThis.navigator.serviceWorker;
        const existing = await worker.getRegistration().catch(() => null);
        if (existing) return existing;
        // core.js registers the worker after the page load; wait for it a little.
        return Promise.race([worker.ready, new Promise((resolve) => setTimeout(() => resolve(null), 8000))]);
      },
      async checkPush() {
        try {
          this.push.optInHidden = localStorage.getItem('pelicanSimplify.pushOffer') === 'hidden';
        } catch {}
        if (!this.pushSupported()) return (this.push.checked = true);
        try {
          const registration = await globalThis.navigator.serviceWorker.getRegistration();
          const subscription = await registration?.pushManager.getSubscription();
          this.push.endpoint = subscription?.endpoint || null;
        } catch {}
        this.push.checked = true;
      },
      openNotifications() {
        this.openPopup('notifications');
        this.loadPush();
      },
      async loadPush() {
        this.push.error = '';
        await this.checkPush();
        try {
          this.push.settings = await wire.pushSettings(this.push.endpoint);
        } catch {
          this.push.error = 'Notification settings could not be loaded.';
        }
      },
      pushOn() {
        return !!this.push.endpoint && !!this.push.settings?.devices?.some((device) => device.current);
      },
      deviceLabel() {
        const agent = globalThis.navigator?.userAgent || '';
        const browser = /Edg\//.test(agent) ? 'Edge' : /Firefox\//.test(agent) ? 'Firefox' : /SamsungBrowser/.test(agent) ? 'Samsung Internet' : /Chrome\//.test(agent) ? 'Chrome' : /Safari\//.test(agent) ? 'Safari' : 'Browser';
        const system = /iPhone/.test(agent) ? 'iPhone' : /iPad/.test(agent) ? 'iPad' : /Android/.test(agent) ? 'Android' : /Windows/.test(agent) ? 'Windows' : /Mac OS X/.test(agent) ? 'Mac' : /Linux/.test(agent) ? 'Linux' : '';
        const app = globalThis.matchMedia?.('(display-mode: standalone)').matches || globalThis.navigator?.standalone === true;
        return browser + (system ? ' on ' + system : '') + (app ? ' · installed app' : '');
      },
      async enablePush() {
        if (this.push.busy) return;
        this.push.busy = true;
        this.push.error = '';
        try {
          const permission = await globalThis.Notification.requestPermission();
          if (permission !== 'granted') {
            this.push.error = permission === 'denied' ? 'Notifications are blocked for this site. Allow them in the browser\'s site settings, then try again.' : 'Notifications were not allowed.';
            return;
          }
          const registration = await this.pushRegistration();
          if (!registration) {
            this.push.error = 'The app is still starting. Reload the page and try again.';
            return;
          }
          const key = this.push.settings?.key || (await wire.pushSettings(null)).key;
          const bytes = Uint8Array.from(atob(key.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - (key.length % 4)) % 4)), (c) => c.charCodeAt(0));
          let subscription = await registration.pushManager.getSubscription();
          // A subscription made with another key (after the panel's keys changed) cannot be used.
          const current = subscription?.options?.applicationServerKey;
          if (subscription && current && !new Uint8Array(current).every((value, index) => value === bytes[index])) {
            await subscription.unsubscribe();
            subscription = null;
          }
          subscription ||= await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: bytes });
          const result = await wire.savePushSubscription(subscription.toJSON(), this.deviceLabel());
          if (!result?.ok) {
            await subscription.unsubscribe().catch(() => {});
            this.push.error = result?.message || 'Notifications could not be turned on.';
            return;
          }
          this.push.endpoint = subscription.endpoint;
          this.push.settings = { ...this.push.settings, ...result };
          this.toast('Notifications are on for this device.', 'ok');
        } catch {
          this.push.error = 'Notifications could not be turned on in this browser.';
        } finally {
          this.push.busy = false;
        }
      },
      async disablePush() {
        if (this.push.busy || !this.push.endpoint) return;
        this.push.busy = true;
        const endpoint = this.push.endpoint;
        try {
          const registration = await globalThis.navigator.serviceWorker.getRegistration();
          await (await registration?.pushManager.getSubscription())?.unsubscribe();
        } catch {}
        try {
          this.push.settings = { ...this.push.settings, ...(await wire.removePushDevice(endpoint)) };
          this.push.endpoint = null;
          this.toast('Notifications are off for this device.', 'info');
        } catch {
          this.push.error = 'Notifications could not be turned off.';
        } finally {
          this.push.busy = false;
        }
      },
      async testPush() {
        if (this.push.busy || !this.push.endpoint) return;
        this.push.busy = true;
        try {
          const result = await wire.sendTestPush(this.push.endpoint);
          this.toast(result?.message || 'Test sent.', result?.ok ? 'ok' : 'warn');
        } catch {
          this.toast('The test could not be sent.', 'warn');
        } finally {
          this.push.busy = false;
        }
      },
      async removePushDevice(id) {
        try {
          this.push.settings = { ...this.push.settings, ...(await wire.removePushDevice(id)) };
        } catch {
          this.toast('The device could not be removed.', 'warn');
        }
      },
      async setPushPreference(kind, value) {
        const before = this.push.settings?.preferences || {};
        this.push.settings = { ...this.push.settings, preferences: { ...before, [kind]: value } };
        try {
          this.push.settings.preferences = await wire.savePushPreferences({ [kind]: value });
        } catch {
          this.push.settings = { ...this.push.settings, preferences: before };
          this.toast('Your choice could not be saved.', 'warn');
        }
      },
      pushDeviceTime(device) {
        if (!device.last_sent_at) return 'No notification sent yet';
        const seconds = Math.max(0, Date.now() / 1000 - device.last_sent_at);
        const days = Math.round(seconds / 86400);
        const ago = seconds < 3600 ? Math.max(1, Math.round(seconds / 60)) + ' min ago' : seconds < 86400 ? Math.round(seconds / 3600) + ' h ago' : days === 1 ? 'yesterday' : days + ' days ago';
        return 'Last notification ' + ago;
      },
      async refreshAnnouncements() {
        if (typeof wire.announcements !== 'function') return;
        try {
          const items = await wire.announcements();
          if (Array.isArray(items)) this.announcements = items;
        } catch {}
      },
      adminPages() {
        return Array.isArray(this.boot.admin_pages) ? this.boot.admin_pages : [];
      },
      /** Host mode: an admin choice per user; otherwise automatic for multi-node panels when enabled in settings. */
      hostModeKey() {
        return 'pelicanSimplify.hostMode.' + encodeURIComponent(this.boot.user || 'default');
      },
      restoreHostMode() {
        if (!this.adminPages().length) return;
        let saved = null;
        try {
          saved = localStorage.getItem(this.hostModeKey());
        } catch {}
        this.hostMode = saved === null ? !!this.boot.host_mode_auto && this.nodeOptions().length > 1 : saved === '1';
        if (!this.hostMode) return;
        // Back from a server: reopen the section and page that were open (this browser tab only).
        let last = {};
        try {
          last = JSON.parse(sessionStorage.getItem(this.hostModeKey() + '.place') || '{}') || {};
        } catch {}
        const sections = this.hostSections();
        const section = sections.find((x) => x.key === last.section) || sections.find((x) => x.key === 'health') || sections[0];
        this.openHostSection(section);
        const page = section?.pages.find((item) => item.key === last.page);
        if (page) this.setHostPage(page);
      },
      setHostPage(item) {
        this.hostPage = item;
        this.rememberHostPlace();
      },
      rememberHostPlace() {
        try {
          sessionStorage.setItem(this.hostModeKey() + '.place', JSON.stringify({ section: this.hostSection?.key || null, page: this.hostPage?.key || null }));
        } catch {}
      },
      setHostMode(on) {
        this.hostMode = !!on && this.adminPages().length > 0;
        if (this.hostMode && !this.hostPage) this.openHostSection(this.hostSections().find((x) => x.key === 'health') || this.hostSections()[0]);
        try {
          localStorage.setItem(this.hostModeKey(), this.hostMode ? '1' : '0');
        } catch {}
      },
      /** Admin pages merged into a few colour-coded sections; pages from other plugins go to Extensions. */
      hostSections() {
        const plan = [
          { key: 'servers', label: 'Servers', icon: 'host', color: '#5fb87a', pages: ['easycreator', 'gamecreator', 'eggresource', 'mountresource'] },
          { key: 'nodes', label: 'Nodes', icon: 'monitor', color: '#5b9cf0', pages: ['noderesource', 'backuphostresource'] },
          { key: 'people', label: 'People', icon: 'players', color: '#a78bfa', pages: ['userresource', 'roleresource'] },
          { key: 'integrations', label: 'Integrations', icon: 'database', color: '#f0a35b', pages: ['databasehostresource', 'apikeyresource', 'webhookresource'] },
          { key: 'plugins', label: 'Plugins', icon: 'plugins', color: '#f472b6', pages: ['pluginresource'] },
          { key: 'panel', label: 'Panel', icon: 'gear', color: '#94a3b8', pages: ['settings', 'listlogs'] },
          { key: 'legacy', label: 'Legacy', icon: 'legacy', color: '#a8a29e', pages: ['serverresource', 'legacyplugins', 'legacyusers', 'legacyroles'] },
          { key: 'extensions', label: 'External', icon: 'plugins', color: '#2dd4bf', pages: [] },
          { key: 'health', label: 'Health', icon: 'heart', color: '#f87171', pages: ['health'] },
        ];
        const items = [...this.adminPages().flatMap((group) => group.items), ...(this.boot.host_extras || [])];
        const known = new Set(plan.flatMap((section) => section.pages));
        if (this.boot.features?.legacy_pages === false) plan.splice(plan.findIndex((section) => section.key === 'legacy'), 1);
        return plan.map((section) => ({
          ...section,
          pages: section.key === 'extensions' ? items.filter((item) => !known.has(item.key)) : section.pages.map((key) => items.find((item) => item.key === key)).filter(Boolean),
        })).filter((section) => section.pages.length);
      },
      openHostSection(section) {
        if (!section) return;
        this.hostSection = section;
        this.hostPage = section.pages[0] || null;
        this.rememberHostPlace();
        // Phones show the sections as one sideways row: keep the open one in view.
        if (this.phone) this.$nextTick?.(() => this.$root?.querySelector?.('.ps-host-rail-item[aria-current="page"]')?.scrollIntoView({ block: 'nearest', inline: 'nearest' }));
      },
      hostServerVersion(s) {
        const version = String(s.reported_version || '').trim();
        const software = String(s.software || s.game_label || '').trim();
        return version && !version.toLowerCase().includes(software.toLowerCase()) ? (software ? software + ' · ' + version : version) : version || software || '—';
      },
      hostNodes() {
        return this.nodeOptions().map((node) => ({ ...node, servers: (this.boot.servers || []).filter((s) => Number(s.node_id) === node.id) })).filter((node) => node.servers.length);
      },
      adminHealthPage() {
        return this.adminPages().flatMap((group) => group.items).find((item) => item.key === 'health') || null;
      },
      /** Latest Spatie health results (admins only); empty until the checks have run once. */
      healthChecks() {
        return this.boot.health_checks || [];
      },
      openAdminPage(item) {
        this.adminMenu = false;
        this.adminPage = item;
        this.openPopup('admin-page');
      },
      /** Opening the bell fetches Pelican's latest notifications (no polling). */
      async toggleNotifications() {
        this.notifyMenu = !this.notifyMenu;
        this.appearanceMenu = false;
        this.groupsMenu = false;
        this.accountMenu = false;
        if (this.notifyMenu) this.refreshAnnouncements();
        if (!this.notifyMenu) return;
        if (typeof wire.notificationPage === 'function') return this.loadNotificationPage(true);
        if (typeof wire.panelNotifications !== 'function') return;
        try {
          const items = await wire.panelNotifications();
          if (Array.isArray(items)) this.panelNotices = items;
        } catch {}
      },
      async pollPanelNotices() {
        if (globalThis.document?.hidden || this.destroyed || this.notifyMenu) return;
        this.refreshAnnouncements();
        try {
          const items = await wire.panelNotifications();
          if (!Array.isArray(items)) return;
          this.panelNotices = items;
          const fresh = items.filter((n) => !this.seenNoticeKeys.has(n.key));
          fresh.forEach((n) => this.seenNoticeKeys.add(n.key));
          if (fresh.length) {
            this.panelUnread += fresh.length;
            if (this.feed.loaded) this.feed = { ...this.feed, items: [...fresh.map((n) => ({ ...n, unread: true })), ...this.feed.items.filter((n) => !fresh.some((f) => f.key === n.key))] };
          }
          // A finished plugin update needs a fresh page load; it happens once the notice has been shown.
          if (fresh.some((n) => /^Plugin (updated|installed|uninstalled)$/.test(n.title || ''))) this.reloadAfterNotice = true;
          this.incomingQueue.push(...fresh.reverse());
          this.showIncomingNotice();
        } catch {}
      },
      showIncomingNotice() {
        if (this.incomingNotice || !this.incomingQueue.length) return;
        this.incomingNotice = this.incomingQueue.shift();
        this.incomingShown = true;
        this.holdIncomingNotice();
      },
      holdIncomingNotice(delay = 6000) {
        clearTimeout(this.incomingTimer);
        this.incomingTimer = setTimeout(() => this.hideIncomingNotice(), delay);
      },
      hideIncomingNotice() {
        clearTimeout(this.incomingTimer);
        this.incomingShown = false;
        setTimeout(() => {
          this.incomingNotice = null;
          if (!this.incomingQueue.length && this.reloadAfterNotice && !this.modal) return window.location.reload();
          this.showIncomingNotice();
        }, 450);
      },
      /** Notification links stay in Simplify: admin pages open in the popup, Simplify links without a reload. */
      followNotice(event, notice) {
        const action = notice?.action;
        if (!action) return;
        if (notice.pelican) this.markPanelNoticesRead([notice.key]);
        if (action.run) {
          event.preventDefault();
          this.notifyMenu = false;
          return action.run();
        }
        let url;
        try {
          url = new URL(action.url, location.origin);
        } catch {
          return;
        }
        if (url.origin !== location.origin) return;
        const admin = url.pathname.startsWith('/admin') ? url.pathname : url.searchParams.get('admin');
        if (!admin || !admin.startsWith('/') || !this.adminPages().length) return;
        event.preventDefault();
        this.notifyMenu = false;
        this.incomingShown = false;
        const known = this.hostSections().flatMap((section) => section.pages).find((item) => new URL(item.url, location.origin).pathname === admin);
        this.openAdminPage(known || { key: 'link', label: action.label || 'Admin', url: admin });
      },
      async markPanelNoticesRead(keys) {
        const ids = keys.filter(Boolean);
        if (!ids.length) return;
        const wasUnread = new Set([...this.panelNotices, ...this.feed.items].filter((n) => ids.includes(n.key) && n.unread !== false).map((n) => n.key));
        this.panelNotices = this.panelNotices.filter((n) => !ids.includes(n.key));
        this.feed = { ...this.feed, items: this.feed.items.map((n) => (ids.includes(n.key) ? { ...n, unread: false } : n)) };
        this.panelUnread = Math.max(0, this.panelUnread - wasUnread.size);
        try {
          const items = await wire.markPanelNotificationsRead(ids);
          if (Array.isArray(items)) this.panelNotices = items;
        } catch {
          this.toast('Notifications could not be marked as read.', 'warn');
        }
      },
      enhanceCopyright() {
        if (!document.querySelectorAll) return;
        for (const footer of document.querySelectorAll('footer,.fi-footer')) {
          if (footer.closest('.ps-modal') || footer.querySelector('[data-ps-attribution]') || !/©|copyright/i.test(footer.textContent)) continue;
          footer.setAttribute('data-ps-footer', '');
          const wrapper = footer.parentElement;
          if (wrapper && wrapper.children.length === 1 && !wrapper.matches('body,.fi-layout,.fi-main-ctn')) wrapper.setAttribute('data-ps-footer-wrapper', '');
          const line = [...(footer.querySelectorAll?.('p,div') || [])].reverse().find((el) => /©|copyright/i.test(el.textContent)) || footer;
          line.setAttribute('data-ps-copyright-line', '');
          const credit = document.createElement('span');
          credit.dataset.psAttribution = '';
          credit.append(document.createTextNode(' - Enhanced by '));
          const link = document.createElement('a');
          link.href = 'https://github.com/jerajerajerajera/PelicanSimplify';
          link.className = 'ps-project-link';
          link.textContent = 'Simplify';
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          credit.append(link);
          if (this.boot.build_version) credit.append(document.createTextNode(' · Build ' + this.boot.build_version));
          line.append(credit);
        }
      },
      applyFavicon() {
        if (!this.boot.favicon_url || !document.head) return;
        this.previousFavicons = [...document.head.querySelectorAll('link[rel~="icon"]')];
        this.previousFavicons.forEach((el) => el.remove());
        this.favicon = document.createElement('link');
        this.favicon.rel = 'icon';
        this.favicon.type = 'image/svg+xml';
        this.favicon.href = this.boot.favicon_url;
        document.head.append(this.favicon);
      },
      restoreFavicon() {
        if (!this.favicon) return;
        this.favicon.remove();
        this.previousFavicons?.forEach((el) => document.head.append(el));
        this.favicon = null;
      },
      focusModal() {
        if (typeof this.$nextTick !== 'function') return;
        this.$nextTick(() => {
          if (!this.modal || this.destroyed) return;
          const visible = (selector) => [...(this.$root?.querySelectorAll?.(selector) || [])].find((el) => el.getClientRects().length > 0);
          (visible('.ps-modal-body :is(input,select,textarea,button):not([disabled]),.ps-modal-body a[href]') || visible('.ps-modal-head .ps-close'))?.focus();
        });
      },

      /* Overview -------------------------------------------------------------------------------- */
      playerCount(s) {
        if (s?.status === 'offline') return '0 / 0';
        const count = Number(s?.players), max = Number(s?.max_players);
        if (s?.players == null || !Number.isFinite(count) || count < 0) return '—';
        return Math.floor(count) + ' / ' + (s?.max_players != null && Number.isFinite(max) && max >= 0 ? Math.floor(max) : 0);
      },
      openHostHealth() {
        if (this.server || this.busy || this.modal || !this.boot.features?.host_health) return;
        this.healthExpanded = !this.healthExpanded;
        this.healthOpenNodes = [];
      },
      healthNodes() {
        return this.boot.host_health?.nodes || [];
      },
      healthNodeOpen(node) {
        return this.healthOpenNodes.some((id) => String(id) === String(node.id));
      },
      toggleHealthNode(node) {
        if (!this.healthExpanded || this.server || this.busy || this.modal || !this.boot.features?.host_health || !this.healthNodes().some((n) => String(n.id) === String(node.id))) return;
        this.healthOpenNodes = this.healthNodeOpen(node) ? this.healthOpenNodes.filter((id) => String(id) !== String(node.id)) : [...this.healthOpenNodes, node.id];
      },
      nodeServers(node) {
        return (this.boot.servers || []).filter((s) => Number(s.node_id) === Number(node.id));
      },
      hostRate(h = this.boot.host_health) {
        return this.rateLabel(h?.network_rx_per_second, h?.network_tx_per_second);
      },
      rateLabel(rx, tx) {
        return rx == null || tx == null ? '—' : '↓ ' + this.trafficBytes(rx) + '/s / ↑ ' + this.trafficBytes(tx) + '/s';
      },
      serverRate(s) {
        const n = this.overviewNetwork[s.uuid];
        return this.rateLabel(n?.rx_per_second, n?.tx_per_second);
      },
      serverTraffic(s) {
        return s.usage?.network_rx_bytes == null || s.usage?.network_tx_bytes == null ? '—' : '↓ ' + this.trafficBytes(s.usage.network_rx_bytes) + ' / ↑ ' + this.trafficBytes(s.usage.network_tx_bytes);
      },
      networkSample(current, at, previous) {
        const rx = Number(current?.rx_bytes), tx = Number(current?.tx_bytes);
        if (current?.rx_bytes == null || current?.tx_bytes == null || !Number.isFinite(rx) || !Number.isFinite(tx) || rx < 0 || tx < 0) return null;
        const dt = at - (previous?.at ?? at), ready = previous && dt > 0 && dt <= 120;
        const reset = ready && (rx < previous.rx || tx < previous.tx);
        return { rx, tx, at, rx_per_second: ready ? (reset ? 0 : (rx - previous.rx) / dt) : null, tx_per_second: ready ? (reset ? 0 : (tx - previous.tx) / dt) : null };
      },
      recordNetwork(current, at = Date.now() / 1000) {
        this.networkPrevious = this.networkSample(current, at, this.networkPrevious);
        this.networkRates = { rx_per_second: this.networkPrevious?.rx_per_second ?? null, tx_per_second: this.networkPrevious?.tx_per_second ?? null };
        if (this.networkPrevious) {
          this.series.network.push((this.networkRates.rx_per_second ?? 0) + (this.networkRates.tx_per_second ?? 0));
          if (this.series.network.length > 30) this.series.network.shift();
        }
      },
      powerActionLabel(action) {
        return POWER_LABELS[action] || '';
      },
      canOverviewPower(s, action) {
        const permission = { start: 'control.start', stop: 'control.stop', kill: 'control.stop', restart: 'control.restart' }[action];
        return this.quickActionsMode() !== 'off' && !!permission && !!s.linked && !!s.permissions?.[permission]
          && !['installing', 'install failed', 'restoring'].includes(s.status)
          && (action !== 'start' || s.status === 'offline')
          && (!['stop', 'kill', 'restart'].includes(action) || s.status !== 'offline');
      },
      async overviewPower(s, action, approved = false) {
        if (this.overviewPowerBusy || !this.canOverviewPower(s, action)) return;
        if (!approved) {
          this.powerConfirmation = { uuid: s.uuid, action };
          return;
        }
        if (this.powerConfirmation?.uuid !== s.uuid || this.powerConfirmation?.action !== action) return;
        this.powerConfirmation = null;
        this.overviewPowerBusy = s.uuid;
        try {
          await wire.overviewPower(s.uuid, action);
          this.toast(POWER_LABELS[action] + ' sent to ' + (s.alias || s.name), 'ok');
          await this.refreshOverview();
        } catch (error) {
          this.toast(error?.message || 'The power action could not be sent.', 'danger');
        } finally {
          this.overviewPowerBusy = null;
        }
      },
      /** Large and secondary card names, following the "Large name" preference. */
      cardNames(s) {
        if (!s.alias) return { large: s.name, small: '' };
        return this.overviewPreferences.largeName === 'name' ? { large: s.name, small: s.alias } : { large: s.alias, small: s.name };
      },
      fitCardTitle(row, name) {
        const title = row.querySelector('h3');
        if (!title) return;
        const base = { list: 21, compact: 18 }[this.overviewView] || 23;
        title.style.fontSize = base + 'px';
        if (this.overviewView !== 'list') {
          // Grid: the name may use two lines; only a name that still does not fit gets smaller.
          let size = base;
          while (size > 12 && (title.scrollHeight > title.clientHeight + 1 || title.scrollWidth > title.clientWidth + 1)) title.style.fontSize = --size + 'px';
          return;
        }
        const original = row.querySelector('.ps-card-real-name');
        const reserve = this.overviewView === 'list' && typeof innerWidth !== 'undefined' && innerWidth > 700 && original && !original.classList.contains('no-alias') ? original.getBoundingClientRect().width + 8 : 0;
        const available = Math.max(0, row.getBoundingClientRect().width - reserve);
        // Measure the text itself: scrollWidth rounds, so a name a fraction too wide would get "…" instead of fitting.
        let needed = title.scrollWidth;
        try {
          const range = document.createRange();
          range.selectNodeContents(title);
          needed = range.getBoundingClientRect().width;
        } catch {}
        if (available > 0 && needed > available) title.style.fontSize = Math.max(10, Math.floor((base * available) / needed * 10) / 10) + 'px';
      },
      /** Shrinks a MOTD box's text only as far as needed to show both full lines, never below 8px. */
      fitMotd(box) {
        if (!box) return;
        box.style.fontSize = '';
        let size = parseFloat(getComputedStyle(box).fontSize) || 12;
        while (size > 8 && (box.scrollHeight > box.clientHeight + 1 || box.scrollWidth > box.clientWidth + 1)) box.style.fontSize = (size -= .5) + 'px';
      },
      softwareTooltip(s) {
        const software = String(s.software || 'Server software').trim();
        const raw = String(s.reported_version || s.minecraft_version || '').trim();
        const version = s.game === 'minecraft' ? raw.match(MINECRAFT_VERSION)?.[1] || '' : raw;
        return (s.status === 'running' ? 'Running ' : '') + software + (version ? ' · ' + version : '');
      },
      gameLabel(s) {
        return s.game === 'minecraft' ? 'Minecraft' : String(s.game_label || 'Other game').trim() || 'Other game';
      },
      gameKey(s) {
        return this.gameLabel(s).toLocaleLowerCase();
      },
      gameOptions() {
        const games = new Map();
        for (const s of this.boot.servers || []) games.set(this.gameKey(s), this.gameLabel(s));
        return [...games].map(([key, label]) => ({ key, label })).sort((a, b) => a.label.localeCompare(b.label));
      },
      visibleServers() {
        const scope = this.ownerFilterShown() ? this.overviewPreferences.serverScope : 'all';
        return (this.boot.servers || []).filter((s) => (this.gameFilter === 'all' || this.gameKey(s) === this.gameFilter) && (this.nodeFilter === 'all' || Number(s.node_id) === this.nodeFilter) && (scope === 'all' || !!s.mine === (scope === 'mine')));
      },
      /** My servers / Others only means something when the overview holds both (administrators, or everyone in friends mode). */
      ownerFilterShown() {
        const servers = this.boot.servers || [];
        return servers.some((s) => s.mine) && servers.some((s) => !s.mine);
      },
      /** Nodes that have servers in the overview (names from the listing, else the health data). */
      nodeOptions() {
        const names = new Map((this.boot.host_health?.nodes || []).map((n) => [Number(n.id), n.name]));
        const nodes = new Map();
        for (const s of this.boot.servers || []) {
          const id = Number(s.node_id);
          if (!nodes.has(id)) nodes.set(id, { id, name: s.node_name || names.get(id) || 'Node ' + id });
        }
        return [...nodes.values()].sort((a, b) => a.name.localeCompare(b.name));
      },
      groupedByNode() {
        const mode = this.overviewPreferences.groupByNode;
        return mode === 'always' || (mode === 'auto' && this.nodeOptions().length > 1);
      },
      /** Servers, with a heading row before each own group, then each node when grouping by node is on. */
      overviewItems() {
        // The listing already comes sorted by name.
        const servers = this.overviewServerOrder === 'name' ? this.visibleServers() : this.sortServers(this.visibleServers(), this.overviewServerOrder);
        const items = [], placed = new Set();
        for (const group of this.overviewGroups) {
          const members = this.groupMembers(group, servers).filter((s) => !placed.has(s.uuid));
          if (!members.length) continue;
          members.forEach((s) => placed.add(s.uuid));
          items.push({ isNodeHeader: true, icon: 'files', uuid: 'group-' + group.id, name: group.name, count: members.length, color: group.color || null }, ...members);
        }
        const rest = servers.filter((s) => !placed.has(s.uuid));
        if (this.groupedByNode()) {
          for (const node of this.nodeOptions()) {
            const members = rest.filter((s) => Number(s.node_id) === node.id);
            if (members.length) items.push({ isNodeHeader: true, uuid: 'node-' + node.id, name: node.name, count: members.length }, ...members);
          }
          return items;
        }
        if (items.length && rest.length) items.push({ isNodeHeader: true, icon: 'grid', uuid: 'group-other', name: 'Other servers', count: rest.length });
        return items.concat(rest);
      },
      /** Name sorts by server name (A to Z, like Pelican's list); Created is oldest first and Port lowest first, then by name. */
      sortServers(list, order) {
        const key = { created: 'created', port: 'port' }[order];
        const name = (a, b) => String(a.name || '').localeCompare(String(b.name || ''));
        if (!key) return order === 'name' ? [...list].sort(name) : list;
        const value = (s) => (s[key] !== null && s[key] !== '' && Number.isFinite(Number(s[key])) ? Number(s[key]) : Infinity);
        return [...list].sort((a, b) => value(a) - value(b) || name(a, b));
      },
      /** A group's servers among list (already in the overview's order), in the group's own order. */
      groupMembers(group, list) {
        const members = list.filter((s) => group.servers.includes(s.uuid));
        if (group.sort === 'manual') return members.sort((a, b) => group.servers.indexOf(a.uuid) - group.servers.indexOf(b.uuid));
        return group.sort === 'overview' ? members : this.sortServers(members, group.sort);
      },
      /** Where groups lived before they were saved with the account (0.5.0-snapshot.11 and .12, beta.5). */
      overviewGroupsKey() {
        return 'pelicanSimplify.overviewGroups.' + encodeURIComponent(this.boot.user || 'default');
      },
      /** A server sits in at most one group; the server checks the same rules. */
      cleanOverviewGroups(list) {
        const groups = [], seen = new Set();
        for (const group of Array.isArray(list) ? list : []) {
          if (groups.length >= GROUP_LIMIT || !group || typeof group.id !== 'string' || groups.some((g) => g.id === group.id)) continue;
          const name = String(group.name || '').trim().slice(0, GROUP_NAME_LENGTH) || 'Group';
          const servers = (Array.isArray(group.servers) ? group.servers : []).filter((uuid) => typeof uuid === 'string' && !seen.has(uuid) && seen.add(uuid));
          const sort = GROUP_ORDERS.some((o) => o.key === group.sort) ? group.sort : 'overview';
          const color = typeof group.color === 'string' && /^#[0-9a-f]{6}$/i.test(group.color) ? group.color.toLowerCase() : null;
          groups.push({ id: group.id.slice(0, 40), name, servers, sort, ...(color ? { color } : {}) });
        }
        return groups;
      },
      /** Groups and order come with the account; an account that never saved any takes over the groups this browser kept. */
      restoreOverviewGroups() {
        const account = this.boot.overview_groups;
        if (account && typeof account === 'object') {
          this.overviewGroups = this.cleanOverviewGroups(account.groups);
          this.overviewServerOrder = SERVER_ORDERS.some((o) => o.key === account.order) ? account.order : 'name';
          return;
        }
        let saved = null;
        try {
          saved = JSON.parse(localStorage.getItem(this.overviewGroupsKey()) || 'null');
        } catch {}
        const groups = this.cleanOverviewGroups(saved);
        this.overviewGroups = groups;
        if (groups.length) this.saveOverviewGroups(groups, true);
      },
      /** Shows the change at once and saves it shortly after, so quick clicks become one save. */
      saveOverviewGroups(groups, handover = false) {
        this.overviewGroups = groups;
        clearTimeout(this.overviewGroupsTimer);
        this.overviewGroupsTimer = setTimeout(async () => {
          try {
            await wire.saveOverviewGroups(this.overviewGroups, this.overviewServerOrder);
            if (handover) localStorage.removeItem(this.overviewGroupsKey());
          } catch {
            if (!handover) this.toast('Your groups could not be saved. Try again in a moment.', 'danger');
          }
        }, handover ? 0 : 400);
      },
      setServerOrder(order) {
        if (!SERVER_ORDERS.some((o) => o.key === order) || order === this.overviewServerOrder) return;
        this.overviewServerOrder = order;
        this.saveOverviewGroups(this.overviewGroups);
      },
      setGroupOrder(id, order) {
        if (!GROUP_ORDERS.some((o) => o.key === order)) return;
        const group = this.overviewGroups.find((g) => g.id === id);
        if (!group || group.sort === order) return;
        // Switching to My own order starts from the order shown right now.
        const servers = order === 'manual' ? [...this.groupMembers(group, this.sortServers(this.boot.servers || [], this.overviewServerOrder)).map((s) => s.uuid), ...group.servers.filter((uuid) => !(this.boot.servers || []).some((s) => s.uuid === uuid))] : group.servers;
        this.saveOverviewGroups(this.overviewGroups.map((g) => (g.id === id ? { ...g, sort: order, servers } : g)));
      },
      /** Arrows in My own order: swaps a server with the one shown above or below it. */
      moveGroupServer(id, uuid, step) {
        const group = this.overviewGroups.find((g) => g.id === id);
        if (!group || group.sort !== 'manual') return;
        const shown = this.groupMembers(group, this.boot.servers || []), at = shown.findIndex((s) => s.uuid === uuid), other = shown[at + step];
        if (at < 0 || !other) return;
        const servers = [...group.servers], a = servers.indexOf(uuid), b = servers.indexOf(other.uuid);
        [servers[a], servers[b]] = [servers[b], servers[a]];
        this.saveOverviewGroups(this.overviewGroups.map((g) => (g.id === id ? { ...g, servers } : g)));
      },
      /** The group's servers first, in its order, then every other server by name. */
      groupServerList(group) {
        const all = this.boot.servers || [];
        const members = this.groupMembers(group, this.sortServers(all, this.overviewServerOrder));
        const others = all.filter((s) => !group.servers.includes(s.uuid)).sort((a, b) => this.cardNames(a).large.localeCompare(this.cardNames(b).large));
        return [...members, ...others];
      },
      addOverviewGroup() {
        if (this.overviewGroups.length >= GROUP_LIMIT) return;
        let number = this.overviewGroups.length + 1;
        while (this.overviewGroups.some((g) => g.name === 'Group ' + number)) number++;
        const id = 'g' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
        this.saveOverviewGroups([...this.overviewGroups, { id, name: 'Group ' + number, servers: [], sort: 'overview' }]);
        this.groupEditing = id;
      },
      renameOverviewGroup(id, name) {
        const clean = String(name || '').trim().slice(0, GROUP_NAME_LENGTH);
        // An empty name keeps the old one (the field shows it again).
        this.saveOverviewGroups(this.overviewGroups.map((g) => (g.id === id && clean ? { ...g, name: clean } : { ...g })));
        return this.overviewGroups.find((g) => g.id === id)?.name || '';
      },
      removeOverviewGroup(id) {
        this.saveOverviewGroups(this.overviewGroups.filter((g) => g.id !== id));
        if (this.groupEditing === id) this.groupEditing = null;
      },
      moveOverviewGroup(id, step) {
        const groups = [...this.overviewGroups], from = groups.findIndex((g) => g.id === id), to = from + step;
        if (from < 0 || to < 0 || to >= groups.length) return;
        [groups[from], groups[to]] = [groups[to], groups[from]];
        this.saveOverviewGroups(groups);
      },
      /** Puts a server in this group (taking it out of any other), or takes it out again. */
      toggleGroupServer(id, uuid) {
        const inGroup = this.overviewGroups.some((g) => g.id === id && g.servers.includes(uuid));
        this.saveOverviewGroups(this.overviewGroups.map((g) => {
          const servers = g.servers.filter((x) => x !== uuid);
          return { ...g, servers: g.id === id && !inGroup ? [...servers, uuid] : servers };
        }));
      },
      /** A group's colour, or null to go back to each server's own colour. */
      setGroupColor(id, color) {
        const clean = typeof color === 'string' && /^#[0-9a-f]{6}$/i.test(color) ? color.toLowerCase() : null;
        this.saveOverviewGroups(this.overviewGroups.map((g) => {
          if (g.id !== id) return { ...g };
          const { color: _old, ...rest } = g;
          return clean ? { ...rest, color: clean } : rest;
        }));
      },
      serverGroup(uuid) {
        return this.overviewGroups.find((g) => g.servers.includes(uuid)) || null;
      },
      /** Servers in a group that still exist (a removed server simply stops counting). */
      groupCount(group) {
        return (this.boot.servers || []).filter((s) => group.servers.includes(s.uuid)).length;
      },
      setGameFilter(key) {
        if (key === 'all' || this.gameOptions().some((g) => g.key === key)) this.gameFilter = key;
      },
      overviewPreferencesKey() {
        return 'pelicanSimplify.overviewAppearance.' + encodeURIComponent(this.boot.user || 'default');
      },
      restoreOverviewPreferences() {
        const preferences = { ...OVERVIEW_DEFAULTS };
        try {
          const saved = JSON.parse(localStorage.getItem(this.overviewPreferencesKey()) || 'null');
          if (saved && typeof saved === 'object') {
            for (const key of OVERVIEW_SWITCHES) if (typeof saved[key] === 'boolean') preferences[key] = saved[key];
            // Before Off/On/Hover, quick actions were a switch that meant "on hover".
            if (typeof saved.showQuickActions === 'boolean') preferences.quickActions = saved.showQuickActions ? 'hover' : 'off';
            for (const [key, values] of Object.entries(OVERVIEW_CHOICES)) if (values.includes(saved[key])) preferences[key] = saved[key];
          }
        } catch {}
        this.overviewPreferences = preferences;
      },
      setOverviewPreference(key, value) {
        if (OVERVIEW_SWITCHES.includes(key)) {
          if (typeof value !== 'boolean') return;
        } else if (!(OVERVIEW_CHOICES[key] || []).includes(value)) return;
        this.overviewPreferences = { ...this.overviewPreferences, [key]: value };
        if (key === 'quickActions' && value === 'off') this.powerConfirmation = null;
        try {
          localStorage.setItem(this.overviewPreferencesKey(), JSON.stringify(this.overviewPreferences));
        } catch {}
      },
      /** Bigger console and the log in the mini console are remembered, so the next server page opens the same way. */
      setConsoleFocus(on) {
        this.consoleFocus = on;
        this.setOverviewPreference('serverBiggerConsole', on);
      },
      setConsolePeek(on) {
        this.consolePeek = on;
        this.setOverviewPreference('serverConsoleLog', on);
        this.renderLogs();
      },
      /** Off, on (always shown) or hover. A touch screen cannot hover, so there Hover shows them like On. */
      quickActionsMode() {
        const mode = this.overviewPreferences.quickActions;
        if (mode !== 'hover') return mode === 'on' ? 'on' : 'off';
        return typeof matchMedia !== 'undefined' && matchMedia('(hover: none)').matches ? 'on' : 'hover';
      },
      overviewMetrics() {
        return [
          { key: 'cpu', label: 'CPU', icon: 'cpu', show: this.overviewPreferences.showCpu },
          { key: 'ram', label: 'RAM', icon: 'ram', show: this.overviewPreferences.showRam },
        ].filter((metric) => metric.show);
      },
      overviewViewKey() {
        const phone = typeof innerWidth !== 'undefined' && innerWidth <= 600;
        return 'pelicanSimplify.overviewView.' + (phone ? 'phone.' : '') + encodeURIComponent(this.boot.user || 'default');
      },
      restoreOverviewView() {
        const phone = typeof innerWidth !== 'undefined' && innerWidth <= 600;
        this.overviewCompact = phone;
        this.phone = phone;
        this.overviewView = phone ? 'list' : 'grid';
        try {
          const view = localStorage.getItem(this.overviewViewKey());
          if (OVERVIEW_VIEWS.includes(view)) this.overviewView = view;
        } catch {}
      },
      /** Marks a sideways scrolling strip with the edges that hide more items, so the cut-off side fades out. */
      scrollHint(el) {
        const update = () => {
          const max = el.scrollWidth - el.clientWidth;
          const hint = max > 1 ? [el.scrollLeft > 1 && 'start', el.scrollLeft < max - 1 && 'end'].filter(Boolean).join(' ') : '';
          if ((el.dataset.psMore || '') === hint) return;
          if (hint) el.dataset.psMore = hint;
          else delete el.dataset.psMore;
        };
        el.addEventListener('scroll', update, { passive: true });
        if (typeof ResizeObserver === 'function') {
          const observer = new ResizeObserver(update);
          observer.observe(el);
          for (const child of el.children) observer.observe(child);
        }
        this.$nextTick?.(update);
      },
      setOverviewView(view) {
        if (!OVERVIEW_VIEWS.includes(view)) return;
        this.overviewView = view;
        try {
          localStorage.setItem(this.overviewViewKey(), view);
        } catch {}
      },
      /** Card width and column count for the grid views: a fixed count is the most per row, narrower screens wrap. */
      overviewGridStyle() {
        const compact = this.overviewView === 'compact';
        const width = (compact ? { narrow: 170, normal: 210, wide: 270 } : { narrow: 210, normal: 260, wide: 330 })[this.overviewPreferences.cardWidth] || 260;
        const fixed = Number(this.overviewPreferences.columns) || 0;
        const most = fixed || (compact ? { narrow: 7, normal: 6, wide: 5 } : { narrow: 5, normal: 4, wide: 3 })[this.overviewPreferences.cardWidth] || 4;
        const min = width - 30;
        return {
          '--ps-grid-width': `calc(${most} * ${width}px + ${most - 1} * 16px)`,
          '--ps-grid-template': fixed ? `repeat(auto-fill, minmax(min(100%, max(${min}px, calc((100% - ${(fixed - 1) * 16}px) / ${fixed}))), 1fr))` : `repeat(auto-fill, minmax(min(100%, ${min}px), 1fr))`,
        };
      },
      /** Keeps the health dock aligned with the rendered card grid (including scrollbar width). */
      initOverviewDock() {
        if (this.server || !this.$root || typeof ResizeObserver === 'undefined') return;
        this.overviewDockObserver?.disconnect();
        const overview = this.$root.querySelector('.ps-overview'), grid = overview?.querySelector('.ps-server-grid');
        if (!overview || !grid) return;
        const sync = () => {
          const parent = overview.getBoundingClientRect(), cards = grid.getBoundingClientRect();
          if (cards.width <= 0) return;
          const scale = this.uiScale || 1;
          overview.style.setProperty('--ps-health-width', cards.width / scale + 'px');
          overview.style.setProperty('--ps-health-left', (cards.left - parent.left) / scale + 'px');
        };
        this.overviewDockObserver = new ResizeObserver(sync);
        this.overviewDockObserver.observe(overview);
        this.overviewDockObserver.observe(grid);
        sync();
      },
      overviewColumns() {
        return Math.min(4, Math.max(1, this.visibleServers().length));
      },
      cpuLabel(s) {
        const n = s.usage?.cpu_percent;
        return typeof n === 'number' && Number.isFinite(n) ? n.toFixed(1) + '%' : '—';
      },
      memoryLabel(s) {
        const n = s.usage?.memory_bytes;
        if (typeof n !== 'number' || !Number.isFinite(n)) return '—';
        const cap = s.usage?.memory_limit_bytes || 0;
        const gb = Math.max(n, cap) >= 1073741824;
        const unit = gb ? 1073741824 : 1048576;
        const number = (v) => (gb ? (v / unit).toFixed(1) : Math.round(v / unit));
        return number(n) + (cap > 0 ? ' / ' + number(cap) : '') + (gb ? ' GB' : ' MB');
      },
      hostPercent(kind, h = this.boot.host_health) {
        if (!h || !h.available) return null;
        if (kind === 'cpu') return h.cpu_percent;
        const total = h[kind + '_total'];
        return total > 0 ? (h[kind + '_used'] / total) * 100 : null;
      },
      hostMetricState(kind, h = this.boot.host_health) {
        if (h?.metric_states?.[kind]) return h.metric_states[kind];
        const n = this.hostPercent(kind, h);
        if (typeof n !== 'number' || !Number.isFinite(n) || h.available < h.hosts) return 'unavailable';
        return n >= 95 ? 'critical' : n >= 85 ? 'warning' : 'healthy';
      },
      hostMetricLabel(kind, h = this.boot.host_health) {
        if (kind === 'network') return this.hostMetricState(kind, h) === 'healthy' ? 'Traffic reported; bandwidth capacity unknown' : 'Unavailable';
        return { healthy: 'Healthy', warning: 'High load', critical: 'Critical load', unavailable: 'Unavailable' }[this.hostMetricState(kind, h)] || 'Unavailable';
      },
      hostFill(kind, h = this.boot.host_health) {
        if (kind === 'network') return (h?.network_rx_bytes ?? 0) + (h?.network_tx_bytes ?? 0) > 0 ? 100 : 0;
        return Math.min(100, Math.max(0, this.hostPercent(kind, h) || 0));
      },
      hostValue(kind, h = this.boot.host_health) {
        if (kind === 'network') return h?.network_rx_bytes == null || h?.network_tx_bytes == null ? '—' : '↓ ' + this.trafficBytes(h.network_rx_bytes) + ' / ↑ ' + this.trafficBytes(h.network_tx_bytes);
        const n = this.hostPercent(kind, h);
        if (typeof n !== 'number' || !Number.isFinite(n)) return '—';
        if (kind === 'cpu') return n.toFixed(1) + '%';
        return this.memoryLabel({ usage: { memory_bytes: h[kind + '_used'], memory_limit_bytes: h[kind + '_total'] } });
      },
      recordOverview(servers) {
        const ids = new Set();
        for (const s of servers || []) {
          ids.add(s.uuid);
          const h = this.overviewHistory[s.uuid] || (this.overviewHistory[s.uuid] = []);
          h.push({ cpu: s.usage?.cpu_percent ?? null, ram: s.usage?.memory_bytes ?? null });
          this.overviewNetwork[s.uuid] = this.networkSample({ rx_bytes: s.usage?.network_rx_bytes, tx_bytes: s.usage?.network_tx_bytes }, s.usage?.network_sampled_at ?? Date.now() / 1000, this.overviewNetwork[s.uuid]);
          if (h.length > 24) h.shift();
        }
        for (const id of Object.keys(this.overviewHistory)) {
          if (ids.has(id)) continue;
          delete this.overviewHistory[id];
          delete this.overviewNetwork[id];
        }
      },
      cardRamLabel(s) {
        return this.memoryLabel({ usage: { memory_bytes: s.usage?.memory_bytes } });
      },
      cardGraphY(s, key, value) {
        if (typeof value !== 'number' || !Number.isFinite(value) || value < 0) return null;
        const limit = key === 'cpu'
          ? (s.usage?.cpu_limit > 0 ? s.usage.cpu_limit : 100)
          : (s.usage?.memory_limit_bytes > 0 ? s.usage.memory_limit_bytes : Math.max(1073741824, ...(this.overviewHistory[s.uuid] || []).map((p) => p.ram || 0)));
        return 26 - Math.min(1, value / limit) * 23;
      },
      cardGraph(s, key) {
        const h = this.overviewHistory[s.uuid] || [];
        let path = '', drawing = false;
        h.forEach((p, i) => {
          const y = this.cardGraphY(s, key, p[key]);
          if (y === null) {
            drawing = false;
            return;
          }
          const x = 2 + ((24 - h.length + i) / 23) * 96;
          path += (drawing ? ' L' : ' M') + x.toFixed(2) + ' ' + y.toFixed(2);
          drawing = true;
        });
        return path;
      },
      cardGraphPoint(s, key) {
        const h = this.overviewHistory[s.uuid] || [];
        return h.length ? this.cardGraphY(s, key, h[h.length - 1][key]) : null;
      },
      async refreshOverview() {
        if (globalThis.document?.hidden || this.server || this.destroyed || this.overviewRefreshing) return;
        this.overviewRefreshing = true;
        try {
          const [servers, health] = await Promise.all([wire.listing(), this.boot.features?.host_health ? wire.hostHealth() : Promise.resolve({})]);
          if (this.destroyed) return;
          this.boot.servers = servers;
          if (this.gameFilter !== 'all' && !this.gameOptions().some((g) => g.key === this.gameFilter)) this.gameFilter = 'all';
          this.boot.host_health = health;
          this.healthOpenNodes = this.healthOpenNodes.filter((id) => health.nodes?.some((n) => String(n.id) === String(id)));
          this.recordOverview(servers);
        } catch {
        } finally {
          this.overviewRefreshing = false;
        }
      },

      /* Creation, settings and account popups (embedded pages) ---------------------------------- */
      /** Same-origin URL with the embed marker, or '' for anything else. */
      frameUrl(href) {
        try {
          const url = new URL(href, location.origin);
          if (url.origin !== location.origin) return '';
          url.searchParams.set('simplify_embed', '1');
          return url.href;
        } catch {
          return '';
        }
      },
      createFrameUrl() {
        const url = this.frameUrl(['server', 'minecraft'].includes(this.createGame) ? this.boot.create_url : this.createGame === 'other' && this.boot.game_create_url ? this.boot.game_create_url : this.boot.legacy_create_url);
        if (!url) return '';
        const target = new URL(url);
        // The Server Maker shows Minecraft and the other games together; a game in the link opens straight on it.
        if (['minecraft', 'other'].includes(this.createGame)) target.searchParams.set('game', this.createGame);
        return target.href;
      },
      /** Simplify's own creators (Minecraft, other games) fill the popup edge to edge; Pelican's form is a native page. */
      createFrameKind() {
        return ['server', 'minecraft'].includes(this.createGame) || (this.createGame === 'other' && this.boot.game_create_url) ? 'create' : 'native';
      },
      canCreateGame(game) {
        // 'server' is the Server Maker (Minecraft and/or other games, whichever add-ons are on).
        if (game === 'server') return !!this.boot.can_create && (this.canCreateGame('minecraft') || (this.canCreateGame('other') && !!this.boot.game_create_url));
        // 'native' is the only choice when the easy creator is off (Pelican's own form, administrators).
        return !!this.boot.can_create && (game === 'minecraft' || (game === 'other' && !!this.boot.game_create_url) || !!this.boot.legacy_create_url) && ['minecraft', 'other', 'native'].includes(game) && (this.boot.create_games || ['minecraft', 'other']).includes(game);
      },
      /** Pelican's own form for other games: administrators, when the game add-on is off but Minecraft is on. */
      pelicanFormForOthers() {
        return !this.boot.game_create_url && !this.canCreateGame('native') && this.canCreateGame('other');
      },
      /** Create options for the phone tab bar, in the same order as the overview's create tiles. */
      createChoices() {
        return [
          { key: 'server', label: 'Server', icon: 'plus' },
          { key: 'other', label: 'Other server (Pelican form)', icon: 'gamepad' },
          { key: 'native', label: 'New server', icon: 'host' },
        ].filter((c) => (c.key === 'native' ? this.canCreateGame('native') : c.key === 'server' ? this.canCreateGame('server') : this.pelicanFormForOthers()));
      },
      openCreate(game = 'server') {
        if (!['server', 'minecraft', 'other', 'native'].includes(game) || this.busy || this.server || !this.canCreateGame(game)) return;
        this.createGame = game;
        this.openPopup('create');
      },
      openPopup(key) {
        this.accountMenu = false;
        this.adminMenu = false;
        this.navMenu = false;
        this.notifyMenu = false;
        this.appearanceMenu = false;
        this.groupsMenu = false;
        this.modalRequest++;
        this.modal = key;
        this.loading = false;
        this.focusModal();
      },
      async createFrameLoaded(frame) {
        if (this.modal !== 'create') return;
        let completed = false, createdUrl = null;
        try {
          const current = new URL(frame.contentWindow.location.href, location.origin);
          const list = new URL(this.boot.list_url, location.origin);
          const creator = new URL(['server', 'minecraft'].includes(this.createGame) ? this.boot.create_url : this.boot.legacy_create_url, location.origin);
          if (current.origin === location.origin) {
            completed = current.pathname === list.pathname && /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(current.searchParams.get('server') || '');
            if (completed) createdUrl = current.href;
            if (!completed && /\/create\/?$/.test(creator.pathname)) {
              const prefix = creator.pathname.replace(/create\/?$/, '');
              if (current.pathname.startsWith(prefix)) {
                const suffix = current.pathname.slice(prefix.length).split('/').filter(Boolean);
                completed = /^\d+$/.test(suffix[0] || '') && (suffix.length === 1 || (suffix.length === 2 && ['edit', 'view'].includes(suffix[1])));
              }
            }
          }
        } catch {}
        if (completed) {
          this.modal = null;
          this.loading = false;
          if (createdUrl) {
            location.href = createdUrl;
            return;
          }
          await this.refreshOverview();
          this.toast('Server created. Installation has started.', 'ok');
          return;
        }
        this.styleFrame(frame, this.createFrameKind());
      },
      panelSettingsUrl() {
        const url = this.frameUrl(this.boot.settings_url);
        if (!url || this.settingsSection === 'settings') return url;
        const target = new URL(url);
        target.searchParams.set('section', this.settingsSection);
        return target.href;
      },
      openPanelSettings(section = 'settings') {
        if (!this.boot.settings_url || this.busy || this.modal) return;
        this.settingsSection = section;
        this.openPopup('panel-settings');
      },
      panelSettingsLoaded(frame) {
        this.styleFrame(frame, 'settings');
        try {
          if (frame.contentWindow._psSettingsListener) return;
          frame.contentWindow._psSettingsListener = true;
          frame.contentWindow.addEventListener('simplify-settings-saved', () => {
            if (this.modal !== 'panel-settings' || this.destroyed) return;
            this.settingsChanged = true;
            this.toast('Settings saved', 'ok');
          });
        } catch {
          this.toast('Settings could not be opened.', 'danger');
        }
      },
      /** Measured height of each plugin widget frame (app list, or per server uuid); 0 hides the tile. */
      pluginWidgetHeights: {},
      pluginWidgetsLoaded(frame, slot) {
        this.styleFrame(frame, 'native');
        try {
          const box = frame.contentDocument?.querySelector('.simplify-plugin-widgets');
          const measure = () => { this.pluginWidgetHeights = { ...this.pluginWidgetHeights, [slot]: box && box.children.length ? Math.ceil(box.getBoundingClientRect().height) : 0 }; };
          measure();
          // Widgets poll and change size (a player list fills in): follow the content.
          if (box) new frame.contentWindow.ResizeObserver(measure).observe(box);
        } catch {
          this.pluginWidgetHeights = { ...this.pluginWidgetHeights, [slot]: 0 };
        }
      },
      /** Buttons other plugins add to the server list ('app') or the console ('server'), already checked by the server. */
      pluginActions(scope) {
        return (scope === 'server' ? this.server?.plugin_actions : this.boot.plugin_actions) || [];
      },
      pluginActionFrame: null,
      pluginActionOpen: false,
      pluginActionBusy: null,
      pluginActionTimer: null,
      /** A link opens like a plugin page; anything else runs in a transparent layer that shows only the plugin's dialog. */
      runPluginAction(action) {
        if (!action || action.disabled || this.pluginActionBusy || this.pluginActionOpen) return;
        if (action.url) {
          if (action.new_tab || !this.frameUrl(action.url)) return void window.open(action.url, '_blank', 'noopener');
          return this.openPluginPage({ key: action.key, label: action.label, url: action.url });
        }
        const url = action.run_url && this.frameUrl(action.run_url);
        if (!url) return;
        this.pluginActionOpen = false;
        this.pluginActionBusy = action.key;
        this.pluginActionFrame = { key: action.key, url };
        // A button that never reports back (an error page) does not block the others.
        clearTimeout(this.pluginActionTimer);
        this.pluginActionTimer = setTimeout(() => this.pluginActionMessage({ state: 'done' }), 30000);
      },
      pluginActionMessage(data) {
        if (!this.pluginActionFrame) return;
        this.pluginActionBusy = null;
        if (data.state === 'modal') {
          clearTimeout(this.pluginActionTimer);
          this.pluginActionOpen = true;
          this.$nextTick?.(() => this.$root?.querySelector?.('.ps-plugin-action-layer iframe')?.focus());
          return;
        }
        // Done: its notifications arrive with the next request and still have to reach the toasts, so the
        // (invisible) layer goes a few seconds later; another button may start meanwhile.
        const frame = this.pluginActionFrame;
        this.pluginActionOpen = false;
        clearTimeout(this.pluginActionTimer);
        this.pluginActionTimer = setTimeout(() => { if (this.pluginActionFrame === frame) this.pluginActionFrame = null; }, 5000);
        if (this.server) this.refreshServerInfo();
      },
      /** A button that redirects (to a page of its plugin) takes the whole window there, like on Pelican's page. */
      pluginActionLoaded(frame) {
        this.styleFrame(frame, 'native');
        try {
          const target = new URL(frame.contentWindow.location.href);
          if (target.pathname.endsWith('/simplify-actions')) return;
          target.searchParams.delete('simplify_embed');
          this.pluginActionFrame = null;
          window.location.href = target.href;
        } catch {}
      },
      /** Account-level pages other plugins add to the panel (hidden ones are left out by the server). */
      pluginPages() {
        return this.boot.plugin_pages || [];
      },
      openPluginPage(page) {
        if (!page?.url || this.busy || this.modal) return;
        this.accountMenu = false;
        if (page.new_tab) return void window.open(page.url, '_blank', 'noopener');
        this.adminPage = page;
        this.openPopup('admin-page');
      },
      openAccount() {
        if (!this.boot.profile_url || this.busy || this.modal) return;
        this.openPopup('account');
      },
      openAlias() {
        if (this.busy || this.modal || !this.server?.permissions?.['settings.rename']) return;
        this.aliasDraft = this.server.alias || '';
        this.openPopup('alias');
      },
      async saveAlias() {
        const result = await this.run(() => wire.saveAlias(this.aliasDraft), 'Alias saved');
        if (result !== SKIPPED && result) Object.assign(this.server, result);
      },

      /* Text helpers ---------------------------------------------------------------------------- */
      minecraftVersion() {
        const match = String(this.server?.reported_version || '').match(MINECRAFT_VERSION);
        if (match) return match[1];
        const saved = String(this.server?.pack?.version || '').trim();
        return /^(?:1\.[0-9]+(?:\.[0-9]+)?|2[6-9]\.[0-9]+(?:\.[0-9]+)?)$/.test(saved) ? saved : 'Not reported';
      },
      /** Read-only MOTD rendering for cards and the server header (the editor lives in motd.js). */
      motdPreview(value) {
        return window.psMotd.parse(value).flatMap((line, index) => [
          ...(index ? [{ text: '\n', style: {} }] : []),
          ...window.psMotd.runs(line).map(({ text, style, obfuscated }) => ({ text: obfuscated ? text.replace(/\S/g, '▒') : text, style })),
        ]);
      },
      safeImage(s) {
        if (typeof s !== 'string' || !s.trim()) return '';
        if (s.startsWith('data:image/png;base64,') && s.length < 150000) return s;
        try {
          const u = new URL(s, location.origin);
          return ['https:', 'http:'].includes(u.protocol) ? u.href : '';
        } catch {
          return '';
        }
      },
      safeLink(s) {
        try {
          const u = new URL(s);
          return ['https:', 'http:'].includes(u.protocol) ? u.href : '#';
        } catch {
          return '#';
        }
      },
      head(p) {
        return 'https://mc-heads.net/avatar/' + encodeURIComponent(p.id || p.uuid || p.name) + '/48';
      },
      trafficBytes(v) {
        if (!Number.isFinite(Number(v)) || Number(v) < 0) return '—';
        v = Number(v);
        if (v >= 1073741824) return (v / 1073741824).toFixed(1) + ' GB';
        if (v >= 1048576) return (v / 1048576).toFixed(1) + ' MB';
        if (v >= 1024) return (v / 1024).toFixed(1) + ' KB';
        return Math.round(v) + ' B';
      },
      bytes(v) {
        return v >= 1073741824 ? (v / 1073741824).toFixed(1) + ' GB' : (v / 1048576).toFixed(0) + ' MB';
      },

      /* Actions ---------------------------------------------------------------------------------- */
      /** Runs one server action at a time; failures are reported once. Returns SKIPPED when it did not run. */
      async run(fn, message) {
        if (this.busy) return SKIPPED;
        this.busy = true;
        try {
          const result = await fn();
          if (message) this.toast(message, 'ok');
          return result;
        } catch (e) {
          this.toast(e?.message || 'The action could not be completed.', 'danger');
          return SKIPPED;
        } finally {
          this.busy = false;
        }
      },
      async copyText(text, message = 'Address copied') {
        try {
          await navigator.clipboard.writeText(text);
        } catch {
          const el = document.createElement('textarea');
          el.value = text;
          document.body.append(el);
          el.select();
          document.execCommand('copy');
          el.remove();
        }
        this.toast(message, 'ok');
      },
      fillSettings() {
        this.name = this.server?.name || '';
        const p = { ...(this.server?.properties || {}), ...(this.server?.pending?.properties || {}) };
        const difficulty = ['peaceful', 'easy', 'normal', 'hard'], modes = ['survival', 'creative', 'adventure', 'spectator'];
        this.settings = {
          motd: p.motd || '',
          max_players: Number(p['max-players'] || 20),
          difficulty: difficulty[Number(p.difficulty)] || p.difficulty || 'normal',
          gamemode: modes[Number(p.gamemode)] || p.gamemode || 'survival',
          view_distance: Number(p['view-distance'] || 10),
          spawn_protection: Number(p['spawn-protection'] ?? 16),
          pvp: p.pvp !== 'false',
          white_list: p['white-list'] === 'true',
          allow_flight: p['allow-flight'] === 'true',
        };
        if (this.server?.can_resources) {
          Object.assign(this.settings, { memory_gb: this.server.memory / 1024, disk_mb: this.server.disk, cpu: this.server.cpu, ...(this.server?.pending?.resources || {}) });
        }
        this.resourceCustom = {};
      },
      /* Resource presets: the same choices as in the server creator, plus your own value. */
      resourceGroups: [
        { key: 'memory_gb', label: 'RAM', icon: 'ram', custom: 'Custom RAM in GB', min: 1, max: 128, presets: [[2, '2 GB', 'Small worlds'], [4, '4 GB', 'Everyday play'], [8, '8 GB', 'More headroom'], [12, '12 GB', 'Larger modpacks']] },
        { key: 'cpu', label: 'CPU allocation', icon: 'cpu', custom: 'Custom CPU (%)', min: 1, max: 1600, presets: [[100, '100%', '1 CPU core'], [150, '150%', '1.5 CPU cores'], [200, '200%', '2 CPU cores'], [400, '400%', '4 CPU cores']] },
        { key: 'disk_mb', label: 'Disk allocation', icon: 'disk', custom: 'Custom disk (MB)', min: 1024, max: 10485760, presets: [[5120, '5 GB', '5 GB storage'], [10240, '10 GB', '10 GB storage'], [20480, '20 GB', '20 GB storage'], [40960, '40 GB', '40 GB storage']] },
      ],
      resourceCustom: {},
      resourceChoice(group) {
        const value = Number(this.settings[group.key]);
        return !this.resourceCustom[group.key] && group.presets.some(([preset]) => preset === value) ? value : 'custom';
      },
      pickResource(group, value) {
        this.resourceCustom = { ...this.resourceCustom, [group.key]: value === 'custom' };
        if (value !== 'custom') this.settings[group.key] = value;
      },
      /** Administrators may use 0 for unlimited, as in the creator. */
      resourceMin(group) {
        return this.server?.resources_admin && group.key !== 'memory_gb' ? 0 : group.min;
      },
      packageKind() {
        return ['mods', 'plugins'].includes(this.server?.package_kind) ? this.server.package_kind : '';
      },
      isPackageModal() {
        return ['mods', 'plugins'].includes(this.modal);
      },
      showWidget(key) {
        const feature = { performance: 'resources', plugins: 'packages', mods: 'packages' }[key] || key;
        if (this.boot.features?.[feature] === false) return false;
        const p = this.server?.permissions || {};
        switch (key) {
          case 'web': return !!(this.boot.web && this.server?.owner);
          case 'files': return !!p['file.read'];
          case 'backups': return !!p['backup.read'];
          case 'settings': return !!((this.server?.minecraft && p['file.read-content']) || p['settings.rename']);
          case 'performance': return !!this.server;
          case 'restart': return !!(this.server?.pending && p['control.restart'] && p['control.stop'] && p['control.start']);
          case 'players': return !!(this.server?.minecraft && p['file.read-content']);
          case 'mods':
          case 'plugins': return !!(this.packageKind() === key && p['file.read']);
          case 'schedules': return !!(p['schedule.read'] && this.server?.native_sections?.schedules);
          case 'activity': return !!(p['activity.read'] && this.server?.native_sections?.activity);
          case 'software': return !!(p['startup.read'] && (this.server?.minecraft || this.server?.native_sections?.startup));
          case 'advanced':
          case 'connection': return !!p['startup.read'];
          default: return false;
        }
      },
      widgetTitle(widget) {
        if (!this.server?.minecraft) {
          if (widget.key === 'settings') return 'Server settings';
          if (widget.key === 'software') return 'Startup settings';
        }
        return widget.title;
      },
      widgetDescription(widget) {
        if (this.server?.minecraft) return widget.description;
        return {
          settings: 'Server name & appearance',
          software: (this.server?.software || 'Game') + ' startup variables',
          advanced: 'Runtime details & ' + (this.server?.software || 'server') + ' management',
        }[widget.key] || widget.description;
      },
      modalTitle() {
        if (this.modal === 'alias') return 'Change server alias';
        if (this.modal === 'background') return 'Server background';
        if (this.modal === 'panel-settings') return this.settingsSection === 'banners' ? 'Server banners' : 'Simplify settings';
        if (this.modal === 'create') return { other: 'Create other server', native: 'Create server' }[this.createGame] || 'Server Maker';
        if (this.modal === 'account') return 'Profile';
        if (this.modal === 'notifications') return 'Notifications';
        if (this.modal === 'inbox') return 'Notifications';
        if (this.modal === 'admin-page') return this.adminPage?.label || 'Admin';
        if (this.modal === 'external') return (this.externalPage?.label || 'External') + (this.externalPage?.plugin && this.externalPage.plugin !== this.externalPage.label ? ' · ' + this.externalPage.plugin : '');
        const widget = this.widgets.find((w) => w.key === this.modal);
        return (widget ? this.widgetTitle(widget) : null) || (this.modal === 'restart' ? 'Schedule restart' : 'Workspace');
      },
      /** Every popup has a fixed size: small for short forms, large for embedded or list workspaces. */
      modalSize() {
        if (['alias', 'restart', 'notifications'].includes(this.modal)) return 'is-sm';
        if (['create', 'panel-settings', 'admin-page', 'account', 'files', 'schedules', 'activity', 'backups', 'software', 'settings', 'plugins', 'mods', 'external'].includes(this.modal)) return 'is-lg';
        return 'is-md';
      },
      modalFlush() {
        return ['create', 'panel-settings', 'admin-page', 'account', 'files', 'schedules', 'activity', 'external'].includes(this.modal) || (this.modal === 'software' && !this.server?.minecraft);
      },
      settingsTabs() {
        return [
          { key: 'general', label: 'General', icon: 'edit' },
          { key: 'gameplay', label: 'Gameplay', icon: 'settings' },
          { key: 'motd', label: 'MOTD', icon: 'image' },
        ];
      },
      backupPolicyLabel() {
        const options = this.server?.backup_options || {};
        if (options.enabled === false) return 'Paused for this server';
        const minutes = Number(options.interval_minutes) || 360;
        const every = minutes % 60 === 0 ? (minutes === 60 ? 'Every hour' : 'Every ' + minutes / 60 + ' hours') : 'Every ' + minutes + ' minutes';
        return every + ' · ' + (options.retention || 2) + ' recovery points' + (options.only_when_used !== false ? ' · skipped while the server has not run' : '');
      },
      /** Server workspaces open inside the page instead of as a popup. */
      docked() {
        // Phones open workspaces as sheets over the page instead of docking them next to the console.
        return !!this.server && !!this.modal && !this.phone && (this.modal === 'external' || this.widgets.some((widget) => widget.key === this.modal));
      },
      /** Moves the workspace shell into the page block while docked, and back to the root otherwise. */
      syncDock(dock, docked) {
        const layer = this.$root?.querySelector?.('.ps-modal-layer');
        if (!layer) return;
        if (docked && layer.parentElement !== dock) dock.appendChild(layer);
        if (!docked && layer.parentElement === dock) this.$root.appendChild(layer);
        this.$nextTick?.(() => { const out = this.$refs?.output; if (out) out.scrollTop = out.scrollHeight; });
      },
      /** Animates a layout change (console moving between the block and the corner) where the browser can. */
      async layoutTransition(change) {
        const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        if (typeof document === 'undefined' || !document.startViewTransition || reduced) return change();
        const transition = document.startViewTransition(async () => {
          change();
          await this.$nextTick();
        });
        // A skipped transition rejects these too (newer Chromium); the change itself already ran.
        transition.ready.catch(() => {});
        transition.finished.catch(() => {});
        try { await transition.updateCallbackDone; } catch {}
      },
      /** Colour banner: inline style for a chosen colour, {} for the accent-based one, null for image banners. */
      bannerColor(s) {
        const id = String(s?.background_id || '');
        if (id === 'color') return {};
        const match = id.match(/^color:(#[0-9a-f]{6})$/i);
        return match ? { '--ps-banner': match[1] } : null;
      },
      externalPage: null,
      /** Opens a page from another plugin in the server page's middle block. */
      async openExternal(page) {
        if (this.busy || !page?.url) return;
        if (this.modal === 'external' && this.externalPage?.key === page.key) return;
        const change = () => { this.externalPage = page; this.modal = 'external'; };
        if (this.docked() || this.phone) change();
        else await this.layoutTransition(change);
        this.loading = true;
      },
      widgetTone(key) {
        return { settings: '#e0a050', players: '#6ea8fe', plugins: '#b48cf2', mods: '#b48cf2', files: '#5cc8c0', backups: '#7ad08a', schedules: '#f5b84a', software: '#c084fc', connection: '#60a5fa', performance: '#f2707a', web: '#38bdf8', activity: '#9a9aa6', advanced: '#9a9aa6' }[key] || 'var(--ps-accent)';
      },
      async open(key) {
        if (this.busy || !this.showWidget(key)) return;
        const request = ++this.modalRequest;
        if (this.server && !this.phone && !this.docked() && this.widgets.some((widget) => widget.key === key)) await this.layoutTransition(() => { this.modal = key; });
        else this.modal = key;
        this.loading = true;
        const current = () => !this.destroyed && this.modalRequest === request && this.modal === key;
        const load = async (fn) => {
          const value = await fn();
          if (!current()) throw new Error('Workspace closed');
          return value;
        };
        try {
          if (key === 'backups') this.backups = await load(() => wire.backupList());
          if (key === 'web') this.website = await load(() => wire.website());
          if (key === 'players') this.playerLists = await load(() => wire.players());
          if (['mods', 'plugins'].includes(key)) {
            this.packageQuery = '';
            this.packageResults = [];
            this.packageVersion = '';
            this.installed = await load(() => wire.installedPackages());
            if (this.installed.context?.kind !== key) throw new Error('This server does not support ' + key + '.');
            this.packageVersion = this.installed.context?.version || '';
          }
          if (['advanced', 'software', 'connection'].includes(key) && (key !== 'software' || this.server?.minecraft)) {
            this.advanced = await load(() => (key === 'connection' ? wire.connectionData() : wire.advancedData()));
            this.requestedSubdomain = this.advanced.requested_subdomain || this.advanced.suggested_subdomain || '';
            if (key === 'connection' && this.server && this.advanced.address) this.server.address = this.advanced.address;
          }
          if (['settings', 'performance'].includes(key)) this.fillSettings();
          if (key === 'settings') {
            this.settingsTab = 'general';
            this.aliasDraft = this.server?.alias || '';
          }
          if (key === 'backups') this.backupSettings = { only_when_used: true, ...this.server?.backup_options };
        } catch (e) {
          if (current()) {
            this.toast(e?.message || 'This section is unavailable.', 'danger');
            this.modal = null;
          }
        } finally {
          if (this.modalRequest === request) {
            this.loading = false;
            this.focusModal();
          }
        }
      },
      /** Phones: drag a sheet down by its header to close it (close() still asks about unsaved work). */
      sheetStart(event) {
        if (!this.phone || this.docked() || event.touches.length !== 1 || !event.target.closest('.ps-modal-head') || event.target.closest('button, a, input')) return;
        this.sheetDrag = { y: event.touches[0].clientY, dy: 0, el: event.currentTarget };
        this.sheetDrag.el.style.transition = 'none';
      },
      sheetMove(event) {
        if (!this.sheetDrag) return;
        this.sheetDrag.dy = Math.max(0, event.touches[0].clientY - this.sheetDrag.y);
        this.sheetDrag.el.style.transform = this.sheetDrag.dy ? 'translateY(' + this.sheetDrag.dy + 'px)' : '';
      },
      sheetEnd() {
        const drag = this.sheetDrag;
        if (!drag) return;
        this.sheetDrag = null;
        drag.el.style.transition = '';
        drag.el.style.transform = '';
        if (drag.dy > 110) this.close();
      },
      async close() {
        if (this.busy || this.closingCreator) return;
        if (this.fileEditorDirty && !(await this.ask({ title: 'Discard unsaved file changes?', message: 'Your edits in the file editor have not been saved.', confirmLabel: 'Discard changes', tone: 'danger' }))) return;
        this.fileEditorUrl = null;
        this.fileEditorDirty = false;
        this.sftpOpen = false;
        const creating = this.modal === 'create', changedSettings = this.modal === 'panel-settings' && this.settingsChanged;
        const backToInbox = this.modal === 'admin-page' && this.adminPage?.returnTo === 'inbox';
        if (creating) {
          this.closingCreator = true;
          try {
            const save = this.$root?.querySelector('iframe[title="Create server"]')?.contentWindow?.sscSaveDraft;
            if (save) await save();
            else if (!(await this.ask({ title: 'Leave the creator?', message: 'Unsaved changes may be lost.', confirmLabel: 'Leave' }))) return;
          } catch {
            this.toast('Draft could not be saved. Please try again before closing.', 'danger');
            return;
          } finally {
            this.closingCreator = false;
          }
        }
        this.modalRequest++;
        if (this.docked()) await this.layoutTransition(() => { this.modal = null; });
        else this.modal = null;
        this.loading = false;
        if (creating) this.refreshOverview();
        if (changedSettings) location.reload();
        if (backToInbox) this.openAllNotifications();
      },

      /* Console --------------------------------------------------------------------------------- */
      async connect() {
        if (this.destroyed || !this.server?.permissions['websocket.connect']) return;
        const retry = () => {
          // Back off from 4 seconds up to 5 minutes while Wings is unreachable.
          this.reconnectDelay = Math.min(300000, this.reconnectDelay ? this.reconnectDelay * 2 : 4000);
          if (!this.destroyed) this.reconnect = setTimeout(() => this.connect(), this.reconnectDelay);
        };
        try {
          const auth = await wire.socket();
          if (this.destroyed) return;
          this.networkPrevious = null;
          this.networkRates = { rx_per_second: null, tx_per_second: null };
          const socket = new WebSocket(auth.url);
          this.ws = socket;
          socket.onopen = () => socket.send(JSON.stringify({ event: 'auth', args: [auth.token] }));
          socket.onmessage = async (msg) => {
            let data;
            try {
              data = JSON.parse(msg.data);
            } catch {
              return;
            }
            const { event, args = [] } = data;
            if (event === 'auth success') {
              this.connected = true;
              this.reconnectDelay = 0;
              this.renderLogs();
              socket.send(JSON.stringify({ event: 'send logs', args: [null] }));
              socket.send(JSON.stringify({ event: 'send stats', args: [null] }));
            }
            if (['console output', 'install output', 'daemon error'].includes(event)) {
              const line = String(args[0] ?? '').replace(/\x1b\[[0-9;?]*[ -/]*[@-~]/g, '');
              if (event === 'install output') this.installLines++;
              this.logs.push(line);
              if (this.logs.length > 1200) this.logs.splice(0, this.logs.length - 1200);
              this.renderLogs();
              if (event === 'console output' && /agree to the EULA/i.test(line)) this.promptEula();
            }
            if (event === 'status') {
              this.status = args[0];
              if (this.status === 'offline') {
                this.networkPrevious = null;
                this.networkRates = { rx_per_second: 0, tx_per_second: 0 };
                this.stats.network = { rx_bytes: 0, tx_bytes: 0 };
                this.stats.cpu_absolute = 0;
                this.stats.memory_bytes = 0;
                this.server.players = 0;
                this.server.player_names = [];
              }
            }
            if (event === 'stats') {
              try {
                const v = typeof args[0] === 'string' ? JSON.parse(args[0]) : args[0];
                Object.assign(this.stats, v);
                this.recordNetwork(v.network);
                if (!this.networkPrevious) this.stats.network = null;
                for (const [key, field] of [['cpu', 'cpu_absolute'], ['ram', 'memory_bytes'], ['disk', 'disk_bytes']]) {
                  this.series[key].push(Number(v[field] || 0));
                  if (this.series[key].length > 30) this.series[key].shift();
                }
              } catch {}
            }
            if (['token expiring', 'token expired'].includes(event)) {
              try {
                const refresh = await wire.socket();
                socket.send(JSON.stringify({ event: 'auth', args: [refresh.token] }));
              } catch {
                socket.close();
              }
            }
            if (event === 'install started') this.installCompleted = false;
            if (event === 'install completed') {
              this.installCompleted = true;
              this.refreshServerInfo();
            }
            // Wings matched the EULA line itself (Pelican's console features): ask in Simplify's own way.
            if (event === 'feature match') {
              try {
                const match = typeof args[0] === 'string' ? JSON.parse(args[0]) : args[0];
                if (match?.key === 'eula') this.promptEula();
              } catch {}
            }
          };
          socket.onerror = () => (this.connected = false);
          socket.onclose = () => {
            this.connected = false;
            retry();
          };
        } catch {
          this.connected = false;
          retry();
        }
      },
      renderLogs() {
        this.$nextTick(() => {
          const el = this.$refs.output;
          if (!el) return;
          const text = this.logs.join('\n');
          if (el.textContent === text) return;
          const bottom = el.scrollHeight - el.scrollTop - el.clientHeight < 80;
          el.textContent = text;
          if (bottom) el.scrollTop = el.scrollHeight;
        });
      },
      /** The Explain button shows once an admin saved an AI API key, for people who may watch this console. */
      canExplain() {
        return !!(this.boot.explain_errors && this.server?.permissions?.['websocket.connect']);
      },
      /** What the signed-in user may have explained here: the console, and for root admins the panel and Wings logs. */
      explainSources() {
        return (this.boot.explain_sources || []).filter((source) => source !== 'console' || this.canExplain());
      },
      explainLabel(source) {
        return { console: 'Console', panel: 'Panel log', wings: 'Wings log' }[source] || source;
      },
      /** The same name inside a sentence: "the last lines of the Wings log". */
      explainNoun(source) {
        return { console: 'console', panel: 'panel log', wings: 'Wings log' }[source] || 'log';
      },
      /** Sends the last lines of a log (read again by the panel) to the chosen AI and shows the answer. */
      async explainError(source) {
        source = source || this.explain?.source || this.explainSources()[0];
        if (!this.explainSources().includes(source) || this.explain?.loading) return;
        const request = (this.explainRequest = (this.explainRequest || 0) + 1);
        this.explain = { source, loading: true, error: null, answer: null };
        let result;
        try {
          result = await wire.explainConsole(source);
        } catch {
          result = { ok: false, error: 'The panel could not answer. Try again in a moment.' };
        }
        if (this.explainRequest !== request || !this.explain) return;
        this.explain = result?.ok
          ? { source, loading: false, error: null, answer: { found: result.found !== false, summary: result.summary || '', issues: result.issues || [], lines: result.lines, model: result.model } }
          : { source, loading: false, error: result?.error || 'No explanation is available right now.', answer: null };
      },
      closeExplain() {
        this.explainRequest = (this.explainRequest || 0) + 1;
        this.explain = null;
      },
      sendCommand() {
        const cmd = this.command.trim();
        if (!cmd || !this.connected || !this.server.permissions['control.console']) return;
        this.ws.send(JSON.stringify({ event: 'send command', args: [cmd] }));
        this.history = [cmd, ...this.history].slice(0, 50);
        this.historyIndex = -1;
        this.command = '';
      },
      /** Puts a quick command in the command bar (a trailing space means: type the rest), ready to send. */
      useQuickCommand(text) {
        if (!this.connected || !this.server?.permissions['control.console']) return;
        this.command = text;
        this.$nextTick(() => {
          const input = this.$root.querySelector('.ps-command input');
          input?.focus();
          input?.setSelectionRange(text.length, text.length);
        });
      },
      phoneMoreActive() {
        return (!!this.modal && this.widgets.some((w) => w.key === this.modal && !PHONE_SECTIONS.includes(w.key))) || this.modal === 'external';
      },
      historyBack() {
        this.historyIndex = Math.min(this.historyIndex + 1, this.history.length - 1);
        this.command = this.history[this.historyIndex] || '';
      },
      historyForward() {
        this.historyIndex = Math.max(-1, this.historyIndex - 1);
        this.command = this.history[this.historyIndex] || '';
      },
      metricValue(key) {
        if (key === 'network') return this.rateLabel(this.networkRates.rx_per_second, this.networkRates.tx_per_second);
        const v = this.stats[{ cpu: 'cpu_absolute', ram: 'memory_bytes', disk: 'disk_bytes' }[key]];
        if (v === null || v === undefined) return '—';
        return key === 'cpu' ? Number(v).toFixed(1) + '%' : this.bytes(v);
      },
      metricLimit(key) {
        if (key === 'network') {
          const n = this.stats.network;
          return 'Used ' + (n?.rx_bytes == null || n?.tx_bytes == null ? '—' : '↓ ' + this.trafficBytes(n.rx_bytes) + ' / ↑ ' + this.trafficBytes(n.tx_bytes)) + ' · since server start';
        }
        if (key === 'cpu') return `of ${this.server?.cpu || '∞'}% allocated`;
        return `of ${this.bytes(((key === 'ram' ? this.server?.memory : this.server?.disk) || 0) * 1048576)} allocated`;
      },
      spark(key) {
        const a = this.series[key];
        if (a.length < 2) return 'M0 34 L200 34';
        const max = Math.max(...a, 1);
        return a.map((v, i) => `${i ? 'L' : 'M'}${((i * 200) / (a.length - 1)).toFixed(1)} ${(34 - (v / max) * 30).toFixed(1)}`).join(' ');
      },
      /** Pelican's own install state: installing, install_failed, reinstall_failed, restoring_backup or null. */
      serverSetup() {
        return this.server?.setup_state || null;
      },
      /** True while a new or reinstalled server is not usable yet: the page then shows only the steps and the log. */
      installView() {
        return ['installing', 'install_failed', 'reinstall_failed', 'restoring_backup'].includes(this.serverSetup());
      },
      installFailed() {
        return ['install_failed', 'reinstall_failed'].includes(this.serverSetup());
      },
      installKilled() {
        return this.installFailed() && !!this.server?.install_killed;
      },
      /** Kill: only the first installation of a new server, by its owner (a reinstall would lose the server's files). */
      canKillInstall() {
        return this.serverSetup() === 'installing' && !!this.server?.install_fresh && !!this.server?.owner && !!this.server?.permissions?.['control.stop'];
      },
      installTitle() {
        if (this.serverSetup() === 'restoring_backup') return 'Restoring a backup…';
        if (this.installKilled()) return 'Installation stopped';
        if (this.installFailed()) return 'The installation did not finish';
        return 'Setting up ' + (this.server?.alias || this.server?.name);
      },
      installText() {
        if (this.serverSetup() === 'restoring_backup') return 'Everything opens again when the restore is done.';
        if (this.installKilled()) return 'You stopped the installation and its files were removed. Change the resources if needed, then try again.';
        if (this.installFailed()) return 'The last lines of the installation log are below. Nothing was started.';
        return 'This takes a few minutes, longer for big games. You can leave this page; the server keeps installing.';
      },
      /**
       * The steps as far as Pelican and Wings report them: the server exists, Wings prepares the installer
       * (no output yet), the install script runs (output arrives), Wings reports it finished.
       */
      installSteps() {
        const software = this.server?.software || this.server?.pack?.modpack_name || 'the game';
        const reached = this.installFailed() ? 2 : this.installCompleted ? 3 : this.installLines || this.server?.install_log ? 2 : 1;
        const steps = [
          { key: 'created', label: 'Server created', detail: 'Resources reserved' },
          { key: 'prepare', label: 'Preparing', detail: 'Wings gets the installer ready' },
          { key: 'install', label: 'Installing ' + software, detail: this.installKilled() ? 'Stopped' : this.installFailed() ? 'Stopped with an error' : 'Downloading the game files' },
          { key: 'ready', label: 'Ready to start', detail: 'Everything opens' },
        ];
        return steps.map((step, index) => ({ ...step, state: index < reached ? 'done' : index > reached ? 'todo' : this.installFailed() ? 'failed' : 'active' }));
      },
      /** Time since Pelican started the installation, such as "2:14". */
      installElapsed() {
        const since = this.server?.install_since;
        if (!since || this.serverSetup() !== 'installing') return '';
        const seconds = Math.max(0, Math.floor(this.installNow / 1000 - since));
        return Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
      },
      /** How long this egg usually takes on this panel (from earlier installations), such as "usually about 2 min". */
      installTypical() {
        const seconds = this.server?.install_typical;
        return seconds ? 'usually about ' + Math.max(1, Math.round(seconds / 60)) + ' min' : '';
      },
      installLogText() {
        return this.logs.join('\n');
      },
      async toggleInstallNotify() {
        if (!this.installNotify && typeof Notification === 'function' && Notification.permission === 'default') {
          try { await Notification.requestPermission(); } catch {}
        }
        this.installNotify = !this.installNotify;
      },
      notifyInstalled() {
        const name = this.server?.alias || this.server?.name;
        try {
          if (typeof Notification === 'function' && Notification.permission === 'granted' && document.hidden) new Notification(name + ' is ready', { body: 'You can now start your server.' });
        } catch {}
        this.toast(name + ' is ready. You can now start it.', 'ok');
      },
      installFiles() {
        window.open(this.server.files_url, '_blank', 'noopener');
      },
      installStartup() {
        window.open(this.server.native_sections?.startup, '_blank', 'noopener');
      },
      async killInstall() {
        if (!(await this.ask({ title: 'Stop the installation?', message: 'The installer stops right away and the files it downloaded are removed. The server stays here: change its resources if needed and try again, or delete it.', confirmLabel: 'Kill', tone: 'danger' }))) return;
        const result = await this.run(() => wire.killInstallation(), 'Installation stopped');
        if (result === SKIPPED || !result) return;
        Object.assign(this.server, result);
        this.logs.push('[Simplify]: Installation stopped.');
        this.renderLogs();
      },
      async cancelInstall() {
        const name = this.server?.alias || this.server?.name;
        if (!(await this.ask({ title: 'Delete ' + name + '?', message: (this.serverSetup() === 'installing' ? 'The installation stops and the server' : 'The server') + ' is deleted with all its files. This cannot be undone.', confirmLabel: 'Delete server', tone: 'danger' }))) return;
        await this.run(() => wire.deleteSetupServer(this.server.name), 'Server deleted');
      },
      async reinstall() {
        if (!(await this.ask({ title: 'Try the installation again?', message: 'Pelican runs the install script again. Files the script replaces are overwritten.', confirmLabel: 'Reinstall' }))) return;
        const result = await this.run(() => wire.reinstallServer(), 'Installation started');
        if (result !== SKIPPED && result) {
          this.installLines = 0;
          this.installCompleted = false;
          this.logs = [];
          this.renderLogs();
          Object.assign(this.server, result);
        }
      },
      async refreshServerInfo() {
        if (globalThis.document?.hidden || this.busy || this.refreshingInfo || this.destroyed) return;
        this.refreshingInfo = true;
        try {
          const info = await wire.refreshInfo();
          if (this.destroyed) return;
          const wasSettingUp = this.serverSetup() === 'installing';
          Object.assign(this.server, info);
          if (wasSettingUp && !this.serverSetup()) {
            this.installReady = true;
            if (this.installNotify) this.notifyInstalled();
            this.refitWorkspace();
            this.finishInstall();
          }
          this.renderLogs();
        } catch {
        } finally {
          this.refreshingInfo = false;
        }
      },
      /** Minecraft stops until its EULA is accepted: the banner above the server card asks for it (once per page view). */
      promptEula() {
        const p = this.server?.permissions || {};
        if (this.eulaPrompted || !p['file.update'] || !p['control.start']) return;
        this.eulaPrompted = true;
        this.eulaNeeded = true;
      },
      async acceptEula() {
        const result = await this.run(() => wire.acceptEula(), 'EULA accepted, start sent');
        if (result === SKIPPED) return;
        this.eulaNeeded = false;
        this.installReady = false;
      },
      /** After an installation finished on this page: the creator's settings are applied, then the EULA is checked once. */
      async finishInstall() {
        try {
          const result = await wire.finishInstall();
          if (result?.eula === false) this.promptEula();
        } catch {}
      },
      /** The banner's main button: accept the EULA first when it is still needed, otherwise just start. */
      readyStart() {
        return this.eulaNeeded ? this.acceptEula() : this.power('start');
      },
      async power(action) {
        if (action === 'kill' && !(await this.ask({ title: 'Force stop this server?', message: 'The server stops immediately. Unsaved game progress may be lost.', confirmLabel: 'Force stop', tone: 'danger' }))) return;
        const result = await this.run(() => wire.power(action), action === 'restart' && this.server.pending ? 'Restart queued' : POWER_LABELS[action] + ' sent');
        if (result !== SKIPPED && ['restart', 'stop'].includes(action) && this.server.pending) Object.assign(this.server, await wire.refreshInfo());
      },

      /* Appearance -------------------------------------------------------------------------------- */
      openBackground() {
        if (this.busy || this.modal || !this.server?.permissions?.['settings.rename']) return;
        this.colorMenu = false;
        this.backgroundDraft = this.server.background_id || 'auto';
        this.openPopup('background');
      },
      async uploadBanner() {
        const result = await this.run(() => wire.uploadServerBanner(), 'Banner uploaded');
        if (result === SKIPPED || !result) return;
        this.uploadedBackgrounds = result.uploaded_backgrounds || [];
        this.backgroundDraft = result.background_id;
        Object.assign(this.server, result);
      },
      async deleteBanner(id) {
        if (this.busy || !(await this.ask({ title: 'Remove this uploaded banner?', message: 'Servers using it return to their automatic background.', confirmLabel: 'Remove banner', tone: 'danger' }))) return;
        const result = await this.run(() => wire.deleteServerBanner(id), 'Banner removed');
        if (result === SKIPPED || !result) return;
        this.uploadedBackgrounds = result.uploaded_backgrounds || [];
        this.backgroundDraft = result.background_id;
        Object.assign(this.server, result);
      },
      async saveBackground() {
        if (this.busy || !this.server?.permissions?.['settings.rename']) return;
        const result = await this.run(() => wire.saveServerBackground(this.backgroundDraft), 'Server background saved');
        if (result === SKIPPED || !result || !Object.hasOwn(result, 'background')) return;
        Object.assign(this.server, result);
      },
      toggleColorMenu(button) {
        if (this.colorMenu) {
          this.colorMenu = false;
          return;
        }
        this.colorDraft = this.server?.color || this.currentAccentHex();
        const box = button.getBoundingClientRect();
        const scale = this.uiScale || 1;
        this.colorMenuStyle = { left: Math.max(12, Math.min(box.left - 200, innerWidth - 260)) / scale + 'px', top: Math.max(12, Math.min(box.bottom + 8, innerHeight - 240)) / scale + 'px' };
        this.colorMenu = true;
      },
      async commitServerColor() {
        if (await this.saveServerColor(this.colorDraft)) this.colorMenu = false;
      },
      async saveServerColor(color) {
        if (!this.server?.permissions?.['settings.rename'] || this.busy) return false;
        const saved = await this.run(() => wire.saveServerColor(color), 'Server color saved');
        if (typeof saved !== 'string' || !/^#[0-9a-f]{6}$/i.test(saved)) return false;
        this.server.color = saved;
        try {
          this.boot.available_colors = await wire.availableServerColors();
        } catch {}
        return true;
      },

      /* Settings, resources and restarts -------------------------------------------------------- */
      async saveSettings() {
        const state = { ...this.settings };
        const resources = this.modal === 'performance';
        if (!resources) for (const key of ['memory_gb', 'disk_mb', 'cpu']) delete state[key];
        const result = await this.run(() => (resources ? wire.saveResourceLimits({ memory_gb: state.memory_gb, cpu: state.cpu, disk_mb: state.disk_mb }) : wire.saveSettings(state)));
        if (result === SKIPPED) return;
        try {
          Object.assign(this.server, await wire.refreshInfo());
        } catch {}
        if (result?.changed === false) this.toast('No changes to save.', 'info');
        else if (result?.pending && this.server?.pending) this.toast('Changes saved. Stop or restart to apply them.', 'ok');
        else if (resources && this.installFailed()) this.toast('Resources changed. They are used when you try again.', 'ok');
        else this.toast('Changes applied. They will load at the next start.', 'ok');
      },
      async renameServer() {
        const name = this.name;
        const result = await this.run(() => wire.renameServer(name), 'Server renamed');
        if (result !== SKIPPED) this.server.name = name;
      },
      async discard() {
        const result = await this.run(() => wire.discard(), this.server?.pending?.file_only ? 'Restart reminder dismissed' : 'Pending changes discarded');
        if (result !== SKIPPED) this.server.pending = null;
      },
      async restartChanges(minutes) {
        const result = await this.run(() => wire.restartChanges(Number(minutes)), minutes ? 'Restart scheduled' : 'Restart queued. Settings will be applied when the server is stopped.');
        if (result === SKIPPED) return;
        if (this.modal === 'restart') this.modal = null;
        try {
          Object.assign(this.server, await wire.refreshInfo());
        } catch {}
      },
      async saveListing(value) {
        const listing = this.server?.website_listing;
        if (!listing) return;
        const result = await this.run(() => wire.saveListing(value), value ? 'Shown on the website' : 'Hidden from the website');
        if (result !== SKIPPED) listing.visible = result === true;
      },
      async saveWebsite() {
        const state = { ...this.website };
        delete state.display_name;
        await this.run(() => wire.saveWebsite(state), 'Website settings saved');
      },
      async saveSubdomain() {
        if (!this.advanced?.subdomains) {
          await this.run(() => wire.subdomain(this.requestedSubdomain), 'Subdomain request saved. DNS will be connected later.');
          return;
        }
        const name = this.requestedSubdomain.trim().toLowerCase();
        const result = await this.run(() => wire.subdomain(name));
        if (result === SKIPPED) return;
        if (result?.error) return this.toast(result.error, 'danger');
        this.toast(name ? 'Subdomain saved. Connecting…' : 'Subdomain removed', 'ok');
        // Cloudflare is called right after the response; one follow-up read shows the outcome.
        await new Promise((resolve) => setTimeout(resolve, 2500));
        await this.refreshConnection();
      },
      async refreshConnection() {
        if (this.modal !== 'connection') return;
        try {
          this.advanced = await wire.connectionData();
          this.requestedSubdomain = this.advanced.requested_subdomain || this.advanced.suggested_subdomain || '';
          if (this.server && this.advanced.address) this.server.address = this.advanced.address;
        } catch {}
      },
      subdomainStatus() {
        const state = this.advanced?.subdomain || {};
        if (!state.name) return null;
        return {
          active: { tone: 'is-ok', label: 'Connected' },
          pending: { tone: 'is-info', label: 'Connecting…' },
          removing: { tone: 'is-info', label: 'Removing…' },
          error: { tone: 'is-danger', label: 'Not connected' },
        }[state.status] || null;
      },

      /* Players and packages ------------------------------------------------------------------- */
      async playerAction(action) {
        if (!/^[A-Za-z0-9_]{1,16}$/.test(this.playerName)) {
          this.toast('Enter a valid Minecraft username.', 'warn');
          return;
        }
        if (['OP', 'Ban', 'Kick'].includes(action) && !(await this.ask({ title: action + ' ' + this.playerName + '?', message: action === 'OP' ? 'Operators can run every server command.' : 'The player is removed from the server.', confirmLabel: action, tone: action === 'OP' ? 'primary' : 'danger' }))) return;
        const prefixes = { Whitelist: 'whitelist add', Unwhitelist: 'whitelist remove', OP: 'op', DeOP: 'deop', Kick: 'kick', Ban: 'ban', Unban: 'pardon' };
        this.command = prefixes[action] + ' ' + this.playerName;
        this.sendCommand();
        this.toast('Command sent. The console shows the result.', 'ok');
        setTimeout(async () => {
          try {
            this.playerLists = await wire.players();
          } catch {}
        }, 1800);
      },
      async searchPackages() {
        const result = await this.run(() => wire.packageSearch(this.packageQuery, this.packageVersion));
        if (result === SKIPPED || !result) return;
        this.packageResults = result.items;
        this.packageVersion = result.context.version;
      },
      async installPackage(id) {
        const result = await this.run(() => wire.installPackage(id, this.packageVersion), 'Package installed. Start the server to load it.');
        if (result !== SKIPPED) this.installed = await wire.installedPackages();
      },
      async removePackage(file) {
        if (!(await this.ask({ title: 'Remove ' + file + '?', message: 'A copy is kept in the server package backup folder.', confirmLabel: 'Remove', tone: 'danger' }))) return;
        const result = await this.run(() => wire.removePackage(file), 'Package removed');
        if (result !== SKIPPED) this.installed = await wire.installedPackages();
      },
      tracked(file) {
        return Object.values(this.installed.tracked || {}).find((x) => x.filename === file);
      },
      async searchPacks() {
        const result = await this.run(() => wire.packSearch(this.packQuery));
        this.packs = result === SKIPPED ? [] : result || [];
      },
      async selectPack(p) {
        this.software.modpack_project = p.key;
        this.software.modpack_version = '';
        const result = await this.run(() => wire.packVersions(p.key));
        this.packReleases = result === SKIPPED ? {} : result || {};
        this.packs = [p];
      },
      async changeSoftware() {
        if (this.softwareConfirm !== this.server.name) {
          this.toast('Enter the exact server name.', 'warn');
          return;
        }
        if (!(await this.ask({ title: 'Reinstall this server?', message: 'The selected software is installed. Existing files may change.', confirmLabel: 'Reinstall', tone: 'danger' }))) return;
        await this.run(() => wire.changeSoftware(this.software, this.softwareConfirm), 'Software installation started');
      },
      async deleteServer() {
        if (this.busy) return;
        if (!(await this.ask({ title: 'Delete ' + this.server.name + '?', message: 'The server and all of its files are removed permanently.', confirmLabel: 'Delete server', tone: 'danger' }))) return;
        this.stopServerTimers();
        const result = await this.run(() => wire.deleteServer(this.deleteConfirm));
        if (result === SKIPPED) {
          this.startServerTimers();
          return;
        }
        this.destroyed = true;
        clearTimeout(this.reconnect);
        if (this.ws) {
          this.ws.onclose = null;
          this.ws.close();
        }
        location.href = boot.list_url;
      },

      /* Backups ------------------------------------------------------------------------------------ */
      async saveBackups() {
        const result = await this.run(() => wire.backupOptions(this.backupSettings), 'Backup policy saved');
        if (result !== SKIPPED) this.server.backup_options = { ...this.backupSettings };
      },
      async downloadBackup(uuid) {
        const url = await this.run(() => wire.downloadBackup(uuid));
        if (url !== SKIPPED && url) location.href = this.safeLink(url);
      },
      async restoreBackup(uuid) {
        const confirmation = await window.Simplify?.prompt?.({ title: 'Restore this backup?', message: 'Stop the server first. Current files are replaced by the backup.', requireText: this.server.name, confirmLabel: 'Restore backup', tone: 'danger' });
        if (confirmation === null || confirmation === undefined) return;
        await this.run(() => wire.restoreBackup(uuid, confirmation), 'Backup restoration started.');
      },
      bannerUploadChanged(event) {
        const file = event.target.files?.[0], limit = this.boot.banner_upload_bytes ?? 5242880;
        if (file && file.size > limit) {
          event.stopImmediatePropagation();
          event.target.value = '';
          this.toast('This banner exceeds the panel upload limit (' + this.bytes(limit) + '). See System checks.', 'warn');
        }
      },
      sftpLink() {
        try {
          const url = new URL(this.server?.sftp?.url);
          return url.protocol === 'sftp:' ? url.href : '';
        } catch {
          return '';
        }
      },

      /* Files ---------------------------------------------------------------------------------------- */
      fileEditUrl(url) {
        try {
          const base = new URL(this.server.files_url, location.origin), target = new URL(url, location.origin);
          const prefix = base.pathname.replace(/\/$/, '') + '/edit/';
          return target.origin === base.origin && target.pathname.startsWith(prefix) && target.pathname.length > prefix.length ? target : null;
        } catch {
          return null;
        }
      },
      async openFileEditor(url) {
        if (this.busy) return;
        if (this.fileEditorDirty && !(await this.ask({ title: 'Discard unsaved file changes?', message: 'Open the other file without saving.', confirmLabel: 'Discard changes', tone: 'danger' }))) return;
        url.searchParams.set('simplify_embed', '1');
        this.fileEditorDirty = false;
        this.fileEditorSaving = false;
        this.fileEditorRevision = null;
        const prefix = new URL(this.server.files_url, location.origin).pathname.replace(/\/$/, '') + '/edit/';
        this.fileEditorPath = '/' + decodeURIComponent(url.pathname.slice(prefix.length).replace(/\/$/, ''));
        this.fileEditorUrl = url.href;
        wire.fileRevision(this.fileEditorPath)
          .then((hash) => this.fileEditorUrl === url.href && (this.fileEditorRevision = hash))
          .catch(() => this.toast('The file revision could not be checked. Reopen the editor before saving.', 'warn'));
      },
      filesFrameLoaded(frame) {
        this.styleFrame(frame, 'files');
        try {
          const doc = frame.contentDocument;
          if (doc._psFilesClick) return;
          const click = (event) => {
            if (event.target.closest?.('button,input,select,textarea,label') && !event.target.closest?.('a[href]')) return;
            const anchor = event.target.closest?.('a[href]') || event.target.closest?.('.fi-ta-row')?.querySelector('a[href]');
            const url = anchor && this.fileEditUrl(anchor.href);
            if (!url) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            return this.openFileEditor(url);
          };
          doc.addEventListener('click', click, true);
          doc._psFilesClick = click;
        } catch {
          this.toast('The file workspace could not be opened.', 'danger');
        }
      },
      /** Clicks Pelican's (hidden) "Save and close", which runs the revision check above first. */
      saveFileEditor() {
        try {
          const doc = document.querySelector('.ps-file-editor iframe')?.contentDocument;
          const button = doc && [...doc.querySelectorAll('button[wire\\:click]')].find((b) => (b.getAttribute('wire:click') || '').includes('fm_save_and_close'));
          if (button) return button.click();
        } catch {}
        this.toast('The editor is not ready yet.', 'warn');
      },
      async closeFileEditor() {
        if (this.busy) return;
        if (this.fileEditorDirty && !(await this.ask({ title: 'Discard unsaved file changes?', message: 'Your edits have not been saved.', confirmLabel: 'Discard changes', tone: 'danger' }))) return;
        this.fileEditorUrl = null;
        this.fileEditorDirty = false;
      },
      fileEditorLoaded(frame) {
        try {
          if (!this.fileEditorUrl) return;
          const url = frame.contentWindow.location.href;
          if (url === 'about:blank') return;
          if (!this.fileEditUrl(url)) {
            const target = new URL(url), base = new URL(this.server.files_url, location.origin);
            if (target.origin !== base.origin || (!target.pathname.startsWith(base.pathname.replace(/\/$/, '') + '/') && target.pathname !== base.pathname)) {
              this.toast('The editor could not be opened.', 'danger');
              return;
            }
            this.fileEditorUrl = null;
            this.fileEditorDirty = false;
            if (this.fileEditorSaving) {
              this.toast('File saved', 'ok');
              wire.fileChanged(this.fileEditorPath, this.fileEditorRevision)
                .then((info) => Object.assign(this.server, info))
                .catch(() => this.toast('File saved, but the restart reminder could not be recorded. Refresh the server page.', 'warn'));
              try {
                this.$refs.filesFrame.contentWindow.location.reload();
              } catch {}
            } else this.toast('The file could not be edited. See the file manager warning.', 'warn');
            this.fileEditorSaving = false;
            return;
          }
          this.styleFrame(frame, 'editor');
          const doc = frame.contentDocument;
          const saveButton = () => [...doc.querySelectorAll('button[wire\\:click]')].find((button) => (button.getAttribute('wire:click') || '').includes('fm_save_and_close'));
          // Simplify's header takes over Save/Close; Pelican's own footer only stays when its save button is not found.
          this.fileEditorExternal = !!saveButton();
          if (this.fileEditorExternal) doc.documentElement.dataset.psEditorActions = 'external';
          for (const button of doc.querySelectorAll('button[wire\\:click]')) {
            const action = button.getAttribute('wire:click') || '';
            if ((action.includes('fm_save') && !action.includes('fm_save_and_close')) || action.includes('fm_cancel')) button.style.display = 'none';
          }
          if (doc._psEditorBound) return;
          doc._psEditorBound = true;
          doc.addEventListener('click', async (event) => {
            const button = event.target.closest?.('button[wire\\:click]');
            if (!(button?.getAttribute('wire:click') || '').includes('fm_save_and_close')) return;
            if (button._psVerified) {
              button._psVerified = false;
              this.fileEditorSaving = true;
              return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            if (this.fileEditorChecking) return;
            if (!this.fileEditorRevision) {
              this.toast('Wait for the file revision check before saving.', 'warn');
              return;
            }
            this.fileEditorChecking = true;
            try {
              if ((await wire.fileRevision(this.fileEditorPath)) !== this.fileEditorRevision) {
                this.toast('This file changed elsewhere. Reopen it before saving to avoid overwriting another change.', 'danger');
                return;
              }
              button._psVerified = true;
              button.click();
            } catch {
              this.toast('Could not verify the file. Your editor remains open.', 'danger');
            } finally {
              this.fileEditorChecking = false;
            }
          }, true);
          doc.addEventListener('input', (event) => event.target.closest?.('.monaco-editor') && (this.fileEditorDirty = true), true);
          doc.addEventListener('keydown', (event) => {
            if (!(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== 's') return;
            const button = saveButton();
            if (!button) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            button.click();
          }, true);
          doc.addEventListener('livewire:navigated', () => this.fileEditorLoaded(frame));
        } catch {
          this.toast('The file editor could not be opened.', 'danger');
        }
      },
      /** Marks links back to the overview inside native pages so the popup can hide them. */
      cleanNativeFrame(doc) {
        const overview = new Set();
        for (const href of [this.boot.list_url, this.boot.native_list_url]) {
          if (!href) continue;
          try {
            const u = new URL(href, location.origin);
            if (u.origin === location.origin) overview.add(u.pathname.replace(/\/$/, '') || '/');
          } catch {}
        }
        for (const link of doc.querySelectorAll?.('a[href]') || []) {
          if (link.closest('.fi-ta,.fi-section,.fi-modal,[role="dialog"]')) continue;
          try {
            const u = new URL(link.getAttribute('href'), location.origin);
            if (u.origin === location.origin && overview.has(u.pathname.replace(/\/$/, '') || '/') && !u.search) link.setAttribute('data-ps-embed-navigation', 'overview');
          } catch {}
        }
      },
      /** Embedded pages theme themselves from their <iframe>; this only syncs theme and tidies native links. */
      styleFrame(frame, kind) {
        try {
          if (['native', 'files', 'editor', 'account'].includes(kind) && frame.contentDocument) this.cleanNativeFrame(frame.contentDocument);
          window.SimplifyTheme?.frame(frame, kind, this.currentAccentHex());
        } catch {
          this.toast('The workspace could not be embedded.', 'danger');
        }
      },
    };
  };
})();
