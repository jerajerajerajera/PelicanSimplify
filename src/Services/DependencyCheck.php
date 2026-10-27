<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

/** Shared by the native installer and first-run setup. */
final class DependencyCheck
{
    public const TESTED_RELEASES = ['1.0.0-beta38', '1.0.0-rc.1', '1.0.0-rc.2'];

    public static function inspect(string $panel, string $plugin, bool $runtime = true, bool $allowLegacy = false): array
    {
        $checks = [];
        // English stays here as the id (and as the text while the plugin's lang files are not loaded, during install).
        $add = static function (string $key, string $name, bool $ok, string $fix, bool $blocking = true, array $replace = []) use (&$checks): void {
            $text = static fn (string $field, string $english): string => Translations::text('checks.requirements.'.$key.'.'.$field, $english, $replace);
            $checks[] = ['id' => strtr($name, array_combine(array_map(static fn ($k) => ':'.$k, array_keys($replace)), array_map('strval', $replace)) ?: []), 'name' => $text('name', $name), 'ok' => $ok, 'fix' => $ok ? '' : $text('fix', $fix), 'blocking' => $blocking];
        };
        $add('php', 'PHP 8.3 or newer', version_compare(PHP_VERSION, '8.3', '>='), 'Upgrade PHP for both the web server and queue worker to PHP 8.3 or newer.');
        foreach (['intl', 'json', 'mbstring', 'pdo', 'zip', 'openssl', 'fileinfo', 'ctype', 'tokenizer', 'xml', 'dom', 'curl', 'session'] as $extension) {
            $add('php_extension', 'PHP extension: :extension', extension_loaded($extension), 'Enable the :extension extension in the PHP runtime used by the panel and queue worker, then restart those services.', true, ['extension' => $extension]);
        }
        $add('pelican', 'Pelican application and Composer dependencies', is_readable($panel.'/artisan') && is_readable($panel.'/vendor/autoload.php'), 'Complete the Pelican installation and install its Composer dependencies.');
        foreach ([\Filament\Schemas\Schema::class, \Filament\Actions\Action::class, \Livewire\Livewire::class, \Symfony\Component\Process\Process::class] as $class) {
            $add('composer', 'Composer: :class', class_exists($class), 'Install the Composer dependencies matching your Pelican release.', true, ['class' => $class]);
        }
        $icons = class_exists(\Composer\InstalledVersions::class) && \Composer\InstalledVersions::isInstalled('secondnetwork/blade-tabler-icons');
        $add('tabler', 'Tabler icon components', $icons, 'Restore the blade-tabler-icons Composer dependency and clear the Blade view cache.');
        $node = self::commandVersion('node', $panel);
        $yarn = self::commandVersion('yarn', $panel);
        $add('node', 'Node.js 22.12+ / 24+', self::nodeSupported($node), 'Install Node.js 22.12 or newer (or Node.js 24+). Make node available on the web server and queue worker PATH.');
        $add('yarn', 'Yarn', preg_match('/^\d+\.\d+/', $yarn) === 1, 'Install Yarn or enable it through Corepack. Make yarn available on the web server and queue worker PATH.');
        foreach (['storage', 'bootstrap/cache'] as $directory) {
            $add('writable', 'Writable :directory', self::writeProbe($panel.'/'.$directory), 'Grant the panel and queue worker read/write access to :directory.', true, ['directory' => $directory]);
        }
        foreach (array_merge(...array_values(BuildInfo::BUNDLES)) as $file) {
            $path = $plugin.'/resources/views/'.$file;
            $add('plugin_asset', 'Plugin asset: :file', is_readable($path) && filesize($path) > 0, 'Reimport the complete PelicanSimplify package; this CSS/JavaScript file is missing, empty or unreadable.', true, ['file' => $file]);
        }
        $legacyManifest = $panel.'/plugins/simple-server-creator/plugin.json';
        $legacy = is_readable($legacyManifest) ? json_decode((string) file_get_contents($legacyManifest), true) : [];
        $add('legacy_creator', 'Standalone creator retired', $allowLegacy || ($legacy['meta']['status'] ?? '') !== 'enabled', 'Disable and delete the former standalone creator (simple-server-creator) under Admin → Plugins before enabling the unified creator.');
        $manifestPath = $panel.'/public/build/manifest.json';
        $manifest = is_readable($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : null;
        $required = [];
        foreach (['css', 'js'] as $type) {
            $root = $panel.'/resources/'.$type;
            if (!is_dir($root)) continue;
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && $file->getExtension() === $type) $required[] = str_replace('\\', '/', substr($file->getPathname(), strlen($panel) + 1));
            }
        }
        $errors = self::assetErrors($manifest, $panel.'/public/build', $required);
        $add('built_assets', 'Built Pelican CSS and JavaScript', $errors === [], 'Rebuild the panel assets with yarn install and yarn build in your Pelican folder. :errors', true, ['errors' => implode(' ', $errors)]);
        if ($runtime) {
            // A newer Pelican release is a warning, not a blocker: installing still works and the warning says it is untested.
            $release = ltrim((string) config('app.version'), 'v');
            $add('tested_release', 'Tested Pelican release', in_array($release, self::TESTED_RELEASES, true), 'Simplify is tested on Pelican :tested; this panel reports :release. Installing continues, but report problems on GitHub.', false, ['tested' => implode(', ', self::TESTED_RELEASES), 'release' => $release === '' ? Translations::text('checks.requirements.tested_release.no_version', 'no version') : $release]);
            try { \Illuminate\Support\Facades\DB::connection()->getPdo(); $database = true; } catch (\Throwable) { $database = false; }
            $add('database', 'Database connection', $database, 'Repair the panel database connection before continuing.');
            try {
                $key = 'simplify-preflight-'.bin2hex(random_bytes(8));
                \Illuminate\Support\Facades\Cache::put($key, 'ready', 10);
                $cache = \Illuminate\Support\Facades\Cache::get($key) === 'ready';
                \Illuminate\Support\Facades\Cache::forget($key);
            } catch (\Throwable) { $cache = false; }
            $add('cache', 'Cache read/write', $cache, 'Repair the configured cache backend and its permissions.');
            try {
                $disk = \Illuminate\Support\Facades\Storage::disk('local');
                $key = 'simplify/.preflight-'.bin2hex(random_bytes(8));
                $storage = $disk->put($key, 'ready') && $disk->get($key) === 'ready';
                $disk->delete($key);
            } catch (\Throwable) { $storage = false; }
            $add('storage', 'Plugin settings storage', $storage, 'Repair the local Laravel storage disk and grant the panel write access.');
            $add('queue', 'Background queue configured', !in_array(config('queue.default'), [null, '', 'sync', 'null'], true), 'Configure a persistent Pelican queue connection and start its queue worker.');
        }
        return [...$checks,...UploadLimits::checks()];
    }

    public static function ready(array $checks): bool
    {
        return $checks !== [] && !array_filter($checks, static fn (array $check): bool => !$check['ok'] && ($check['blocking'] ?? true));
    }

    public static function assertReady(array $checks): void
    {
        if (self::ready($checks)) return;
        $failed = array_filter($checks, static fn (array $check): bool => !$check['ok'] && ($check['blocking'] ?? true));
        throw new \RuntimeException("PelicanSimplify installation blocked. Fix these requirements and retry:\n".implode("\n", array_map(static fn (array $check): string => $check['name'].': '.$check['fix'], $failed)));
    }

    public static function nodeSupported(string $version): bool
    {
        if (!preg_match('/^v?(\d+)\.(\d+)\.(\d+)/', $version, $match)) return false;
        return ((int) $match[1] === 22 && (int) $match[2] >= 12) || (int) $match[1] >= 24;
    }

    /** Validate actual build outputs, not just the presence of a manifest. */
    public static function assetErrors(mixed $manifest, string $build, array $required = []): array
    {
        if (!is_array($manifest) || !$manifest) return [Translations::text('checks.assets.manifest', 'Build manifest is missing or invalid.')];
        $errors = []; $css = false; $js = false;
        foreach ($required as $source) if (!isset($manifest[$source])) $errors[] = Translations::text('checks.assets.missing_entry', 'Missing entry: :source.', ['source' => $source]);
        foreach ($manifest as $entry) {
            if (!is_array($entry) || !isset($entry['file']) || !is_string($entry['file'])) { $errors[] = Translations::text('checks.assets.invalid_entry', 'Invalid build entry.'); continue; }
            $files = [$entry['file']];
            if (isset($entry['css'])) {
                if (!is_array($entry['css'])) { $errors[] = Translations::text('checks.assets.invalid_css', 'Invalid CSS list.'); continue; }
                $files = [...$files, ...$entry['css']];
            }
            foreach ($files as $file) {
                if (!is_string($file) || !preg_match('~^[a-zA-Z0-9_./-]+$~D', $file) || str_contains($file, '..') || str_starts_with($file, '/')) { $errors[] = Translations::text('checks.assets.invalid_path', 'Invalid build asset path.'); continue; }
                $path = $build.'/'.$file;
                if (!is_readable($path) || !is_file($path) || filesize($path) === 0) $errors[] = Translations::text('checks.assets.missing_asset', 'Missing or empty asset: :file.', ['file' => $file]);
                if (str_ends_with($file, '.css')) $css = true;
                if (str_ends_with($file, '.js')) $js = true;
            }
            $imports = $entry['imports'] ?? [];
            if (!is_array($imports)) $errors[] = Translations::text('checks.assets.invalid_imports', 'Invalid build imports.');
            else foreach ($imports as $import) if (!is_string($import) || !isset($manifest[$import])) $errors[] = Translations::text('checks.assets.missing_import', 'Missing imported build entry.');
        }
        if (!$css) $errors[] = Translations::text('checks.assets.no_css', 'No compiled CSS found.');
        if (!$js) $errors[] = Translations::text('checks.assets.no_js', 'No compiled JavaScript found.');
        return array_values(array_unique($errors));
    }

    /** Whether Pelican can build a theme after it is installed (Node.js and Yarn on the PATH); kept for ten minutes. */
    public static function themeBuildReady(string $panel): array
    {
        return \Illuminate\Support\Facades\Cache::remember('simplify.theme-build-ready', 600, function () use ($panel): array {
            $node = self::commandVersion('node', $panel);
            $yarn = self::commandVersion('yarn', $panel);
            return ['node' => self::nodeSupported($node), 'yarn' => preg_match('/^\d+\.\d+/', $yarn) === 1];
        });
    }

    private static function commandVersion(string $command, string $panel): string
    {
        try {
            $process = new \Symfony\Component\Process\Process([$command, '--version'], $panel);
            $process->setTimeout(5)->run();
            return $process->isSuccessful() ? trim($process->getOutput()) : '';
        } catch (\Throwable) { return ''; }
    }

    private static function writeProbe(string $directory): bool
    {
        if (!is_dir($directory) || !is_writable($directory)) return false;
        $path = $directory.'/.simplify-preflight-'.bin2hex(random_bytes(8));
        try {
            if (\Illuminate\Support\Facades\File::put($path, 'ready') === false) return false;
            $ok = \Illuminate\Support\Facades\File::get($path) === 'ready';
            return \Illuminate\Support\Facades\File::delete($path) && $ok;
        } catch (\Throwable) {
            return false;
        }
    }
}
