<?php
declare(strict_types=1);
namespace App\Models {class Server {public function __construct(public string $uuid){}}}
namespace Filament\Facades {class Filament {public static string $panel='app';public static $tenant=null;public static function getCurrentPanel(){return new class{function getId(){return Filament::$panel;}};}public static function getTenant(){return self::$tenant;}public static function getPanel($id){return $id;}}}
namespace App\Filament\App\Resources\Servers\Pages {class ListServers {public static function getRouteName($panel){if($panel!=='app')throw new \RuntimeException('wrong panel');return 'filament.app.resources...index';}}}
namespace Simplify\Panel\Filament\App\Pages {class Dashboard {public static function getUrl($parameters=[], $panel=null){if($panel!=='app')throw new \RuntimeException('wrong target panel');return '/simplify'.(isset($parameters['server'])?'?server='.$parameters['server']:'');}}}
namespace Simplify\Panel\Services {class FeatureSettings {public static bool $enabled=true;public static function enabled($key){return self::$enabled;}}class Access {public static bool $linked=true;public static function linked($server){return self::$linked;}}}
namespace {
 require __DIR__.'/../src/Services/LegacyNavigation.php';
 use Simplify\Panel\Services\LegacyNavigation as N;
 use Filament\Facades\Filament as F;
 $authenticated=true;$route='filament.app.resources...index';$embed=false;
 function user(){return $GLOBALS['authenticated']?(object)['id'=>1]:null;}
 function request(){return new class{function boolean($key){return $GLOBALS['embed'];}function routeIs(...$patterns){foreach($patterns as $pattern)if(fnmatch($pattern,$GLOBALS['route']))return true;return false;}};}
 function check($ok){if(!$ok)throw new \RuntimeException('Legacy navigation regression');}
 check(N::serverListRoute()==='filament.app.resources...index');
 check(N::url()==='/simplify'&&N::topbarVisible());
 F::$panel='admin';$route='filament.admin.pages.dashboard';check(N::url()==='/simplify'&&N::topbarVisible());
 F::$panel='server';F::$tenant=new \App\Models\Server('server-a');$route='filament.server.pages.console';check(N::url()==='/simplify?server=server-a'&&!N::topbarVisible());
 \Simplify\Panel\Services\Access::$linked=false;check(N::url()==='/simplify');
 $embed=true;check(N::url()===null);$embed=false;
 foreach(['filament.app.pages.simplify','filament.app.pages.simple-server-creator','filament.app.auth.login','api.servers.index'] as $route)check(N::url()===null);
 $route='filament.admin.pages.dashboard';$authenticated=false;check(N::url()===null);$authenticated=true;
 \Simplify\Panel\Services\FeatureSettings::$enabled=false;check(N::url()===null);
 echo "PASS native server list route (slug /), overview header placement, admin return, linked-server target, no unauthorized deep link, embed/auth exclusions and dashboard toggle\n";
}
