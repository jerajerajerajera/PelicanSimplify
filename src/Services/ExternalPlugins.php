<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Other installed Pelican plugins, as Simplify sees them. A page belongs to a plugin when its class lives in
 * that plugin's namespace (from its plugin.json), so plugin pages can be shown under "External". Plugins
 * known to clash with Simplify are listed in conflicts.json: while one is enabled, Simplify's dashboard switches
 * itself off, or only the Simplify features the plugin overlaps with.
 */
final class ExternalPlugins
{
    private const SELF = 'pelican-simplify';
    private static ?array $plugins = null;

    /** Enabled plugins other than Simplify: id => [name, namespace]. */
    public static function plugins(): array
    {
        if (self::$plugins !== null) return self::$plugins;
        $list = [];
        try {
            foreach (\App\Models\Plugin::all() as $plugin) {
                $status = is_object($plugin->status) ? $plugin->status->value : (string) $plugin->status;
                if ($plugin->id === self::SELF || $status !== 'enabled' || !is_string($plugin->namespace) || $plugin->namespace === '') continue;
                $list[$plugin->id] = ['id' => $plugin->id, 'name' => (string) ($plugin->name ?: $plugin->id), 'namespace' => trim($plugin->namespace, '\\').'\\'];
            }
        } catch (\Throwable) {
        }
        return self::$plugins = $list;
    }

    /** The plugin a page or resource class comes from, or null for Pelican's own pages. */
    public static function owner(string $class): ?array
    {
        $class = ltrim($class, '\\');
        foreach (self::plugins() as $plugin) if (str_starts_with($class, $plugin['namespace'])) return $plugin;
        return null;
    }

    /**
     * The published list (conflicts.json next to update.json, so it can grow without a release), with the bundled copy
     * as fallback per section: "plugins" switch Simplify's dashboard off, "overlaps" only the Simplify features they
     * name. Versions before 0.5.0 only read "plugins", so overlaps never switch their dashboard off.
     */
    private static function published(string $section): array
    {
        $lists = Cache::remember('simplify.conflicts.v2', 3600, function (): array {
            $bundled = json_decode((string) @file_get_contents(dirname(__DIR__, 2).'/conflicts.json'), true);
            try {
                $url = 'https://raw.githubusercontent.com/jerajerajerajera/PelicanSimplify/main/conflicts.json';
                $remote = Http::timeout(4)->connectTimeout(2)->get($url)->throw()->json();
            } catch (\Throwable) {
                $remote = null;
            }
            $lists = [];
            foreach (['plugins', 'overlaps'] as $key) {
                $list = is_array($remote[$key] ?? null) ? $remote[$key] : (is_array($bundled[$key] ?? null) ? $bundled[$key] : []);
                $lists[$key] = array_values(array_filter($list, 'is_array'));
            }
            return $lists;
        });
        return $lists[$section] ?? [];
    }

    /** Known conflicts: [{id?, name?, reason}]. */
    public static function conflictList(): array
    {
        return self::published('plugins');
    }

    /** Enabled plugins listed in $list, with the matching entry: [[plugin, entry]]. */
    private static function matches(array $list): array
    {
        $found = [];
        foreach (self::plugins() as $plugin) {
            foreach ($list as $entry) {
                $byId = isset($entry['id']) && strcasecmp((string) $entry['id'], $plugin['id']) === 0;
                $byName = isset($entry['name']) && strcasecmp(trim((string) $entry['name']), trim($plugin['name'])) === 0;
                if ($byId || $byName) {
                    $found[] = [$plugin, $entry];
                    break;
                }
            }
        }
        return $found;
    }

    private static ?array $overlaps = null;

    /**
     * Enabled plugins that do part of what Simplify does: [{id, name, reason, features}]. The Simplify features they
     * name are switched off while the plugin is enabled; without features it is only a notice in the settings.
     */
    public static function overlaps(): array
    {
        return self::$overlaps ??= array_map(fn (array $match): array => [
            'id' => $match[0]['id'], 'name' => $match[0]['name'], 'reason' => (string) ($match[1]['reason'] ?? 'It does part of what Simplify does.'),
            'features' => array_values(array_filter((array) ($match[1]['features'] ?? []), 'is_string')),
        ], self::matches(self::published('overlaps')));
    }

    /** The overlapping plugin that switches a Simplify feature off, or null. */
    public static function overlapFor(string $feature): ?array
    {
        foreach (self::overlaps() as $overlap) if (in_array($feature, $overlap['features'], true)) return $overlap;
        return null;
    }

    /** Enabled plugins that clash with Simplify, with the reason: [{id, name, reason}]. */
    public static function conflicts(): array
    {
        return array_map(fn (array $match): array => ['id' => $match[0]['id'], 'name' => $match[0]['name'], 'reason' => (string) ($match[1]['reason'] ?? 'It changes the same parts of the panel as Simplify.')], self::matches(self::conflictList()));
    }

    /**
     * Pages other plugins add to the server panel, as links for one server: [{key, label, url, plugin}].
     * Each page still runs its own access check when it opens.
     */
    public static function serverPages(object $server): array
    {
        return self::pages('server', $server);
    }

    /**
     * Pages other plugins add to the account-level app panel (such as Legal Pages), for the account menu:
     * [{key, label, url, plugin}]. Pages the user may not open are left out.
     */
    public static function appPages(): array
    {
        return self::pages('app');
    }

    private static function pages(string $panelId, ?object $tenant = null): array
    {
        if (self::plugins() === []) return [];
        try {
            $panel = \Filament\Facades\Filament::getPanel($panelId);
        } catch (\Throwable) {
            return [];
        }
        $pages = [];
        foreach ([...$panel->getResources(), ...$panel->getPages()] as $class) {
            $plugin = self::owner($class);
            if (!$plugin || self::isHidden($panelId, $class)) continue;
            try {
                $isResource = is_subclass_of($class, \Filament\Resources\Resource::class);
                if (!$isResource && method_exists($class, 'shouldRegisterNavigation') && !$class::shouldRegisterNavigation()) continue;
                if ($tenant === null && ($isResource ? !$class::canViewAny() : !$class::canAccess())) continue;
                $url = $isResource ? $class::getUrl('index', panel: $panelId, tenant: $tenant) : $class::getUrl(panel: $panelId, tenant: $tenant);
                $label = (string) $class::getNavigationLabel();
            } catch (\Throwable) {
                continue;
            }
            $pages[] = ['key' => 'ext-'.strtolower(class_basename($class)), 'label' => $label, 'url' => $url, 'plugin' => $plugin['name']];
        }
        usort($pages, fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
        return $pages;
    }

    private const HIDDEN_FILE = 'simplify/external-pages.json';

    /** Every page other plugins add to the admin, server and account panels: key => "Label — Plugin (Admin/Server/Account)". */
    public static function catalog(): array
    {
        $list = [];
        foreach (['admin' => 'Admin', 'server' => 'Server', 'app' => 'Account'] as $panelId => $where) {
            try {
                $panel = \Filament\Facades\Filament::getPanel($panelId);
            } catch (\Throwable) {
                continue;
            }
            foreach ([...$panel->getResources(), ...$panel->getPages()] as $class) {
                $plugin = self::owner($class);
                if (!$plugin) continue;
                try {
                    if (!is_subclass_of($class, \Filament\Resources\Resource::class) && method_exists($class, 'shouldRegisterNavigation') && !$class::shouldRegisterNavigation()) continue;
                    $label = (string) $class::getNavigationLabel();
                } catch (\Throwable) {
                    continue;
                }
                $list[self::key($panelId, $class)] = $label.' — '.$plugin['name'].' ('.$where.')';
            }
        }
        asort($list, SORT_NATURAL | SORT_FLAG_CASE);
        return $list;
    }

    public static function key(string $panel, string $class): string
    {
        return $panel.':'.strtolower(class_basename($class));
    }

    /** Keys of plugin pages an administrator chose to hide in Simplify. */
    public static function hidden(): array
    {
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk('local');
            $data = $disk->exists(self::HIDDEN_FILE) ? json_decode((string) $disk->get(self::HIDDEN_FILE), true) : [];
        } catch (\Throwable) {
            $data = [];
        }
        return array_values(array_filter((array) ($data['hidden'] ?? []), 'is_string'));
    }

    public static function isHidden(string $panel, string $class): bool
    {
        return in_array(self::key($panel, $class), self::hidden(), true);
    }

    /** Stores which pages are shown; pages not in the catalog (yet) keep their current state. */
    public static function saveShown(array $shown): void
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $catalog = array_keys(self::catalog());
        $shown = array_intersect(array_map('strval', $shown), $catalog);
        $hidden = array_values(array_unique([...array_diff(self::hidden(), $catalog), ...array_diff($catalog, $shown)]));
        \Illuminate\Support\Facades\Storage::disk('local')->put(self::HIDDEN_FILE, json_encode(['hidden' => $hidden], JSON_THROW_ON_ERROR));
    }
}
