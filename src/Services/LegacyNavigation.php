<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Server;
use Filament\Facades\Filament;
use Simplify\Panel\Filament\App\Pages\Dashboard;
final class LegacyNavigation
{
    public static function url(): ?string
    {
        $request = request();
        if (!user() || !FeatureSettings::enabled('dashboard') || $request->boolean('simplify_embed')
            || !$request->routeIs('filament.*') || $request->routeIs('*.auth.*', 'filament.app.pages.simplify', 'filament.app.pages.simple-server-creator')) return null;
        $server = Filament::getCurrentPanel()?->getId() === 'server' ? Filament::getTenant() : null;
        $parameters = $server instanceof Server && Access::linked($server) ? ['server' => $server->uuid] : [];
        return Dashboard::getUrl($parameters, panel: 'app');
    }
    public static function topbarVisible(): bool
    {
        return request()->routeIs('filament.admin.*', self::serverListRoute()) && self::url() !== null;
    }
    /** Pelican's own server list. Its resource uses the slug "/", so on beta38 and rc.1 the route is "filament.app.resources...index". */
    public static function serverListRoute(): string
    {
        try {
            return \App\Filament\App\Resources\Servers\Pages\ListServers::getRouteName(Filament::getPanel('app'));
        } catch (\Throwable) {
            return 'filament.app.resources.servers.index';
        }
    }
}
