// Preloaded for the JavaScript unit tests (node -r): pages get core.js's Simplify.t / tc with the English texts
// from lang/en/js.php, as the panel gives them, so tests read the same words people see.
// SIMPLIFY_LOCALE=ar shows another language pack instead (browser harness, right-to-left checks).
const vm = require('vm'), path = require('path'), { execFileSync } = require('child_process');
const LOCALE = process.env.SIMPLIFY_LOCALE || 'en';
const php = (file) => JSON.parse(execFileSync(process.env.PHP_BIN || 'php', ['-r', 'echo json_encode(require $argv[1]);', file]));
const langFile = (locale, group) => path.join(__dirname, '../lang', locale, group + '.php');
const lang = php(require('fs').existsSync(langFile(LOCALE, 'js')) ? langFile(LOCALE, 'js') : langFile('en', 'js'));
const fill = (text, params) => Object.keys(params).sort((a, b) => b.length - a.length).reduce((out, name) => out.split(':' + name).join(String(params[name])), text);
const lookup = (key) => { const found = key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), lang); return typeof found === 'string' ? found : key; };
const t = (key, params = {}) => fill(lookup(key), params);
const choice = (raw, count, params = {}) => {
  const forms = String(raw).split('|');
  for (const form of forms) {
    const exact = form.match(/^\s*\{(\d+)\}\s*/), range = form.match(/^\s*\[(\d+|\*),\s*(\d+|\*)\]\s*/);
    if (exact && Number(exact[1]) === count) return fill(form.slice(exact[0].length), { count, ...params });
    if (range && (range[1] === '*' || count >= Number(range[1])) && (range[2] === '*' || count <= Number(range[2]))) return fill(form.slice(range[0].length), { count, ...params });
  }
  const plain = forms.map((form) => form.replace(/^\s*(\{\d+\}|\[(\d+|\*),\s*(\d+|\*)\])\s*/, ''));
  return fill(plain[Math.min(count === 1 ? 0 : 1, plain.length - 1)], { count, ...params });
};
const tc = (key, count, params = {}) => choice(lookup(key), count, params);
const run = vm.runInContext;
vm.runInContext = function (code, context, ...rest) {
  const win = context && context.window;
  if (win && typeof win === 'object') {
    win.simplifyLang = win.simplifyLang || lang;
    if (win.Simplify && typeof win.Simplify === 'object' && !win.Simplify.t) Object.assign(win.Simplify, { t, tc, fill: (text, params = {}) => fill(String(text), params), choice });
    else if (!win.Simplify) win.Simplify = { t, tc, fill: (text, params = {}) => fill(String(text), params), choice, toast() {} };
  }
  return run.call(this, code, context, ...rest);
};
// Static checks read Blade views as English: __('pelican-simplify::file.key') becomes the English text
// (and @js(...) of it a quoted string), so a test can still look for the words on the page.
const fs = require('fs');
const groups = {};
const text = (locale, group, key) => {
  const id = locale + '/' + group;
  if (!(id in groups)) groups[id] = fs.existsSync(langFile(locale, group)) ? php(langFile(locale, group)) : {};
  const found = key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), groups[id]);
  return typeof found === 'string' ? found : null;
};
const english = (group, key) => text(LOCALE, group, key) ?? text('en', group, key);
const read = fs.readFileSync;
fs.readFileSync = function (file, ...rest) {
  const out = read.call(this, file, ...rest);
  if (typeof out !== 'string' || !String(file).endsWith('.blade.php')) return out;
  return out
    .replace(/@js\(__\('pelican-simplify::([a-z0-9_-]+)\.([A-Za-z0-9_.]+)'\)\)/g, (all, group, key) => { const text = english(group, key); return text === null ? all : "'" + text.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'"; })
    .replace(/\{\{ __\('pelican-simplify::([a-z0-9_-]+)\.([A-Za-z0-9_.]+)'\) \}\}/g, (all, group, key) => english(group, key) ?? all);
};
module.exports = { lang, locale: LOCALE };
