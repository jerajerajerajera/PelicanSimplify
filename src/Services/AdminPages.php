<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;

/**
 * Pelican's admin pages that Simplify does not replace, grouped for the dashboard's admin menu. Read from the
 * admin panel itself, so plugin pages appear too (under External, see ExternalPlugins) and each item honours
 * that page's own access check.
 */
final class AdminPages
{
    /** Basename => [group, icon]; anything else lands in "Other". */
    private const KNOWN = [
        'ServerResource' => ['Game servers', 'host'], 'EggResource' => ['Game servers', 'egg'], 'MountResource' => ['Game servers', 'files'],
        'NodeResource' => ['Infrastructure', 'monitor'], 'DatabaseHostResource' => ['Infrastructure', 'database'], 'BackupHostResource' => ['Infrastructure', 'backups'],
        'UserResource' => ['People & access', 'players'], 'RoleResource' => ['People & access', 'lock'], 'ApiKeyResource' => ['People & access', 'key'],
        'PluginResource' => ['Panel', 'plugins'], 'Settings' => ['Panel', 'gear'], 'WebhookResource' => ['Panel', 'link'], 'Health' => ['Panel', 'heart'], 'ListLogs' => ['Panel', 'activity'],
        'ManageAnnouncements' => ['Panel', 'bell'],
    ];
    private const GROUPS = ['Game servers', 'Infrastructure', 'People & access', 'Panel', 'External', 'Other'];
    /** Pages Simplify replaces or that need parameters. */
    private const SKIP = ['Dashboard', 'ViewLogs', 'SimplifySettings', 'CreateMinecraftServer', 'ManagePlugins', 'ManagePeople'];

    public static function menu(): array
    {
        if (!user()?->isAdmin()) return [];
        try {
            $panel = \Filament\Facades\Filament::getPanel('admin');
        } catch (\Throwable) {
            return [];
        }
        $groups = array_fill_keys(self::GROUPS, []);
        $people = \Simplify\Panel\Filament\Admin\Pages\ManagePeople::canAccess();
        foreach ([...$panel->getResources(), ...$panel->getPages()] as $class) {
            $name = class_basename($class);
            if (in_array($name, self::SKIP, true)) continue;
            try {
                $isResource = is_subclass_of($class, \Filament\Resources\Resource::class);
                if ($isResource ? !$class::canViewAny() : !$class::canAccess()) continue;
                if (!$isResource && method_exists($class, 'shouldRegisterNavigation') && !$class::shouldRegisterNavigation() && !isset(self::KNOWN[$name])) continue;
                $url = match (true) {
                    // Servers open straight on Pelican's (legacy) create form; plugins on Simplify's safe updater.
                    $name === 'ServerResource' => $class::canCreate() ? $class::getUrl('create', panel: 'admin') : $class::getUrl('index', panel: 'admin'),
                    $name === 'PluginResource' && FeatureSettings::enabled('plugin_manager') => \Simplify\Panel\Filament\Admin\Pages\ManagePlugins::getUrl(panel: 'admin'),
                    // People & roles add-on: both lists open Simplify's page on the matching tab.
                    $people && in_array($name, ['UserResource', 'RoleResource'], true) => \Simplify\Panel\Filament\Admin\Pages\ManagePeople::getUrl(['tab' => $name === 'RoleResource' ? 'roles' : 'people'], panel: 'admin'),
                    $isResource => $class::getUrl('index', panel: 'admin'),
                    default => $class::getUrl(panel: 'admin'),
                };
                $label = (string) $class::getNavigationLabel();
            } catch (\Throwable) {
                continue;
            }
            // Pages from other plugins (their class is in that plugin's namespace) go under External.
            $plugin = isset(self::KNOWN[$name]) ? null : ExternalPlugins::owner($class);
            if ($plugin && ExternalPlugins::isHidden('admin', $class)) continue;
            [$group, $icon] = self::KNOWN[$name] ?? ($plugin ? ['External', 'plugins'] : ['Other', 'legacy']);
            if ($name === 'ServerResource' && $class::canCreate()) $label = 'Create server (legacy)';
            if ($people && $name === 'UserResource') $label = 'People';
            if ($name === 'Settings') $label = 'Pelican settings';
            if ($name === 'PluginResource') $label = 'Plugin manager';
            $groups[$group][] = ['key' => strtolower($name), 'label' => $label, 'url' => $url, 'icon' => $icon, 'plugin' => $plugin['name'] ?? null];
        }
        $menu = [];
        foreach ($groups as $label => $items) {
            if (!$items) continue;
            usort($items, fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
            $menu[] = ['label' => $label, 'items' => $items];
        }
        return $menu;
    }

    /** Extra pages for host mode: the easy creator next to Eggs and Mounts, and Pelican's own plugin page under Legacy. */
    public static function hostExtras(): array
    {
        if (!user()?->isAdmin()) return [];
        $items = [];
        try {
            // One Server Maker for Minecraft and other games.
            if ((FeatureSettings::enabled('easy_creator') || FeatureSettings::enabled('game_creator')) && Extensions::canCreate()) $items[] = ['key' => 'easycreator', 'label' => 'Create server', 'url' => \Simplify\Panel\Filament\App\Pages\CreateServer::getUrl(panel: 'app'), 'icon' => 'plus'];
            $plugins = \App\Filament\Admin\Resources\Plugins\PluginResource::class;
            if (\Simplify\Panel\Filament\Admin\Pages\ManagePeople::canAccess() && FeatureSettings::enabled('legacy_pages')) {
                $items[] = ['key' => 'legacyusers', 'label' => 'Users (legacy)', 'url' => \App\Filament\Admin\Resources\Users\UserResource::getUrl('index', ['simplify_legacy' => 1], panel: 'admin'), 'icon' => 'players'];
                $items[] = ['key' => 'legacyroles', 'label' => 'Roles (legacy)', 'url' => \App\Filament\Admin\Resources\Roles\RoleResource::getUrl('index', ['simplify_legacy' => 1], panel: 'admin'), 'icon' => 'lock'];
            }
            if ($plugins::canViewAny() && FeatureSettings::enabled('legacy_pages')) $items[] = ['key' => 'legacyplugins', 'label' => 'Plugin manager (legacy)', 'url' => $plugins::getUrl('index', ['simplify_legacy' => 1], panel: 'admin'), 'icon' => 'plugins'];
        } catch (\Throwable) {
        }
        return $items;
    }
}
