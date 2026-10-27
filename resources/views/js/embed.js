/* PelicanSimplify website embed: draws the panel's public server list on any website.
   <div id="simplify-servers"></div><script src="https://panel.example.com/simplify/embed.js" defer></script>
   Optional data-* attributes on the script: style (auto|light|dark), accent (#rrggbb), corners (round|square),
   players, modpack, join (on|off), server (short ID: only that server), servers (short IDs, comma separated),
   game (only this game), target (CSS selector). */
(() => {
  const script = document.currentScript;
  if (!script || !script.src) return;
  const origin = new URL(script.src).origin;
  const attr = script.dataset;
  let host = attr.target ? document.querySelector(attr.target) : document.getElementById('simplify-servers');
  if (!host || host.dataset.simplifyEmbed) { host = document.createElement('div'); script.after(host); }
  host.dataset.simplifyEmbed = '1';
  const root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;
  const flag = (value, fallback) => value === undefined ? fallback : !/^(off|false|0|no)$/i.test(value);

  const css = `
    :host{display:block}
    .list{--bg:#fff;--card:#fff;--ink:#18181b;--muted:#71717a;--line:#e4e4e7;--soft:#f4f4f5;--ok:#16a34a;--warn:#ca8a04;
      font:14px/1.45 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink);
      display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr));gap:12px;align-items:start}
    .list.dark{--card:#18181b;--ink:#f4f4f5;--muted:#a1a1aa;--line:#2e2e33;--soft:#232327;--ok:#4ade80;--warn:#facc15}
    @media (prefers-color-scheme:dark){.list.auto{--card:#18181b;--ink:#f4f4f5;--muted:#a1a1aa;--line:#2e2e33;--soft:#232327;--ok:#4ade80;--warn:#facc15}}
    .card{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:14px;display:grid;gap:10px;align-content:start;min-width:0}
    .top{display:flex;gap:10px;align-items:center;min-width:0}
    .icon{width:40px;height:40px;border-radius:calc(var(--r) - 2px);flex:none;object-fit:cover;background:var(--accent);color:#fff;display:grid;place-items:center;font-weight:700}
    .title{display:grid;min-width:0;flex:1}
    .name{font-weight:600;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .sub{color:var(--muted);font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .state{font-size:12px;font-weight:600;color:var(--muted);display:flex;gap:6px;align-items:center;white-space:nowrap}
    .state::before{content:"";width:8px;height:8px;border-radius:50%;background:currentColor}
    .state.online{color:var(--ok)}.state.starting,.state.installing,.state.stopping{color:var(--warn)}
    .pack{display:flex;gap:8px;align-items:center;border:1px solid var(--line);border-radius:calc(var(--r) - 4px);padding:6px 8px;color:inherit;text-decoration:none;min-width:0}
    a.pack:hover{border-color:var(--accent)}
    .pack img,.pack .dot{width:22px;height:22px;border-radius:5px;flex:none;object-fit:cover;background:var(--accent)}
    .pack span{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:13px}
    .pack small{color:var(--muted);font-size:12px}
    .join{display:flex;gap:6px;align-items:center;background:var(--soft);border-radius:calc(var(--r) - 4px);padding:4px 4px 4px 10px;min-width:0}
    .join code{flex:1;min-width:0;font:12.5px ui-monospace,Menlo,Consolas,monospace;overflow-wrap:anywhere}
    .join button{border:0;border-radius:calc(var(--r) - 6px);background:var(--accent);color:#fff;font-family:inherit;font-size:12px;font-weight:600;padding:5px 10px;cursor:pointer}
    .join button:focus-visible,a.pack:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
    .empty{color:var(--muted);font-size:13px}`;

  const el = (tag, props = {}, ...children) => {
    const node = document.createElement(tag);
    for (const [key, value] of Object.entries(props)) {
      if (value === null || value === undefined || value === false) continue;
      if (key === 'class') node.className = value; else if (key === 'text') node.textContent = value; else node.setAttribute(key, value);
    }
    for (const child of children) if (child) node.append(child);
    return node;
  };
  const stateText = { online: 'Online', starting: 'Starting', stopping: 'Stopping', installing: 'Installing', offline: 'Offline' };

  const copy = async (button, text) => {
    try { await navigator.clipboard.writeText(text); button.textContent = 'Copied'; }
    catch (_) { const range = document.createRange(); range.selectNodeContents(button.previousSibling); const selection = getSelection(); selection.removeAllRanges(); selection.addRange(range); button.textContent = 'Selected'; }
    setTimeout(() => { button.textContent = 'Copy'; }, 1600);
  };

  const card = (server, show) => {
    const initials = server.name.split(/\s+/).map((word) => word[0] || '').join('').slice(0, 2).toUpperCase();
    const icon = server.icon ? el('img', { class: 'icon', src: server.icon, alt: '', loading: 'lazy' }) : el('span', { class: 'icon', text: initials, 'aria-hidden': 'true' });
    const players = show.players && server.players !== null && server.state === 'online' ? `${server.players}/${server.max_players} players` : '';
    const detail = [server.game, server.modpack ? '' : (server.version || server.software || ''), players].filter(Boolean).join(' · ');
    const nodes = [el('div', { class: 'top' }, icon,
      el('div', { class: 'title' }, el('span', { class: 'name', text: server.name, title: server.name }), el('span', { class: 'sub', text: detail })),
      el('span', { class: `state ${server.state}`, text: stateText[server.state] || 'Offline' }))];
    if (show.modpack && server.modpack) {
      const pack = server.modpack;
      const inner = [pack.icon ? el('img', { src: pack.icon, alt: '', loading: 'lazy' }) : el('i', { class: 'dot' }),
        el('span', {}, el('b', { text: pack.name }), document.createTextNode(pack.version ? ` ${pack.version}` : '')),
        pack.url ? el('small', { text: 'View ↗' }) : null];
      nodes.push(pack.url ? el('a', { class: 'pack', href: pack.url, target: '_blank', rel: 'noopener' }, ...inner) : el('div', { class: 'pack' }, ...inner));
    }
    if (show.join && server.address) {
      const button = el('button', { type: 'button', text: 'Copy', 'aria-label': `Copy the address of ${server.name}` });
      button.addEventListener('click', () => copy(button, server.address));
      nodes.push(el('div', { class: 'join' }, el('code', { text: server.address }), button));
    }
    return el('article', { class: 'card' }, ...nodes);
  };

  const render = (data) => {
    const options = data.options || {};
    const show = { players: flag(attr.players, options.players !== false), modpack: flag(attr.modpack, options.modpack !== false), join: flag(attr.join, options.join !== false) };
    const style = ['auto', 'light', 'dark'].includes(attr.style) ? attr.style : (options.style || 'auto');
    const accent = /^#[0-9a-fA-F]{6}$/.test(attr.accent || '') ? attr.accent : (options.accent || '#0d9488');
    const corners = (attr.corners || options.corners) === 'square' ? '4px' : '12px';
    let servers = Array.isArray(data.servers) ? data.servers : [];
    if (attr.server) servers = servers.filter((server) => server.id === attr.server);
    if (attr.servers) { const ids = attr.servers.split(','); servers = servers.filter((server) => ids.includes(server.id)); }
    if (attr.game) servers = servers.filter((server) => String(server.game).toLowerCase() === attr.game.toLowerCase());
    const list = el('div', { class: `list ${style}`, style: `--accent:${accent};--r:${corners}`, role: 'list', 'aria-label': `${data.name || 'Game'} servers` });
    for (const server of servers) { const item = card(server, show); item.setAttribute('role', 'listitem'); list.append(item); }
    if (!servers.length) list.append(el('p', { class: 'empty', text: 'No servers to show right now.' }));
    root.replaceChildren(el('style', { text: css }), list);
  };

  const load = () => fetch(`${origin}/simplify/embed/servers.json`, { credentials: 'omit' })
    .then((response) => response.ok ? response.json() : Promise.reject(response.status))
    .then(render)
    .catch(() => { if (!root.querySelector('.list')) root.replaceChildren(el('style', { text: css }), el('p', { class: 'empty', text: 'The server list is not available right now.' })); });
  load();
  // Status and players follow the panel's one-minute cache; hidden tabs do not ask.
  setInterval(() => { if (!document.hidden) load(); }, 60000);
})();
