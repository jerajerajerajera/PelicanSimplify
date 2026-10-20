#!/usr/bin/env python3
"""Checks what the Pelican Hub checks before it accepts a sync, so a release is not blocked there.

- The repository archive the Hub syncs (git archive HEAD, so .gitattributes export-ignore applies) has no .php file
  that calls a function the Hub bans as a file or process operation (it matches names, Blade templates included).
- plugin.json has every field Pelican's plugin documentation marks as required (read live from pelican-dev/docs,
  with the list below as fallback), no "meta" block, and update_url on the Hub.
Usage: python3 tests/hub-check.py   (run from the repository; needs git)
"""
from pathlib import Path
import io, json, re, subprocess, sys, tarfile, urllib.request

root = Path(__file__).resolve().parent.parent
HUB_UPDATE_URL = 'https://hub.pelican.dev/plugins/{id}/update.json'
DOCS = 'https://raw.githubusercontent.com/pelican-dev/docs/main/docs/panel/advanced/plugins.mdx'
# The Hub's "banned file or process operations" (from its rejection messages), plus PHP's other process functions.
BANNED = ['file_put_contents', 'exec', 'unlink', 'fwrite', 'copy', 'rename', 'mkdir', 'rmdir',
          'shell_exec', 'system', 'passthru', 'proc_open', 'popen']
REQUIRED_FALLBACK = ['id', 'name', 'author', 'category', 'namespace', 'class']
# A plain call: not a method (->copy, ::copy) and not part of a longer name (fcopy, $copy).
CALL = re.compile(r'(?<![\w$>:\\])(' + '|'.join(BANNED) + r')\s*\(')

failures = []


def required_fields() -> tuple[list[str], str]:
    try:
        text = urllib.request.urlopen(DOCS, timeout=10).read().decode()
        rows = re.findall(r'^\|\s*([a-z_]+)\s*\|\s*(Yes|No)\s*\|', text, re.M)
        fields = [name for name, required in rows if required == 'Yes']
        if fields:
            return fields, 'pelican-dev/docs'
    except Exception as error:
        print(f'NOTE could not read the plugin documentation ({error}); using the built-in list')
    return REQUIRED_FALLBACK, 'built-in list'


archive = subprocess.run(['git', 'archive', '--format=tar', 'HEAD'], cwd=root, capture_output=True, check=True).stdout
php_files = 0
with tarfile.open(fileobj=io.BytesIO(archive)) as tar:
    for member in tar.getmembers():
        if not member.isfile() or not member.name.endswith('.php'):
            continue
        php_files += 1
        for number, line in enumerate(tar.extractfile(member).read().decode('utf-8', 'replace').splitlines(), 1):
            for match in CALL.finditer(line):
                failures.append(f'{member.name}:{number} {match.group(1)}()')
if php_files == 0:
    failures.append('the repository archive has no .php files: is .gitattributes leaving out too much?')

manifest = json.loads((root / 'plugin.json').read_text())
fields, source = required_fields()
for field in fields:
    if not manifest.get(field):
        failures.append(f'plugin.json misses required field "{field}" ({source})')
if 'meta' in manifest:
    failures.append('plugin.json has a "meta" block; remove it before publishing')
expected = HUB_UPDATE_URL.format(id=manifest.get('id', ''))
if manifest.get('update_url') != expected:
    failures.append(f'plugin.json update_url must be {expected}')

if failures:
    print('The Pelican Hub would block this version:')
    for failure in failures:
        print('  ' + failure)
    sys.exit(1)
print(f'PASS Pelican Hub checks: {php_files} PHP files in the repository archive without banned calls, plugin.json fields ({source}), update_url on the Hub')
