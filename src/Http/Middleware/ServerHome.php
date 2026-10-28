<?php
namespace Simplify\Panel\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
final class ServerHome
{
    public function handle(Request $r,Closure $next): mixed
    {
        if(\Simplify\Panel\Services\FeatureSettings::enabled('dashboard') && !($r->boolean('simplify_legacy') && (user()?->isAdmin()||\Simplify\Panel\Services\RoleAccess::can('legacy'))) && $r->isMethod('GET') && !$r->boolean('simplify_embed') && $r->route()?->getName()==='filament.server.pages.console' && user()){
            $server=\Filament\Facades\Filament::getTenant();
            if($server && \Simplify\Panel\Services\Access::linked($server))return redirect(\Simplify\Panel\Filament\App\Pages\Dashboard::getUrl(['server'=>$server->uuid],panel:'app'));
        }
        return$next($r);
    }
}
