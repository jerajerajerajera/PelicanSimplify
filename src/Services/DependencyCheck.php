<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

/** Shared by the native installer, shell preflight and first-run setup. */
final class DependencyCheck
{
    public static function inspect(string $panel, string $plugin, bool $runtime = true, bool $allowLegacy = false): array
    {
        $checks = [];
        $add = static function (string $name, bool $ok, string $fix) use (&$checks): void {
            $checks[] = ['name' => $name, 'ok' => $ok, 'fix' => $ok ? '' : $fix];
        };
        $add('PHP 8.3 or newer', version_compare(PHP_VERSION, '8.3', '>='), 'Upgrade PHP for both the web server and queue worker to PHP 8.3 or newer.');
        foreach (['intl', 'json', 'mbstring', 'pdo', 'zip', 'openssl', 'fileinfo', 'ctype', 'tokenizer', 'xml', 'dom', 'curl', 'session'] as $extension) {
            $add('PHP extension: '.$extension, extension_loaded($extension), 'Enable the '.$extension.' extension in the PHP runtime used by the panel and queue worker, then restart those services.');
        }
        $add('Pelican application and Composer dependencies', is_readable($panel.'/artisan') && is_readable($panel.'/vendor/autoload.php'), 'Complete the Pelican installation and install its Composer dependencies.');
        foreach ([\Filament\Schemas\Schema::class, \Filament\Actions\Action::class, \Livewire\Livewire::class, \Symfony\Component\Process\Process::class] as $class) {
            $add('Composer: '.$class, class_exists($class), 'Install the Composer dependencies matching your Pelican release.');
        }
        $icons = class_exists(\Composer\InstalledVersions::class) && \Composer\InstalledVersions::isInstalled('secondnetwork/blade-tabler-icons');
        $add('Tabler icon components', $icons, 'Restore the blade-tabler-icons Composer dependency and clear the Blade view cache.');
        $node = self::commandVersion('node', $panel);
        $yarn = self::commandVersion('yarn', $panel);
        $add('Node.js 22.12+ / 24+', self::nodeSupported($node), 'Install Node.js 22.12 or newer (or Node.js 24+). Make node available on the web server and queue worker PATH.');
        $add('Yarn', preg_match('/^\d+\.\d+/', $yarn) === 1, 'Install Yarn or enable it through Corepack. Make yarn available on the web server and queue worker PATH.');
        foreach (['storage', 'bootstrap/cache'] as $directory) {
            $add('Writable '.$directory, self::writeProbe($panel.'/'.$directory), 'Grant the panel and queue worker read/write access to '.$directory.'.');
        }
        foreach (array_merge(...array_values(BuildInfo::BUNDLES)) as $file) {
            $path = $plugin.'/resources/views/'.$file;
            $add('Plugin asset: '.$file, is_readable($path) && filesize($path) > 0, 'Reimport the complete PelicanSimplify package; this CSS/JavaScript file is missing, empty or unreadable.');
        }
        $legacyManifest = $panel.'/plugins/simple-server-creator/plugin.json';
        $legacy = is_readable($legacyManifest) ? json_decode((string) file_get_contents($legacyManifest), true) : [];
        $add('Standalone creator retired', $allowLegacy || ($legacy['meta']['status'] ?? '') !== 'enabled', 'Use the migration installer to retire the former standalone creator before enabling the unified creator.');
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
        $add('Built Pelican CSS and JavaScript', $errors === [], 'Rebuild the panel assets with yarn install and yarn build in your Pelican folder. '.implode(' ', $errors));
        if ($runtime) {
            $add('Supported Pelican release', in_array(ltrim((string) config('app.version'), 'v'), ['1.0.0-beta38', '1.0.0-rc.1'], true), 'Use Pelican 1.0.0-beta38 or 1.0.0-rc.1; other releases have not been validated for this plugin.');
            try { \Illuminate\Support\Facades\DB::connection()->getPdo(); $database = true; } catch (\Throwable) { $database = false; }
            $add('Database connection', $database, 'Repair the panel database connection before continuing.');
            try {
                $key = 'simplify-preflight-'.bin2hex(random_bytes(8));
                \Illuminate\Support\Facades\Cache::put($key, 'ready', 10);
                $cache = \Illuminate\Support\Facades\Cache::get($key) === 'ready';
                \Illuminate\Support\Facades\Cache::forget($key);
            } catch (\Throwable) { $cache = false; }
            $add('Cache read/write', $cache, 'Repair the configured cache backend and its permissions.');
            try {
                $disk = \Illuminate\Support\Facades\Storage::disk('local');
                $key = 'simplify/.preflight-'.bin2hex(random_bytes(8));
                $storage = $disk->put($key, 'ready') && $disk->get($key) === 'ready';
                $disk->delete($key);
            } catch (\Throwable) { $storage = false; }
            $add('Plugin settings storage', $storage, 'Repair the local Laravel storage disk and grant the panel write access.');
            $add('Background queue configured', !in_array(config('queue.default'), [null, '', 'sync', 'null'], true), 'Configure a persistent Pelican queue connection and start its queue worker.');
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
        if (!is_array($manifest) || !$manifest) return ['Build manifest is missing or invalid.'];
        $errors = []; $css = false; $js = false;
        foreach ($required as $source) if (!isset($manifest[$source])) $errors[] = 'Missing entry: '.$source.'.';
        foreach ($manifest as $entry) {
            if (!is_array($entry) || !isset($entry['file']) || !is_string($entry['file'])) { $errors[] = 'Invalid build entry.'; continue; }
            $files = [$entry['file']];
            if (isset($entry['css'])) {
                if (!is_array($entry['css'])) { $errors[] = 'Invalid CSS list.'; continue; }
                $files = [...$files, ...$entry['css']];
            }
            foreach ($files as $file) {
                if (!is_string($file) || !preg_match('~^[a-zA-Z0-9_./-]+$~D', $file) || str_contains($file, '..') || str_starts_with($file, '/')) { $errors[] = 'Invalid build asset path.'; continue; }
                $path = $build.'/'.$file;
                if (!is_readable($path) || !is_file($path) || filesize($path) === 0) $errors[] = 'Missing or empty asset: '.$file.'.';
                if (str_ends_with($file, '.css')) $css = true;
                if (str_ends_with($file, '.js')) $js = true;
            }
            $imports = $entry['imports'] ?? [];
            if (!is_array($imports)) $errors[] = 'Invalid build imports.';
            else foreach ($imports as $import) if (!is_string($import) || !isset($manifest[$import])) $errors[] = 'Missing imported build entry.';
        }
        if (!$css) $errors[] = 'No compiled CSS found.';
        if (!$js) $errors[] = 'No compiled JavaScript found.';
        return array_values(array_unique($errors));
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
