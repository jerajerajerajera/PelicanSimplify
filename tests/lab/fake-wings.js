// A stand-in for Wings: answers the daemon calls the panel and Simplify make, with a configurable delay per
// request, and counts every request per path so the overview's traffic can be measured.
// node fake-wings.js --port 8090 --servers servers.json --delay 120 --log requests.log
const http = require('http'), fs = require('fs'), url = require('url');
const arg = (name, fallback) => { const i = process.argv.indexOf('--' + name); return i > 0 ? process.argv[i + 1] : fallback; };
const port = Number(arg('port', 8090)), delay = Number(arg('delay', 0)), logFile = arg('log', 'wings-requests.log');
const serversFile = arg('servers', 'servers.json');
const states = ['running', 'running', 'offline', 'starting', 'running'];
const servers = () => { try { return JSON.parse(fs.readFileSync(serversFile, 'utf8')); } catch { return []; } };
const entry = (s, i) => ({ state: states[i % states.length], is_suspended: false,
  utilization: { memory_bytes: 400e6 + i * 50e6, memory_limit_bytes: 2048e6, cpu_absolute: 5 + (i * 7) % 60, network: { rx_bytes: 1e6 * (i + 1) + Date.now() % 100000, tx_bytes: 2e6 * (i + 1) + Date.now() % 100000 }, uptime: 36000, state: states[i % states.length], disk_bytes: 900e6 },
  configuration: { uuid: s.uuid, meta: { name: s.name } } });
const properties = 'motd=§aWelcome to the lab\nmax-players=20\ndifficulty=normal\ngamemode=survival\npvp=true\nwhite-list=false\nallow-flight=false\nview-distance=10\nspawn-protection=16\n';
http.createServer((req, res) => {
  const u = url.parse(req.url, true); const path = u.pathname;
  fs.appendFileSync(logFile, new Date().toISOString() + ' ' + req.method + ' ' + path + (u.search || '') + '\n');
  const send = (code, body, type = 'application/json') => setTimeout(() => { res.writeHead(code, { 'Content-Type': type }); res.end(typeof body === 'string' ? body : JSON.stringify(body)); }, delay);
  const list = servers();
  if (path === '/api/system') return send(200, { architecture: 'amd64', cpu_count: 4, kernel_version: '6.8', os: 'linux', version: '1.0.0-lab', docker: { version: '27.0', cgroups: { driver: 'systemd', version: '2' }, containers: { total: list.length, running: list.length, paused: 0, stopped: 0 }, storage: { driver: 'overlay2', filesystem: 'extfs' }, runc: { version: '1.1' } }, system: { architecture: 'amd64', cpu_threads: 4, memory_bytes: 16e9, kernel_version: '6.8', os: 'linux', os_type: 'linux' } });
  if (path === '/api/system/utilization') return send(200, { memory_total: 16e9, memory_used: 6e9, swap_total: 0, swap_used: 0, load_average1: 0.5, load_average5: 0.4, load_average15: 0.3, cpu_percent: 23.5, disk_total: 500e9, disk_used: 120e9 });
  if (path === '/api/system/ips') return send(200, { ip_addresses: ['127.0.0.1'] });
  if (path === '/api/servers') return send(200, list.map(entry));
  const m = path.match(/^\/api\/servers\/([^/]+)(\/.*)?$/);
  if (m) {
    const i = list.findIndex((s) => s.uuid === m[1]); if (i < 0) return send(404, { error: 'not found' });
    const rest = m[2] || '';
    if (rest === '') return send(200, entry(list[i], i));
    if (rest === '/files/contents') { const f = u.query.file || ''; if (f.endsWith('server.properties')) return send(200, properties, 'text/plain'); return send(404, { error: 'file not found' }); }
    if (rest === '/files/list-directory') return send(200, []);
    if (rest === '/power') return send(204, '');
    if (rest === '/logs') return send(200, { data: ['[lab] hello'] });
    if (rest === '/install-logs') return send(200, { data: '' });
    return send(200, {});
  }
  send(404, { error: 'unknown path ' + path });
}).listen(port, '127.0.0.1', () => console.log('fake wings on ' + port + ' delay ' + delay + 'ms'));
