#!/usr/bin/env python3
"""Build the native plugin package from the standard repository-root layout.

Writes dist/pelican-simplify-v<version>.zip and publishes it on the update channel its version names.
Each channel has its own branch, and its update file and zips live there:
- stable (0.4.2, branch main): update.json, plus update-beta.json, which installations up to 0.4.2-beta.7
  still read on main; becomes dist/pelican-simplify-latest.zip (the download the README links to);
- beta (0.4.3-beta.1 or 0.4.3-rc.1, branch beta): update-beta.json;
- snapshot (0.4.3-snapshot.1, branch snapshot): update-snapshot.json.
Stable and beta builds also become dist/pelican-simplify-beta-latest.zip, snapshots
dist/pelican-simplify-snapshot-latest.zip. Archives no longer referenced by any channel file are removed.
SHA256SUMS is refreshed.
Usage: build-package.py [output-dir]   (default: dist; SOURCE_DATE_EPOCH fixes the timestamps)
"""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re
import os
import shutil
import sys
import zipfile

root = Path(__file__).resolve().parent.parent
REPOSITORY = 'https://raw.githubusercontent.com/jerajerajerajera/PelicanSimplify/'
CHANNEL_FILES = ['update.json', 'update-beta.json', 'update-snapshot.json']


def build(output: Path, epoch: int | None = None) -> Path:
    manifest = json.loads((root / 'plugin.json').read_text())
    version = manifest['version']
    output.mkdir(parents=True, exist_ok=True)
    archive = output / f'pelican-simplify-v{version}.zip'
    # ZIP extraction preserves mtimes. Each release needs newer timestamps than the last, or
    # Laravel's compiled views and timestamp-based PHP caches look up to date.
    moment = datetime.fromtimestamp(epoch, timezone.utc) if epoch is not None else datetime.now(timezone.utc)
    release_time = moment.timetuple()[:6]
    files = [root / name for name in ['plugin.json', 'README.md', 'LICENSE', 'conflicts.json']]
    for name in ['src', 'resources', 'database', 'lang']:
        if (root / name).is_dir():
            files.extend(p for p in (root / name).rglob('*') if p.is_file())
    with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as package:
        for path in sorted(files):
            info = zipfile.ZipInfo('pelican-simplify/' + path.relative_to(root).as_posix(), release_time)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            package.writestr(info, path.read_bytes())
    return archive


SUPPORTED_PANELS = ['1.0.0-beta38', '1.0.0-rc.1']
BRANCHES = {'stable': 'main', 'beta': 'beta', 'snapshot': 'snapshot'}


def channel_of(version: str) -> str:
    """0.4.2 is stable, 0.4.3-beta.1 and 0.4.3-rc.1 are beta, 0.4.3-snapshot.1 is a snapshot."""
    if '-' not in version:
        return 'stable'
    tag = version.split('-', 1)[1]
    if re.fullmatch(r'snapshot\.\d+', tag):
        return 'snapshot'
    if re.fullmatch(r'(beta|rc)\.\d+', tag):
        return 'beta'
    raise SystemExit(f'Unknown pre-release {version}: use -snapshot.N, -beta.N or -rc.N.')


def served_archives(channel: str) -> set[str]:
    """Archives this branch serves: those a channel file announces under this channel's branch (merged-in files may name others)."""
    prefix = REPOSITORY + BRANCHES[channel] + '/dist/'
    names = set()
    for name in CHANNEL_FILES:
        if (root / name).is_file():
            names |= {item['download_url'][len(prefix):] for item in json.loads((root / name).read_text()).values() if item['download_url'].startswith(prefix)}
    return names


def publish(archive: Path) -> None:
    manifest = json.loads((root / 'plugin.json').read_text())
    checksum = hashlib.sha256(archive.read_bytes()).hexdigest()
    channel = channel_of(manifest['version'])
    stable = channel == 'stable'
    entry = {
        'version': manifest['version'],
        'download_url': REPOSITORY + BRANCHES[channel] + '/' + archive.relative_to(root).as_posix(),
        'sha256': checksum,
    }
    # One entry per Pelican release this build is validated on (Pelican looks up its own version).
    channels = {'stable': ['update.json', 'update-beta.json'], 'beta': ['update-beta.json'], 'snapshot': ['update-snapshot.json']}[channel]
    for name in channels:
        (root / name).write_text(json.dumps({panel: entry for panel in SUPPORTED_PANELS}, indent=2) + '\n')
    # These fixed names always point at the newest build of the beta and the snapshot channel.
    shutil.copyfile(archive, archive.parent / ('pelican-simplify-snapshot-latest.zip' if channel == 'snapshot' else 'pelican-simplify-beta-latest.zip'))
    if stable:
        shutil.copyfile(archive, archive.parent / 'pelican-simplify-latest.zip')
        # The README install link points at this release's zip on GitHub Releases (counted downloads).
        readme = root / 'README.md'
        release = f"https://github.com/jerajerajerajera/PelicanSimplify/releases/download/v{manifest['version']}/pelican-simplify-v{manifest['version']}.zip"
        readme.write_text(re.sub(r'https://github\.com/jerajerajerajera/PelicanSimplify/releases/download/v[^/]+/pelican-simplify-v[^)\s]+\.zip', release, readme.read_text()))
    keep = {archive.name} | served_archives(channel)
    for old in archive.parent.glob('pelican-simplify-v*.zip'):
        if old.name not in keep:
            old.unlink()
    with (root / 'SHA256SUMS').open('w') as sums:
        for path in sorted(root.rglob('*')):
            relative = path.relative_to(root)
            if not path.is_file() or path.name == 'SHA256SUMS' or any(part in ['node_modules', '.git', '__pycache__', 'test-results', 'playwright-report'] for part in relative.parts):
                continue
            sums.write(hashlib.sha256(path.read_bytes()).hexdigest() + '  ' + relative.as_posix() + '\n')


if __name__ == '__main__':
    target = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else root / 'dist'
    epoch = int(os.environ['SOURCE_DATE_EPOCH']) if 'SOURCE_DATE_EPOCH' in os.environ else None
    built = build(target, epoch)
    if target == root / 'dist':
        publish(built)
    print(built)
