<?php
declare(strict_types=1);
// Installable web app: the manifest uses the panel's name and scope (also in a folder), the icons exist in the
// sizes the manifest names, and the service worker escapes the name and only handles page loads.
namespace Simplify\Panel\Services {
 final class AppearanceSettings {public static array $data=['brand'=>['text'=>'']];public static function all():array{return self::$data;}}
 final class FeatureSettings {public static bool $dashboard=true;public static function enabled(string $k):bool{return self::$dashboard;}}
 final class BuildInfo {public static function version():?string{return '0.5.0-snapshot.4';}}
}
namespace Simplify\Panel\Filament\App\Pages {final class Dashboard {public static function getUrl(...$a):string{return \url('/').'/simplify';}}}
namespace {
require_once __DIR__.'/fixtures/lang.php';
 $GLOBALS['base']='https://panel.example.com';
 $GLOBALS['config']=['app.name'=>'Pelican'];
 function url($p=''){return $GLOBALS['base'];}
 function config($k,$d=null){return $GLOBALS['config'][$k]??$d;}
 function route($name,$params=[]){return $GLOBALS['base'].'/simplify/app-icon/'.($params['size']??'').'.png';}
 function plugin_path($id,$path){return dirname(__DIR__).'/'.$path;}
 require __DIR__.'/../src/Services/WebApp.php';
 use Simplify\Panel\Services\WebApp;
 $check=function(bool $ok,string $what){if(!$ok)throw new \RuntimeException($what);};

 $m=WebApp::manifest();
 $check($m['name']==='Pelican'&&$m['short_name']==='Pelican','name falls back to the app name');
 $check($m['scope']==='/'&&$m['id']==='/'&&$m['display']==='standalone','root scope, standalone');
 $check($m['start_url']==='https://panel.example.com/simplify','opens the dashboard');
 foreach($m['icons'] as $icon){
  preg_match('/(\d+)x\1/',$icon['sizes'],$size);
  $check(in_array((int)$size[1],WebApp::ICON_SIZES,true),'icon size is served: '.$icon['sizes']);
 }
 foreach(WebApp::ICON_SIZES as $size){
  $info=getimagesize(dirname(__DIR__).'/resources/branding/app-icon-'.$size.'.png');
  $check($info!==false&&$info[0]===$size&&$info[1]===$size&&$info['mime']==='image/png','icon '.$size.' is a '.$size.' px PNG');
 }
 $check(in_array('maskable',array_column($m['icons'],'purpose'),true),'a maskable icon for Android');

 // The panel name from the brand settings, shortened for the home screen.
 \Simplify\Panel\Services\AppearanceSettings::$data=['brand'=>['text'=>'Deckzwabbers Server']];
 $m=WebApp::manifest();
 $check($m['name']==='Deckzwabbers Server'&&$m['short_name']==='Deckzwabbers','brand name, short name of 12 characters');

 // Pelican in a folder: scope and worker scope follow it. Dashboard off: the panel home.
 $GLOBALS['base']='https://example.com/panel';
 \Simplify\Panel\Services\FeatureSettings::$dashboard=false;
 $check(WebApp::scope()==='/panel/'&&WebApp::manifest()['start_url']==='https://example.com/panel','folder scope: '.WebApp::scope());

 // A hostile name cannot break out of the worker's template string or the offline page.
 \Simplify\Panel\Services\AppearanceSettings::$data=['brand'=>['text'=>'a`${alert(1)}<b>\\']];
 $worker=WebApp::worker();
 $check(!str_contains($worker,'`${alert')&&!str_contains($worker,'<b>')&&!str_contains($worker,'__NAME__')&&!str_contains($worker,'__VERSION__'),'worker escapes the name');
 $check(str_contains($worker,"event.request.mode !== 'navigate' || event.request.method !== 'GET'"),'worker only handles page loads');
 echo "PASS web app manifest, icons, folder scope and service worker\n";
}
