<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;

/**
 * The panel as an installable web app: a manifest with the panel's name and Simplify's icons, and a small service
 * worker that only shows an offline page when the panel cannot be reached (nothing is cached otherwise).
 */
final class WebApp
{
    public const ICON_SIZES = [180, 192, 512];
    /** Light and dark page background (tokens.css --ps-bg), used for the splash screen and the status bar. */
    public const LIGHT = '#f4f4f5';
    public const DARK = '#101012';

    public static function name(): string
    {
        $brand = trim((string) (AppearanceSettings::all()['brand']['text'] ?? ''));
        return $brand !== '' ? $brand : ((string) config('app.name', 'Pelican') ?: 'Pelican');
    }

    public static function startUrl(): string
    {
        try {
            if (FeatureSettings::enabled('dashboard')) return \Simplify\Panel\Filament\App\Pages\Dashboard::getUrl(panel: 'app');
        } catch (\Throwable) {
        }
        return url('/');
    }

    /** Path of the panel root, the scope of the app and its service worker ("/" unless Pelican runs in a folder). */
    public static function scope(): string
    {
        return rtrim((string) (parse_url(url('/'), PHP_URL_PATH) ?: ''), '/').'/';
    }

    public static function icon(int $size): string
    {
        return route('simplify.app-icon', ['size' => $size]).'?v='.self::version();
    }

    /** Changes with the plugin version, so installed apps pick up new icons and offline pages. */
    public static function version(): string
    {
        return substr(hash('sha256', (string) BuildInfo::version()), 0, 12);
    }

    public static function manifest(): array
    {
        $name = self::name();
        return [
            'id' => self::scope(),
            'name' => $name,
            'short_name' => mb_strlen($name) > 12 ? mb_substr($name, 0, 12) : $name,
            'description' => __('pelican-simplify::dashboard.app.description', ['name' => $name]),
            'start_url' => self::startUrl(),
            'scope' => self::scope(),
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => self::LIGHT,
            'theme_color' => self::LIGHT,
            'icons' => [
                ['src' => self::icon(192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => self::icon(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => self::icon(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];
    }

    /** The service worker with this version and the panel's name filled in. */
    public static function worker(): string
    {
        $source = (string) file_get_contents(plugin_path('pelican-simplify', 'resources/web-app/worker.js'));
        // The name lands in HTML inside a JavaScript template string: escaped for both.
        $name = strtr(htmlspecialchars(self::name(), ENT_QUOTES), ['`' => '&#96;', '$' => '&#36;', '\\' => '&#92;']);
        return strtr($source, ['__VERSION__' => self::version(), '__NAME__' => $name, '__ICON__' => str_replace(['\\', "'"], ['', ''], self::icon(192))]);
    }
}
