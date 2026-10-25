<?php
namespace Illuminate\Support\Facades {class Storage {public static array $files=[];public static function disk($name){return new self;}public function exists($p){return isset(self::$files[$p]);}public function get($p){return self::$files[$p]??null;}public function put($p,$data){self::$files[$p]=$data;return true;}public function files($p){return array_values(array_filter(array_keys(self::$files),fn($f)=>str_starts_with($f,$p.'/')));}public function delete($paths){foreach((array)$paths as $p)unset(self::$files[$p]);return true;}public function path($p){return '/storage/'.$p;}}}
namespace Illuminate\Validation {class ValidationException extends \RuntimeException {public static function withMessages($messages){return new self(reset($messages));}}}
namespace App\Models {class Server {public function __construct(public string $uuid){}}}
namespace App\Enums {enum SubuserPermission:string {case SettingsRename='settings.rename';}}
namespace Simplify\Panel\Services {class Access {public static bool $allowed=true;public static function permission($s,$p){if(!self::$allowed)throw new \RuntimeException('403');}}}
namespace {
require_once __DIR__.'/../src/Services/JsonFile.php';
require __DIR__.'/../src/Services/UploadLimits.php';require __DIR__.'/../src/Services/ServerBanners.php';require __DIR__.'/../src/Services/ServerArtwork.php';
use Simplify\Panel\Services\ServerBanners as B;use Illuminate\Support\Facades\Storage as S;
$root=true;function user(){return new class {function isRootAdmin(){return $GLOBALS['root'];}};}
function abort_unless($ok,$code){if(!$ok)throw new \RuntimeException((string)$code);}
function route($name,$params){return '/banners/'.$params['id'];}
if(!function_exists('mb_substr')){function mb_substr($s,$start,$length){return substr($s,$start,$length);}}
function check($ok){if(!$ok)throw new \RuntimeException('Banner regression');}
$bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAEAAAAAgCAIAAAAt/+nTAAAAQElEQVR4nO3PQQ0AIBDAMED5SUcEj4ZkVbDtmVk/OzrgVQNaA1oDWgNaA1oDWgNaA1oDWgNaA1oDWgNaA1oD2gVLaQHAY0QxtQAAAABJRU5ErkJggg==');check(B::validateBytes($bytes)==='png');
foreach(['<svg></svg>',str_repeat('x',5*1024*1024+1),"<?php echo 'x';"] as $invalid){try{B::validateBytes($invalid);throw new \RuntimeException('accepted invalid upload');}catch(\Illuminate\Validation\ValidationException){}}
$path=__DIR__.'/fixtures/banner.png';check(file_get_contents($path)===$bytes);
$file=new class($path){function __construct(private $path){}function getSize(){return filesize($this->path);}function getRealPath(){return $this->path;}function getClientOriginalName(){return '../../My banner.png';}};
$a=new \App\Models\Server('server-a');$b=new \App\Models\Server('server-b');$item=B::upload($a,$file);$id=$item['id'];
check(B::validId($id)&&B::belongsTo($id,$a)&&!B::belongsTo($id,$b));check(count(B::entries($a))===1&&B::entries($b)===[]);check(B::metadata('../../secret')===null);
try{\Simplify\Panel\Services\ServerArtwork::save($b,$id);throw new \RuntimeException('accepted foreign banner');}catch(\RuntimeException $e){check($e->getMessage()==='422');}
S::$files['simplify/backgrounds/server-a.json']=json_encode(['background_id'=>$id]);
\Simplify\Panel\Services\Access::$allowed=false;
try{B::delete($id,$a);throw new \RuntimeException('deleted without permission');}catch(\RuntimeException $e){check($e->getMessage()==='403');}
$root=false;try{B::delete($id);throw new \RuntimeException('nonadmin deleted library entry');}catch(\RuntimeException $e){check($e->getMessage()==='403');}
$root=true;\Simplify\Panel\Services\Access::$allowed=true;B::delete($id,$a);check(B::metadata($id)===null&&B::entries()===[]);check(json_decode(S::$files['simplify/backgrounds/server-a.json'],true)['background_id']==='auto');
try{B::delete('valley');throw new \RuntimeException('deleted built-in banner');}catch(\RuntimeException $e){check($e->getMessage()==='404');}
echo "PASS validated uploads, scoped gallery, foreign-server rejection, protected deletion, automatic fallback and permanent bundled banners\n";
}
