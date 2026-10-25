<?php
declare(strict_types=1);
namespace Simplify\Panel\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
final class CreatorFramePolicy
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response=$next($request);
        // Admin pages open in the dashboard's admin tools popup, including records clicked inside it; account pages
        // (Simplify's own, profile, plugin pages and plugin widgets) open in popups and tiles.
        if($request->isMethod('GET')&&user()&&($request->routeIs('filament.server.*','filament.admin.*','filament.app.auth.profile')||($request->routeIs('filament.app.*')&&!$request->routeIs('filament.app.auth.*')))){
            // The native SetSecurityHeaders middleware preserves an already set header.
            // Includes native server popups. Authorization remains enforced by the page; other origins remain blocked.
            $response->headers->set('X-Frame-Options','SAMEORIGIN');
            $csp=$response->headers->get('Content-Security-Policy');
            if ($csp && preg_match('/(?:^|;)\s*frame-ancestors\s/i',$csp)) {
                $directives=array_map(static fn(string $directive):string => preg_match('/^\s*frame-ancestors\s/i',$directive)?"frame-ancestors 'self'":trim($directive),explode(';',$csp));
                $response->headers->set('Content-Security-Policy',implode('; ',$directives));
            }
        }
        return $response;
    }
}
