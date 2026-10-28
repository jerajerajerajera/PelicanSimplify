<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
/**
 * Compares the installed version with the published update.json (the same file Pelican's
 * plugin updater reads). Checked at most every ten minutes, like Pelican's own plugin page (or on demand via forget()); installing stays with Pelican.
 */
final class UpdateCheck
{
    public static function status(): ?array
    {
        $current = BuildInfo::version();
        $manifest = self::manifest();
        $url = is_string($manifest['update_url'] ?? null) ? $manifest['update_url'] : null;
        if (!$current || !$url || !str_starts_with($url, 'https://')) return null;
        $published = Remember::value('simplify.update-check.'.hash('sha256', $url), 600, function () use ($url): ?array {
            $response = Http::timeout(3)->connectTimeout(2)->acceptJson()->get($url);
            return $response->successful() && is_array($response->json()) ? $response->json() : null;
        }, 600);
        $entry = is_array($published) ? ($published[$manifest['panel_version'] ?? ''] ?? null) : null;
        $latest = is_array($entry) && is_string($entry['version'] ?? null) ? $entry['version'] : null;
        if (!$latest || !preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $latest)) return null;
        return [
            'current' => $current,
            'version' => $latest,
            'available' => PluginUpdater::isNewer($latest, $current),
            'url' => FeatureSettings::enabled('plugin_manager') ? \Simplify\Panel\Filament\Admin\Pages\ManagePlugins::getUrl(panel: 'admin') : (Route::has('filament.admin.resources.plugins.index') ? route('filament.admin.resources.plugins.index') : null),
        ];
    }

    /** Drops the cached update.json so the next status() fetches it again. */
    public static function forget(): void
    {
        $url = self::manifest()['update_url'] ?? null;
        if (is_string($url)) \Illuminate\Support\Facades\Cache::forget('simplify.update-check.'.hash('sha256', $url));
    }

    private static function manifest(): array
    {
        $path = plugin_path('pelican-simplify', 'plugin.json');
        $data = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
        return is_array($data) ? $data : [];
    }
}
