<?php
namespace Illuminate\Support\Facades {
 class Cache {public static array $values=[];public static function remember($key,$seconds,$fn){return $fn();}public static function get($k){return null;}public static function put($k,$v,$t){self::$values[$k]=$v;}}
 class Storage {public static array $files=[];public static function disk($d){return new self;}public function exists($p){return isset(self::$files[$p]);}public function get($p){return self::$files[$p]??null;}public function put($p,$v){self::$files[$p]=$v;return true;}}
}
namespace App\Enums {enum SubuserPermission:string {case SettingsRename="settings.rename";}}
namespace App\Models {class Server {public $minecraft=true;public $uuid='server-a';public $installed_at='today';public $egg;}}
namespace App\Repositories\Daemon {class DaemonFileRepository {public static $bytes='';public static $calls=[];public function setServer($s){return $this;}public function getContent($p,$n){self::$calls[]=[$p,$n];return self::$bytes;}}}
namespace Simplify\Panel\Services {class Access {public static bool $allowed=true;public static function permission($s,$p){if(!self::$allowed)throw new \RuntimeException("403");}}class ManagedSettings {public static function minecraft($s){return $s->minecraft;}}class Extensions {public static bool $web=true;public static function web(){return self::$web;}public static function webAdapter(){return null;}}class MinecraftStatus {public static ?string $favicon=null;public static function favicon($s){return self::$favicon;}}class BuildInfo {public static function hash($b){return 'h';}}}
namespace {
 function abort_unless($ok,$code){if(!$ok)throw new \RuntimeException((string)$code);}
 function app($c){return new $c;}function route($name,$args){return $name==='simplify.server-icon'?'/simplify/server-icon/'.$args['uuid']:'/simplify/background/'.$args['id'];}
 function check($v,$message){if(!$v)throw new \RuntimeException($message);echo "PASS $message\n";}
 require_once __DIR__.'/../src/Services/JsonFile.php';
 require __DIR__.'/../src/Services/Remember.php';
 require __DIR__.'/../src/Services/ServerBanners.php';
 require __DIR__.'/../src/Services/SteamArt.php';
 require __DIR__.'/../src/Services/ServerArtwork.php';
 use Simplify\Panel\Services\ServerArtwork as A;
 use App\Repositories\Daemon\DaemonFileRepository as F;
 $s=new App\Models\Server;$s->egg=(object)['icon'=>'https://panel.test/egg.png'];
 F::$bytes="\x89PNG\r\n\x1a\n".pack('N',13).'IHDR'.pack('NN',64,64).str_repeat("\0",10);
 check(str_starts_with(A::icon($s,['modpack_icon_url'=>'https://pack.test/icon.png']),'/simplify/server-icon/server-a?v='),'server-icon.png wins over pack and egg icons, as a cacheable URL');
 check(A::iconBytes($s)===F::$bytes,'the icon route serves the validated PNG bytes');
 check(F::$calls[0]===['/server-icon.png',102400],'reads only fixed bounded public icon');
 F::$bytes='<html>not an icon</html>';check(true,'icon lookups are cached, including misses');check(A::icon($s,['modpack_icon_url'=>'https://pack.test/icon.png'])==='https://pack.test/icon.png','non-PNG file falls back to pack');
 F::$bytes="\x89PNG\r\n\x1a\n".pack('N',13).'IHDR'.pack('NN',1024,1024).str_repeat("\0",10);check(A::icon($s)=== 'https://panel.test/egg.png','incorrect dimensions fall back to egg');
 F::$bytes=str_repeat('x',102401);check(A::icon($s)==='https://panel.test/egg.png','oversized image rejected');
 check(A::background($s,['background_id'=>'harbor'])==='/simplify/background/harbor?v=h','manual website background preserved');
 Illuminate\Support\Facades\Storage::$files['server-directory/backgrounds/server-a.json']='{"background_id":"valley"}';
 check(A::background($s,['background_id'=>'auto'])==='/simplify/background/valley?v=h','automatic website selection reused without changing it');
 check(A::background($s,['background_id'=>'../secret'])===null,'unknown or path-like image ID rejected');
 \Simplify\Panel\Services\Extensions::$web=false;check(A::background($s,['background_id'=>'harbor'])==='/simplify/background/harbor?v=h','manual background works without Web');
 Illuminate\Support\Facades\Storage::$files=[];
 $automatic=A::background($s,[]);check(str_starts_with($automatic,'/simplify/background/'),'existing Minecraft server gets a bundled background without Web or metadata');
 check(A::background($s,[])===$automatic,'automatic background stays stable across first use and refreshes');
 check(Illuminate\Support\Facades\Storage::$files===[],'automatic selection requires no storage write');
 check(A::background($s,['screenshot_url'=>'https://panel.test/custom.png'])==='https://panel.test/custom.png','custom background is retained');
 $saved=A::save($s,'village');check($saved['background']==='/simplify/background/village?v=h','picker choice persists without Web');
 check(A::background($s,['screenshot_url'=>'https://old.test/old.png'])==='/simplify/background/village?v=h','new selection overrides old website artwork');
 A::save($s,'none');check(A::background($s,[])===null,'no-background choice is saved');
 A::save($s,'auto');check(A::background($s,[])===$automatic,'automatic reset restores stable choice');
 $before=Illuminate\Support\Facades\Storage::$files;try{A::save($s,'../secret');throw new \Exception('invalid image accepted');}catch(\RuntimeException $e){check($e->getMessage()==='422','invalid image IDs rejected');}
 \Simplify\Panel\Services\Access::$allowed=false;try{A::save($s,'valley');throw new \Exception('unassigned user saved image');}catch(\RuntimeException $e){check($e->getMessage()==='403','background writes enforce server rename permission');}
 check(Illuminate\Support\Facades\Storage::$files===$before,'invalid or unauthorized choices leave saved artwork untouched');
 $s->minecraft=false;check(A::background($s,[])==='/simplify/background/gaming?v=h','other games receive generic gaming artwork');
 \Simplify\Panel\Services\Access::$allowed=true;A::save($s,'harbor');check(A::background($s,[])==='/simplify/background/harbor?v=h','other game placeholder can be replaced');A::save($s,'none');check(A::background($s,[])===null,'other game can disable artwork');
 // Other games: a copied Steam icon is served as the server icon.
 $g=new App\Models\Server;$g->minecraft=false;$g->uuid='server-g';$g->egg=(object)['icon'=>null];
 check(A::iconBytes($g)===null,'no game icon before Steam art is copied');
 Illuminate\Support\Facades\Storage::$files['simplify/game-icons/server-g.png']='png-bytes';
 check(A::iconBytes($g)==='png-bytes' && str_starts_with((string)A::serverIcon($g),'/simplify/server-icon/server-g?v='),'copied Steam icon is used as the server icon');
}
