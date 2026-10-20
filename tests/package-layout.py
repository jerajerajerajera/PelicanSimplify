from pathlib import Path
import hashlib, json, sys, tempfile, zipfile
root = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(root / 'scripts'))
import importlib.util
spec = importlib.util.spec_from_file_location('build_package', root / 'scripts' / 'build-package.py')
builder = importlib.util.module_from_spec(spec); spec.loader.exec_module(builder)

manifest = json.loads((root / 'plugin.json').read_text())
assert manifest['id'] == 'pelican-simplify'
assert (root / 'src' / f"{manifest['class']}.php").is_file()
assert not (root / 'pelican-simplify').exists()
channel = builder.channel_of(manifest['version'])
channels = {name: json.loads((root / name).read_text()) for name in builder.CHANNEL_FILES if (root / name).is_file()}
own = {'stable': 'update.json', 'beta': 'update-beta.json', 'snapshot': 'update-snapshot.json'}[channel]
published = root / 'dist' / f"pelican-simplify-v{manifest['version']}.zip"
assert published.is_file(), 'the published archive for this version is committed'
for panel in builder.SUPPORTED_PANELS:
    current = channels[own][panel]
    assert current['version'] == manifest['version'], f'{own} announces the current version for {panel}'
    assert current['download_url'] == builder.REPOSITORY + builder.BRANCHES[channel] + f"/dist/pelican-simplify-v{manifest['version']}.zip", 'the download is served from the channel branch'
    assert current['sha256'] == hashlib.sha256(published.read_bytes()).hexdigest()
latest = 'pelican-simplify-snapshot-latest.zip' if channel == 'snapshot' else 'pelican-simplify-beta-latest.zip'
assert (root / 'dist' / latest).read_bytes() == published.read_bytes(), latest + ' is the newest build'
if channel == 'stable':
    assert channels['update.json'] == channels['update-beta.json'], 'a stable release is also on main/update-beta.json, which older betas read'
    assert (root / 'dist/pelican-simplify-latest.zip').read_bytes() == published.read_bytes(), 'latest.zip is the stable release'
    assert 'update-snapshot.json' not in channels, 'main carries no snapshot channel'
served = builder.served_archives(channel)
for name in served:
    assert (root / 'dist' / name).is_file(), 'every announced archive on this branch is committed'
assert sorted(p.name for p in (root / 'dist').glob('pelican-simplify-v*.zip')) == sorted(served), 'only archives on a channel of this branch are kept'
assert builder.channel_of('0.4.3-snapshot.2') == 'snapshot' and builder.channel_of('0.4.3-rc.1') == 'beta' and builder.channel_of('0.4.3-beta.4') == 'beta'

with tempfile.TemporaryDirectory() as temp:
    archive = builder.build(Path(temp), 1_800_000_000)
    with zipfile.ZipFile(archive) as package:
        names = package.namelist()
        assert json.loads(package.read('pelican-simplify/plugin.json')) == manifest
        assert 'pelican-simplify/src/' + manifest['class'] + '.php' in names
        for bundle in ['css/tokens.css', 'css/components.css', 'css/dashboard.css', 'js/core.js', 'js/dashboard.js', 'js/wizard.js']:
            assert 'pelican-simplify/resources/views/' + bundle in names, bundle
        assert 'pelican-simplify/database/Seeders/PelicanSimplifySeeder.php' in names
        assert all(n.startswith('pelican-simplify/') for n in names)
        assert not any(n.startswith('pelican-simplify/docs/') for n in names), 'screenshots are not shipped'
        # Laravel reuses a compiled view if its mtime is newer than the source.
        assert package.getinfo('pelican-simplify/resources/views/dashboard.blade.php').date_time[0] >= 2027
print('PASS Hub root manifest, autoload layout, native archive, stable, beta and snapshot channels with their archives and checksums')
