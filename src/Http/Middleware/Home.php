<?php
namespace Simplify\Panel\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Simplify\Panel\Filament\App\Pages\Dashboard;
final class Home
{
    public function handle(Request $r,Closure $next): mixed
    {
        if (\Simplify\Panel\Services\FeatureSettings::enabled('dashboard') && !($r->boolean('simplify_legacy') && (user()?->isAdmin()||\Simplify\Panel\Services\RoleAccess::can('legacy'))) && $r->isMethod('GET') && $r->routeIs(\Simplify\Panel\Services\LegacyNavigation::serverListRoute()) && user()) return redirect(Dashboard::getUrl(panel:'app'));
        return $next($r);
    }
}
