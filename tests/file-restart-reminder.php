<?php
namespace App\Enums {enum SubuserPermission {case FileUpdate;case FileReadContent;case ControlRestart;case ControlStop;case ControlStart;}}
namespace App\Models {class Server {public string $uuid='server-test';public $installed_at=true;public $status=null;}class User {public static function find($id){return (object)['id'=>$id];}}}
namespace Illuminate\Support\Facades {
 class Storage {public static array $files=[];public static function disk($d){return new self;}public function get($p){return self::$files[$p]??null;}public function put($p,$v){self::$files[$p]=$v;return true;}public function delete($p){unset(self::$files[$p]);}}
 class Cache {public static function lock($k,$t){return new self;}public function block($t,$fn){return $fn();}public function get($fn){return $fn();}}
}
namespace App\Repositories\Daemon {class DaemonServerRepository {public static string $state='running';public static array $actions=[];public function setServer($s){return $this;}public function getDetails(){return ['state'=>self::$state];}public function power($a){self::$actions[]=$a;return $this;}public function throw(){}}class DaemonFileRepository {public function __construct(){throw new \RuntimeException('A restart reminder must never rewrite files');}}}
namespace Simplify\Panel\Services {class Access {public static bool $allowed=true;public static function permission($s,$p){if(!self::$allowed)throw new \RuntimeException('403');}public static function owner($s){throw new \RuntimeException('File editors do not require ownership');}}class ManagedSettings {public static function minecraft($s){return true;}}}
namespace {
require_once __DIR__.'/fixtures/lang.php';
 function user(){return (object)['id'=>7];}function abort_if($v,$code,$message=''){if($v)throw new \RuntimeException((string)$code);}function abort_unless($v,$code,$message=''){abort_if(!$v,$code,$message);}function app($c){return new $c;}function auth(){return new class{public function setUser($u){}public function user(){return user();}public function forgetUser(){}};}function report($e){}
 require_once __DIR__.'/../src/Services/JsonFile.php';
 require __DIR__.'/../src/Services/PendingChanges.php';use Simplify\Panel\Services\PendingChanges as P;use Illuminate\Support\Facades\Storage;use App\Repositories\Daemon\DaemonServerRepository as D;
 $s=new App\Models\Server;$check=function($v){if(!$v)throw new \RuntimeException('Restart reminder assertion failed');};P::fileChanged($s);$d=P::read($s);$check($d['file_only']&&$d['file_changes']);P::requestRestart($s,10);$check(P::read($s)['restart_at']>=time()+599&&D::$actions===[]);P::requestRestart($s,0);$check(D::$actions===['stop']);D::$state='offline';P::progress($s);$check(D::$actions===['stop','start']&&P::read($s)===null);P::fileChanged($s);P::discard($s);$check(P::read($s)===null);Simplify\Panel\Services\Access::$allowed=false;try{P::fileChanged($s);throw new \RuntimeException('Unauthorized reminder accepted');}catch(\RuntimeException $e){$check($e->getMessage()==='403');}echo "PASS durable file restart reminder, scheduled/immediate restart, no file rewrites, dismissal and permissions\n";
}
