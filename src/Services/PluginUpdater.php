<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;

use App\Models\Plugin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Safe plugin updates. Pelican's own update swaps the plugin folder (including plugin.json and its install
 * status) and leaves the plugin disabled or half installed. This keeps a backup, downloads (checking the
 * published sha256 when the update file has one), restores the install status, installs in a fresh PHP
 * process and confirms the panel boots with it. Any failure restores the previous version.
 */
final class PluginUpdater
{
    /** Installed plugins with an update for this panel version. */
    public static function available(bool $fresh = false): array
    {
        Plugin::refreshRows();
        return Plugin::all()->filter(function (Plugin $plugin) use ($fresh): bool {
            try {
                return $plugin->status->value !== 'not_installed' && ($latest = self::latestVersion($plugin, $fresh)) && version_compare($latest, $plugin->version, '>');
            } catch (\Throwable) {
                return false;
            }
        })->values()->all();
    }

    /** The version an update file offers for this panel; cached 50 s, or read fresh (past GitHub's CDN) on request. */
    public static function latestVersion(Plugin $plugin, bool $fresh = false): ?string
    {
        if (!$plugin->update_url) return null;
        $key = 'simplify.plugin-latest.'.$plugin->id.'.'.self::channel();
        if ($fresh) Cache::forget($key);
        return Cache::remember($key, 50, function () use ($plugin, $fresh): ?string {
            $entry = self::entry($plugin, $fresh);
            return is_string($entry['version'] ?? null) ? $entry['version'] : null;
        });
    }

    /** This panel's entry of the plugin's update.json. Fresh reads of raw.githubusercontent.com go through GitHub's API, which is not held for five minutes by the CDN. */
    private static function entry(Plugin $plugin, bool $fresh = false): ?array
    {
        $url = (string) $plugin->update_url;
        $entry = self::read($url, $plugin, $fresh);
        // Simplify: the newest of its update_url (the Pelican Hub), the stable build on GitHub and the builds of the chosen channel and every more stable one.
        if ($plugin->id === 'pelican-simplify') {
            foreach (self::channelUrls(self::channel()) as $channelUrl) {
                if ($channelUrl === $url) continue;
                $candidate = self::read($channelUrl, $plugin, $fresh);
                if ($candidate && is_string($candidate['version'] ?? null) && (!$entry || version_compare($candidate['version'], (string) ($entry['version'] ?? '0'), '>'))) $entry = $candidate;
            }
        }
        return $entry;
    }

    /** Simplify's GitHub repository, where each update channel has its own branch. */
    public const REPOSITORY = 'https://raw.githubusercontent.com/jerajerajerajera/PelicanSimplify/';

    /**
     * Simplify's update files on GitHub for a channel: stable on main, beta and snapshot on their own branch.
     * plugin.json's update_url points at the Pelican Hub (which only offers stable releases), so these are read as well.
     */
    public static function channelUrls(string $channel): array
    {
        $channels = ['beta' => ['beta'], 'snapshot' => ['beta', 'snapshot']][$channel] ?? [];
        return [self::REPOSITORY.'main/update.json', ...array_map(fn (string $name): string => self::REPOSITORY.$name.'/update-'.$name.'.json', $channels)];
    }

    /** One update file's entry for this panel, or null when it cannot be read. */
    private static function read(string $url, Plugin $plugin, bool $fresh): ?array
    {
        $get = static fn (string $from, array $headers = []) => Http::timeout(5)->connectTimeout(2)->withHeaders($headers)->get($from)->throw()->json();
        $data = null;
        if ($fresh && preg_match('#^https://raw\.githubusercontent\.com/([^/]+)/([^/]+)/([^/]+)/(.+)$#', $url, $m)) {
            try {
                $data = $get("https://api.github.com/repos/{$m[1]}/{$m[2]}/contents/{$m[4]}?ref={$m[3]}", ['Accept' => 'application/vnd.github.raw+json']);
            } catch (\Throwable) {
                $data = null;
            }
        }
        try {
            $data = is_array($data) ? $data : $get($url);
        } catch (\Throwable) {
            return null;
        }
        return self::pick($data, $plugin);
    }

    /** The entry for this Pelican version in one update file. */
    private static function pick(mixed $data, Plugin $plugin): ?array
    {
        if (!is_array($data)) return null;
        $data = is_array($data[$plugin->id] ?? null) ? $data[$plugin->id] : $data;
        $entry = $data[self::panelVersion()] ?? $data['*'] ?? null;
        return is_array($entry) ? $entry : null;
    }

    /** "stable", "beta" or "snapshot": which Simplify releases this panel follows. */
    public static function channel(): string
    {
        return FeatureSettings::enabled('simplify_snapshot') ? 'snapshot' : (FeatureSettings::enabled('simplify_beta') ? 'beta' : 'stable');
    }

    public static function panelVersion(): string
    {
        return ltrim((string) config('app.version'), 'v');
    }

    /** @return array{ok: bool, message: string} */
    public static function update(Plugin $plugin, ?int $notifyUserId = null): array
    {
        $id = $plugin->id;
        $lock = Cache::lock('simplify.plugin-update.'.$id, 1200);
        if (!$lock->get()) return ['ok' => false, 'message' => $plugin->name.' is already being updated.'];
        $directory = plugin_path($id);
        $backup = storage_path('app/simplify/plugin-backups/'.$id);
        $from = $plugin->version;
        $result = null;
        try {
            // Version, download and checksum come from one fresh read, so a stale CDN copy cannot mix versions.
            $entry = self::entry($plugin, true);
            $url = $entry['download_url'] ?? null;
            if (!is_string($url) || !str_starts_with($url, 'https://')) throw new \RuntimeException('No download is published for this panel version.');
            $sha = $entry['sha256'] ?? null;
            $expected = is_string($sha) && preg_match('/^[a-f0-9]{64}$/', $sha) ? $sha : null;
            $manifest = File::json($directory.'/plugin.json');
            $meta = is_array($manifest['meta'] ?? null) ? $manifest['meta'] : [];
            $wasEnabled = ($meta['status'] ?? '') === 'enabled';

            File::deleteDirectory($backup);
            File::ensureDirectoryExists(dirname($backup));
            if (!File::copyDirectory($directory, $backup)) throw new \RuntimeException('Could not back up the current version.');

            try {
                self::download($plugin, $url, $expected);
                // Keep the install status the new plugin.json would otherwise wipe.
                $next = File::json($directory.'/plugin.json');
                $next['meta'] = [...(is_array($next['meta'] ?? null) ? $next['meta'] : []), ...$meta, 'status_message' => null];
                File::put($directory.'/plugin.json', json_encode($next, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $to = (string) ($next['version'] ?? '?');

                $install = Process::path(base_path())->timeout(1100)->run(array_values(array_filter([self::php(), 'artisan', 'simplify:plugin-install', $id, $wasEnabled ? '--enable' : null])));
                if (!$install->successful()) throw new \RuntimeException(self::output($install) ?: 'The new version could not be installed.');
                // A second fresh process proves the panel still boots with the new code.
                $boot = Process::path(base_path())->timeout(120)->run([self::php(), 'artisan', 'list', '--raw']);
                if (!$boot->successful()) throw new \RuntimeException('The panel does not start with the new version: '.self::output($boot));
                $result = ['ok' => true, 'message' => $plugin->name.' updated from '.$from.' to '.$to.'.'];
                PanelUpdates::forget();
                // What's new right in the "Plugin updated" notification; usually already read by the update check.
                try { $result['notes'] = PanelUpdates::notesFor($plugin, $to); } catch (\Throwable) {}
            } catch (\Throwable $exception) {
                self::restore($directory, $backup);
                report($exception);
                $result = ['ok' => false, 'message' => $plugin->name.' could not be updated, version '.$from.' was restored: '.$exception->getMessage()];
            }
        } catch (\Throwable $exception) {
            report($exception);
            $result = ['ok' => false, 'message' => $plugin->name.' could not be updated: '.$exception->getMessage()];
        } finally {
            $lock->release();
            try {
                Plugin::refreshRows();
                cache()->forget('plugins.'.$id.'.update');
                cache()->forget('simplify.plugin-update-queued.'.$id);
                foreach (['stable', 'beta'] as $channel) cache()->forget('simplify.plugin-latest.'.$id.'.'.$channel);
            } catch (\Throwable) {}
        }
        self::notify($result, $notifyUserId);
        return $result;
    }

    private static function download(Plugin $plugin, string $url, ?string $expected): void
    {
        $service = app(\App\Services\Helpers\PluginService::class);
        if ($expected === null) {
            $service->downloadPluginFromUrl($url, $plugin->id);
            return;
        }
        $body = Http::timeout(60)->connectTimeout(5)->throw()->get($url)->body();
        if (!hash_equals($expected, hash('sha256', $body))) throw new \RuntimeException('The download does not match the published checksum.');
        $temporary = tempnam(sys_get_temp_dir(), 'simplify-plugin-').'.zip';
        try {
            File::put($temporary, $body);
            $service->downloadPluginFromFile(new \Illuminate\Http\UploadedFile($temporary, basename($url), 'application/zip', null, true), $plugin->id);
        } finally {
            File::delete($temporary);
        }
    }

    /** The sha256 an update file publishes for this panel version (Simplify does), if any. */
    private static function restore(string $directory, string $backup): void
    {
        if (!File::isDirectory($backup)) return;
        File::deleteDirectory($directory);
        File::copyDirectory($backup, $directory);
        try {
            Process::path(base_path())->timeout(120)->run([self::php(), 'artisan', 'view:clear']);
        } catch (\Throwable) {}
    }

    private static function output(\Illuminate\Contracts\Process\ProcessResult $result): string
    {
        return trim(substr(trim($result->errorOutput()."\n".$result->output()), -600));
    }

    /** Result in the Simplify bell (Pelican database notification) for the starter or all root admins. */
    /** Tells root admins once per version that an update is being installed. An available update shows as a Panel announcement (PanelUpdates). */
    public static function announce(Plugin $plugin, bool $installing): void
    {
        if (!$installing) return;
        $latest = self::latestVersion($plugin);
        if (!$latest || !\Illuminate\Support\Facades\Cache::add('simplify.plugin-announced.'.$plugin->id.'.'.$latest, true, now()->addDays(60))) return;
        try {
            $notification = \Filament\Notifications\Notification::make()->info()
                ->title($plugin->name.' '.$latest.' is available')
                ->body('Installing it now with a backup and automatic rollback. You get another notification when it is done.')
                ->actions([\Filament\Actions\Action::make('open')->label('Open plugins')->url(\Simplify\Panel\Filament\Admin\Pages\ManagePlugins::getUrl(panel: 'admin'))]);
            foreach (\App\Models\User::query()->get()->filter(fn ($user) => $user->isRootAdmin()) as $user) $user->notifyNow($notification->toDatabase());
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * The PHP command-line binary. Under php-fpm PHP_BINARY points at php-fpm itself, which cannot run artisan;
     * that is why updates started from the web page failed while scheduled ones (CLI) worked.
     */
    private static function php(): string
    {
        static $binary = null;
        if ($binary !== null) return $binary;
        if (PHP_SAPI === 'cli' && PHP_BINARY !== '') return $binary = PHP_BINARY;
        $found = (new \Symfony\Component\Process\PhpExecutableFinder())->find(false);
        foreach (array_filter([$found ?: null, PHP_BINDIR.'/php', '/usr/bin/php', '/usr/local/bin/php']) as $candidate) {
            if (is_file($candidate) && is_executable($candidate) && !str_contains(basename($candidate), 'fpm')) return $binary = $candidate;
        }
        return $binary = 'php';
    }

    /** Install status (plugin.json meta) of every plugin folder, read before an import may replace one of them. */
    public static function installStates(): array
    {
        $states = [];
        foreach (File::glob(plugin_path('*/plugin.json')) ?: [] as $path) {
            try {
                $meta = File::json($path)['meta'] ?? null;
            } catch (\Throwable) {
                continue;
            }
            if (is_array($meta) && ($meta['status'] ?? 'not_installed') !== 'not_installed') $states[basename(dirname($path))] = $meta;
        }
        return $states;
    }

    /**
     * After importing over an existing installation the plugin is installed again, like Pelican 1.0.0-rc.1 does,
     * so new migrations and assets apply. Beta38 drops the install status with the old plugin.json; it is put
     * back first. Returns true when a reinstall was started.
     */
    public static function afterImport(string $id, array $states, ?int $userId = null): bool
    {
        $meta = $states[$id] ?? null;
        if (!is_array($meta)) return false;
        $path = plugin_path($id).'/plugin.json';
        $manifest = File::json($path);
        $manifest['meta'] = [...(is_array($manifest['meta'] ?? null) ? $manifest['meta'] : []), ...$meta, 'status_message' => null];
        File::put($path, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        Plugin::refreshRows();
        Cache::put('simplify.plugin-queued.'.$id, now()->timestamp, 1800);
        \Simplify\Panel\Jobs\ChangePlugin::dispatchAfterResponse($id, 'reinstall', $userId);
        return true;
    }

    /** Installs, reinstalls or uninstalls a plugin in a fresh process and always reports the outcome as a notification. */
    public static function change(Plugin $plugin, string $action, ?int $userId = null): array
    {
        $label = ['install' => 'installed', 'reinstall' => 'installed again'][$action] ?? 'uninstalled';
        try {
            if ($action === 'install' || $action === 'reinstall') {
                // A reinstall keeps the plugin enabled or disabled as it was; an errored one is enabled again.
                $enable = $action === 'install' || $plugin->status->value !== 'disabled';
                $run = Process::path(base_path())->timeout(1100)->run(array_values(array_filter([self::php(), 'artisan', 'simplify:plugin-install', $plugin->id, $enable ? '--enable' : null])));
                if (!$run->successful()) throw new \RuntimeException(self::output($run) ?: 'The plugin could not be installed.');
            } else {
                app(\App\Services\Helpers\PluginService::class)->uninstallPlugin($plugin);
            }
            $boot = Process::path(base_path())->timeout(120)->run([self::php(), 'artisan', 'list', '--raw']);
            if (!$boot->successful()) throw new \RuntimeException('The panel does not start with this plugin: '.(self::output($boot) ?: 'unknown error'));
            $result = ['ok' => true, 'message' => $plugin->name.' was '.$label.'. Reload the panel to see the change.'];
        } catch (\Throwable $exception) {
            report($exception);
            // Pelican enables a plugin before its code is known to load; a broken one would stop the whole panel.
            if ($action !== 'uninstall') self::quarantine($plugin->id, $exception->getMessage());
            $result = ['ok' => false, 'message' => $plugin->name.' could not be '.$label.': '.$exception->getMessage()];
        }
        \Illuminate\Support\Facades\Cache::forget('simplify.plugin-queued.'.$plugin->id);
        self::notify($result, $userId, $action === 'uninstall' ? ['Plugin uninstalled', 'Plugin uninstall failed'] : ['Plugin installed', 'Plugin install failed']);
        return $result;
    }

    /** Sets a plugin back to not installed (Pelican still loads errored ones) so the panel keeps starting; the files stay for inspection or deletion. */
    private static function quarantine(string $id, string $message): void
    {
        try {
            $path = plugin_path($id).'/plugin.json';
            $manifest = File::json($path);
            $manifest['meta'] = ['status' => 'not_installed', 'status_message' => mb_substr($message, 0, 500)];
            File::put($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            Plugin::refreshRows();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /** Called when a queued plugin job itself crashed or timed out, so a failure is never silent. */
    public static function jobFailed(string $pluginId, string $action, ?int $userId, \Throwable $exception): void
    {
        \Illuminate\Support\Facades\Cache::forget('simplify.plugin-queued.'.$pluginId);
        \Illuminate\Support\Facades\Cache::forget('simplify.plugin-update-queued.'.$pluginId);
        self::notify(['ok' => false, 'message' => 'The '.$action.' of '.$pluginId.' stopped: '.$exception->getMessage()], $userId, ['', 'Plugin '.$action.' failed']);
    }

    private static function notify(array $result, ?int $userId, array $titles = ['Plugin updated', 'Plugin update failed']): void
    {
        try {
            $users = $userId ? \App\Models\User::query()->whereKey($userId)->get() : \App\Models\User::query()->get()->filter(fn ($user) => $user->isRootAdmin());
            $notification = \Filament\Notifications\Notification::make()->title($result['ok'] ? $titles[0] : $titles[1])->body($result['message']);
            $result['ok'] ? $notification->success() : $notification->danger();
            if (!empty($result['notes']['lines'])) $notification->viewData(['simplify_notes' => $result['notes']['lines'], 'simplify_notes_url' => $result['notes']['url'] ?? null]);
            if (FeatureSettings::enabled('plugin_manager')) $notification->actions([\Filament\Actions\Action::make('open')->label('Open plugins')->url(\Simplify\Panel\Filament\Admin\Pages\ManagePlugins::getUrl(panel: 'admin'))]);
            // Delivered right away: the result must arrive even when the queue worker is busy or down.
            foreach ($users as $user) $user->notifyNow($notification->toDatabase());
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
