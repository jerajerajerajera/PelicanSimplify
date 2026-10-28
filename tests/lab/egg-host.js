// Lab egg host for lab-final.js: serves $LAB_DIR/egg-host/www over HTTPS on 127.0.0.1:8443, the update address that
// state.php egg-host gives the lab eggs. The panel must trust its certificate, once:
//   cd $LAB_DIR/egg-host && openssl req -x509 -newkey rsa:2048 -nodes -keyout key.pem -out cert.pem -days 30 \
//     -subj "/CN=lab egg host" -addext "subjectAltName=IP:127.0.0.1"
//   cp cert.pem /usr/local/share/ca-certificates/lab-egg-host.crt && update-ca-certificates
// node egg-host.js
const https = require('https'), fs = require('fs'), path = require('path');
const dir = path.join(process.env.LAB_DIR || path.join(require('os').homedir(), 'pelican-lab'), 'egg-host');
fs.mkdirSync(path.join(dir, 'www'), { recursive: true });
https.createServer({ key: fs.readFileSync(path.join(dir, 'key.pem')), cert: fs.readFileSync(path.join(dir, 'cert.pem')) }, (req, res) => {
  const file = path.join(dir, 'www', path.basename(req.url.split('?')[0]));
  fs.readFile(file, (err, body) => { res.writeHead(err ? 404 : 200, { 'Content-Type': 'application/json' }); res.end(err ? '{}' : body); });
}).listen(8443, '127.0.0.1', () => console.log('egg host on https://127.0.0.1:8443 from ' + dir));
