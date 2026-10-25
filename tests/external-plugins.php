<?php
declare(strict_types=1);
// Other plugins: overlaps switch only the named Simplify feature off (also when main's published list predates
// "overlaps"), and account-level plugin pages are listed only when the user may open them.
namespace Illuminate\Support\Facades {
 class Cache {public static array $store=[];public static function remember($key,$ttl,$fn){return self::$store[$key]??=$fn();}}
 class Http {public static $remote=null;public static function timeout($s){return new self;}public function connectTimeout($s){return $this;}public function get($u){if(self::$remote===null)throw new \RuntimeException('offline');return $this;}public function throw(){return $this;}public function json(){return self::$remote;}}
 class Storage {public static function disk($d){return new class{public function exists($p){return false;}public function get($p){return null;}};}}
}
namespace App\Models {class Plugin {public static array $rows=[];public static function all(){return self::$rows;}}}
namespace Filament\Resources {class Resource {}}
namespace Filament\Facades {class Filament {public static array $panels=[];public static function getPanel($id){return self::$panels[$id]??throw new \RuntimeException('no panel');}}}
namespace App\Filament\Server\Pages {class Console {protected static array $customWidgets=['above_console'=>['Vendor\\Legal\\Widgets\\ServerPlayerWidget','Other\\Widget'],'bottom'=>['Vendor\\Legal\\Widgets\\ServerPlayerWidget']];}}
namespace Vendor\Legal\Pages {
 class Imprint {public static function shouldRegisterNavigation(){return true;}public static function canAccess(){return true;}public static function getUrl(...$a){return '/imprint';}public static function getNavigationLabel(){return 'Imprint';}}
 class Secret {public static function shouldRegisterNavigation(){return true;}public static function canAccess(){return false;}public static function getUrl(...$a){return '/secret';}public static function getNavigationLabel(){return 'Secret';}}
}
namespace {
 function class_basename($c){return basename(str_replace('\\','/',$c));}
 require_once __DIR__.'/../src/Services/JsonFile.php';
 require __DIR__.'/../src/Services/ExternalPlugins.php';
 use Simplify\Panel\Services\ExternalPlugins as X;
 $reset=function(array $plugins,$remote){
  \App\Models\Plugin::$rows=array_map(fn($p)=>(object)['id'=>$p[0],'name'=>$p[1],'namespace'=>$p[2],'status'=>'enabled'],$plugins);
  \Illuminate\Support\Facades\Cache::$store=[];\Illuminate\Support\Facades\Http::$remote=$remote;
  foreach(['plugins','overlaps'] as $p){$r=new \ReflectionProperty(X::class,$p);$r->setValue(null,null);}
 };
 $check=function(bool $ok,string $what){if(!$ok)throw new \RuntimeException($what);};

 // Offline: the bundled list applies. Subdomains switches only Simplify's subdomains off, never the dashboard.
 $reset([['subdomains','Subdomains','Boy132\\Subdomains']],null);
 $check(X::conflicts()===[],'an overlap must not switch the dashboard off');
 $check((X::overlapFor('subdomains')['name']??null)==='Subdomains','subdomains plugin switches Simplify subdomains off');
 $check(X::overlapFor('packages')===null,'other features stay on');

 // Main's published list from before 0.5.0 has no "overlaps": the bundled overlaps still apply.
 $reset([['subdomains','Subdomains','Boy132\\Subdomains']],['plugins'=>[]]);
 $check(X::overlapFor('subdomains')!==null,'bundled overlaps apply when the published list has none');

 // Notice-only overlap: listed, nothing switched off.
 $reset([['minecraft-modrinth','Minecraft Modrinth','Boy132\\MinecraftModrinth']],null);
 $check(count(X::overlaps())===1&&X::overlaps()[0]['features']===[],'modrinth is a notice only');
 $check(X::overlapFor('packages')===null,'modrinth leaves Simplify mods and plugins on');

 // Account-level pages: only pages from plugins the user may open.
 $reset([['legal-pages','Legal Pages','Vendor\\Legal']],null);
 \Filament\Facades\Filament::$panels['app']=new class{public function getResources(){return [];}public function getPages(){return [\Vendor\Legal\Pages\Imprint::class,\Vendor\Legal\Pages\Secret::class,'Simplify\\Panel\\Filament\\App\\Pages\\Dashboard'];}};
 $pages=X::appPages();
 $check(array_column($pages,'label')===['Imprint']&&$pages[0]['plugin']==='Legal Pages'&&$pages[0]['url']==='/imprint','account pages: '.json_encode($pages));
 echo "PASS plugin overlaps switch off only their feature, bundled overlaps with an older published list, account pages and console widgets from plugins\n";
}
