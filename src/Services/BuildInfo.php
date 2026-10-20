<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
/** Plugin version and the asset bundles served by AssetController. */
final class BuildInfo
{
    /** Published asset name => source files under resources/views. */
    public const BUNDLES = [
        'core.css' => ['css/tokens.css', 'css/components.css', 'css/native.css'],
        'dashboard.css' => ['css/motd.css', 'css/dashboard.css'],
        'wizard.css' => ['css/motd.css', 'css/wizard.css'],
        'core.js' => ['js/core.js'],
        'dashboard.js' => ['js/motd.js', 'js/dashboard.js'],
        'wizard.js' => ['js/motd.js', 'js/wizard.js'],
    ];
    /** Web fonts under resources/fonts (Monocraft, SIL Open Font License; see Monocraft-OFL.txt). */
    public const FONTS = ['monocraft-regular.woff2', 'monocraft-bold.woff2', 'monocraft-italic.woff2', 'monocraft-bold-italic.woff2'];
    private static ?string $version = null;
    private static array $hashes = [];

    public static function version(): ?string
    {
        if (self::$version !== null) return self::$version ?: null;
        $path = plugin_path('pelican-simplify', 'plugin.json');
        $manifest = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
        $version = is_array($manifest) ? ($manifest['version'] ?? null) : null;
        self::$version = is_string($version) && preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) ? $version : '';
        return self::$version ?: null;
    }

    /** Source files of a bundle, or null for unknown names. */
    public static function files(string $bundle): ?array
    {
        $files = self::BUNDLES[$bundle] ?? null;
        return $files ? array_map(fn (string $file): string => plugin_path('pelican-simplify', 'resources/views/'.$file), $files) : null;
    }

    /** Version token for cache busting: plugin version plus the bundle's file times. */
    public static function hash(string $bundle): string
    {
        if (isset(self::$hashes[$bundle])) return self::$hashes[$bundle];
        $stamp = (string) self::version();
        foreach (self::files($bundle) ?? [] as $file) $stamp .= '|'.(@filemtime($file) ?: 0);
        return self::$hashes[$bundle] = substr(hash('sha256', $stamp), 0, 12);
    }

    public static function asset(string $bundle): string
    {
        return route('simplify.asset', ['asset' => $bundle]).'?v='.self::hash($bundle);
    }
}
