<?php
declare(strict_types=1);
namespace App\Enums {enum SubuserPermission {case FileUpdate;case FileReadContent;case FileCreate;}}
namespace App\Models {class Server {public string $uuid='test';public int $memory=4096,$cpu=200,$disk=10240;public function refresh(){}}}
namespace Illuminate\Support\Facades {
class Storage {public static array $files=[];public static int $writes=0;public static function disk($d){return new self;}public function get($key){return self::$files[$key]??null;}public function put($key,$value){self::$writes++;self::$files[$key]=$value;return true;}public function delete($key){unset(self::$files[$key]);}}
class Cache {public static function lock(...$args){return new self;}public function block($seconds,$callback){return $callback();}}
}
namespace App\Repositories\Daemon {class DaemonFileRepository {public static string $properties="motd=Hello\nmax-players=20\ndifficulty=2\ngamemode=0\npvp=true\nwhite-list=false\nallow-flight=false\nview-distance=10\nspawn-protection=16\n";public function setServer($s){return $this;}public function getContent($path,$limit){return self::$properties;}}class DaemonServerRepository {public function __construct(){throw new \RuntimeException('Unchanged settings must not contact the power endpoint');}}}
namespace Illuminate\Validation {class ValidationException extends \RuntimeException {public static function withMessages(array $m){return new self(implode(' ',$m));}}}
namespace Simplify\Panel\Services {
class Access {public static function permission(...$args){}public static function owner(...$args){}}
class ManagedSettings {public static function minecraft($s){return true;}}
class ManagedResources {public static function canEdit($s){return true;}}
class CreatorAccess {public static bool $admin=true;public static function admin(){return self::$admin;}}
class ServerProperties {public static function prepare($state,$flag){return ['properties'=>array_intersect_key($state,array_flip(['motd','max-players','difficulty','gamemode','pvp','white-list','allow-flight','view-distance','spawn-protection'])),'icon'=>null];}}
}
namespace {
function user(){return (object)['id'=>7];}function app($class){return new $class;}function abort_if($condition,$code,$message=''){if($condition)throw new \RuntimeException($message);}function abort_unless($condition,$code){abort_if(!$condition,$code);}function validator($state,$rules){return new class($state){public function __construct(private array $state){}public function validate(){return array_intersect_key($this->state,array_flip(['memory_gb','disk_mb','cpu']));}};}
require __DIR__.'/../src/Services/PropertiesReader.php';require __DIR__.'/../src/Services/SettingsDifference.php';require __DIR__.'/../src/Services/ResourceLimits.php';require __DIR__.'/../src/Services/PendingChanges.php';
use Simplify\Panel\Services\SettingsDifference as D;use Simplify\Panel\Services\PendingChanges as P;use Illuminate\Support\Facades\Storage as S;
function check($ok,$message){if(!$ok)throw new \RuntimeException($message);echo "PASS $message\n";}
$server=new \App\Models\Server;$state=\Simplify\Panel\Services\PropertiesReader::parse(\App\Repositories\Daemon\DaemonFileRepository::$properties);$state['difficulty']='normal';$state['gamemode']='survival';$state['max-players']=20;
$result=P::stage($server,$state);check($result['changed']===false&&$result['pending']===false&&S::$writes===0,'unchanged MOTD and equivalent numeric settings produce no writes or restart reminder');
check(D::properties(['motd'=>'Changed'],['motd'=>'Hello'])===['motd'=>'Changed'],'real MOTD changes are detected');
check(D::properties(['motd'=>'§aHello'],['motd'=>'Hello'])!==[],'Minecraft formatting changes count as real changes');
check(D::properties(['motd'=>"Hello\r\nWorld"],['motd'=>"Hello\nWorld"])===[],'textarea newline normalization does not create a false change');
$result=P::stage($server,['memory_gb'=>'4','cpu'=>'200','disk_mb'=>'10240'],null,true);check(!$result['changed']&&!$result['pending']&&S::$writes===0,'unchanged resource values produce no restart reminder');
$path='simplify/pending/test.json';$old=['actor_id'=>7,'minecraft'=>true,'properties'=>['motd'=>'Pending'],'icon'=>null,'created_at'=>1,'restart_at'=>9999999999,'restart_requested'=>false];S::$files[$path]=json_encode($old);$state['motd']='Pending';$result=P::stage($server,$state);check(!$result['changed']&&$result['pending']&&P::read($server)===$old,'saving unchanged staged settings preserves its planned restart');
S::$files[$path]=json_encode(['actor_id'=>7,'file_only'=>true,'file_changes'=>true,'properties'=>[],'icon'=>null,'created_at'=>1,'restart_at'=>9999999999,'restart_requested'=>false]);$state['motd']='Hello';$before=S::$files[$path];$result=P::stage($server,$state);check(!$result['changed']&&S::$files[$path]===$before,'unchanged settings preserve an existing file restart reminder');
// Limits from servers created elsewhere save unchanged: unlimited CPU/disk (administrators) and RAM that is not whole GB.
use Simplify\Panel\Services\ResourceLimits as L;
S::$files=[];$server->cpu=0;$server->disk=0;$server->memory=6000;
$result=P::stage($server,['memory_gb'=>6000/1024,'cpu'=>0,'disk_mb'=>0],null,true);check(!$result['changed']&&!$result['pending'],'unlimited CPU and storage and 6000 MB RAM are accepted and seen as unchanged');
check(L::from(['memory_gb'=>6000/1024,'cpu'=>0,'disk_mb'=>0])===['memory'=>6000,'cpu'=>0,'disk'=>0],'limits keep exact megabytes');
check(D::resources(L::pending(L::from(['memory_gb'=>8,'cpu'=>0,'disk_mb'=>0])),$server)&&!D::resources(L::pending(L::from(['memory_gb'=>6000/1024,'cpu'=>0,'disk_mb'=>0])),$server),'a RAM change is detected, the same limits are not');
\Simplify\Panel\Services\CreatorAccess::$admin=false;
try{L::from(['memory_gb'=>4,'cpu'=>0,'disk_mb'=>10240]);$ok=false;}catch(\Illuminate\Validation\ValidationException){$ok=true;}check($ok,'only administrators may set unlimited CPU');
try{L::from(['memory_gb'=>'abc','cpu'=>100,'disk_mb'=>10240]);$ok=false;}catch(\Illuminate\Validation\ValidationException){$ok=true;}check($ok,'invalid RAM is refused');
}
