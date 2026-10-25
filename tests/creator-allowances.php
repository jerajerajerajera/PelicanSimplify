<?php
declare(strict_types=1);
namespace Illuminate\Validation {class ValidationException extends \RuntimeException {public static function withMessages($messages){return new self(json_encode($messages));}}}
namespace Illuminate\Support\Facades {
 class Storage {public static array $files=[];public static function disk($d){return new self;}public function exists($k){return isset(self::$files[$k]);}public function get($k){return self::$files[$k]??null;}public function put($k,$v){self::$files[$k]=$v;return true;}}
 class Cache {public static function lock(...$a){return new self;}public function block($s,$c){return $c();}}
}
namespace Simplify\Panel\Services {class FeatureSettings {public static function all(){return [];}}}
namespace {
require_once __DIR__.'/../src/Services/JsonFile.php';
require __DIR__.'/../src/Services/RoleAccess.php';
require __DIR__.'/../src/Services/CreatorAccess.php';
use Simplify\Panel\Services\CreatorAccess as A;
use Simplify\Panel\Services\RoleAccess as R;
use Illuminate\Support\Facades\Storage;
$GLOBALS['instances']=[];
function app($c){return $GLOBALS['instances'][$c]??=new $c;}
function report($e){}
function check($ok,$message){if(!$ok)throw new \RuntimeException($message);echo "PASS $message\n";}
function roles($v){Storage::$files[R::FILE]=json_encode($v);$GLOBALS['instances']=[];}
$config=A::defaults();$config['node_ids']=[1];
$user=(object)['id'=>10,'roles'=>[(object)['id'=>2],(object)['id'=>3]]];
roles(['everyone'=>['abilities'=>['files']],'roles'=>[['role_id'=>2,'abilities'=>['create'],'max_servers'=>3,'max_memory_mb'=>4096,'max_disk_mb'=>20480,'max_cpu'=>100,'max_backups'=>1],['role_id'=>3,'abilities'=>[],'max_servers'=>1,'max_memory_mb'=>16384,'max_disk_mb'=>10240,'max_cpu'=>300,'max_backups'=>4]]]);
check(A::allowed($user,$config),'a role with "Create servers" allows self-service');
check(!A::allowed((object)['id'=>11,'roles'=>[]],$config),'users without the ability cannot create');
$limits=A::effective($user,$config);
check($limits['max_memory_mb']===16384&&$limits['max_disk_mb']===20480&&$limits['max_servers']===3&&$limits['max_cpu']===300,'several roles: the highest limit per resource wins');
$config['user_limits']=[['user_id'=>10,'max_servers'=>1,'max_memory_mb'=>2048,'max_disk_mb'=>5120,'max_cpu'=>50,'max_backups'=>0]];
$limits=A::effective($user,$config);check($limits['max_cpu']===50&&$limits['max_memory_mb']===2048&&$limits['max_servers']===1,'old individual user limits still take precedence');
$payload=['memory'=>2048,'disk'=>5120,'cpu'=>50,'backup_limit'=>0];$usage=['count'=>0,'memory'=>0,'disk'=>0];A::checkQuota($payload,$usage,$limits);
foreach ([['memory'=>3072],['disk'=>6144],['cpu'=>51],['backup_limit'=>1]] as $over){try{A::checkQuota(array_replace($payload,$over),$usage,$limits);throw new \LogicException('quota bypassed');}catch(\Illuminate\Validation\ValidationException){}}
try{A::checkQuota($payload,['count'=>1,'memory'=>0,'disk'=>0],$limits);throw new \LogicException('count bypassed');}catch(\Illuminate\Validation\ValidationException){}
check(true,'effective RAM, disk, CPU, backup and server limits enforced by creation quota');
$stranger=(object)['id'=>12,'roles'=>[(object)['id'=>3]]];check(!A::allowed($stranger,$config),'limits alone do not grant creation');
$config['enabled']=true;$config['user_ids']=[12];check(A::allowed($stranger,$config),'old individual user grants remain compatible');
$config['node_ids']=[];check(!A::allowed($user,$config),'no hosting machine means no self-service');
}
