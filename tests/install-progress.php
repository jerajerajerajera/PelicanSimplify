<?php
declare(strict_types=1);
namespace App\Enums {enum ServerState:string {case Installing='installing';case InstallFailed='install_failed';case ReinstallFailed='reinstall_failed';}}
namespace App\Models {class Server {public string $uuid='u1';public ?object $status=null;public $installed_at=null;public $updated_at=null;public ?int $egg_id=1;public array $saved=[];public function forceFill(array $a){foreach($a as $k=>$v)$this->$k=$v;return $this;}public function save(){$this->saved[]=$this->status?->value;return true;}}}
namespace App\Repositories\Daemon {class DaemonServerRepository {public static array $calls=[];public static bool $fail=false;public function setServer($s){return $this;}public function delete(){self::$calls[]='delete';}public function create($start=true){if(self::$fail)throw new \RuntimeException('Wings down');self::$calls[]='create:'.($start?'start':'stay');}public function getInstallLogs(){return "\e[1mInstalling\e[0m\nDone\n";}}}
namespace App\Services\Servers {class ReinstallServerService {public static int $runs=0;public function handle($s){self::$runs++;$s->status=\App\Enums\ServerState::Installing;}}}
namespace Illuminate\Support\Facades {
class Storage {public static array $files=[];public static function disk($d){return new self;}public function put($p,$c){self::$files[$p]=$c;return true;}public function delete($p){unset(self::$files[$p]);return true;}public function get($p){return self::$files[$p]??null;}}
class Cache {public static function remember($k,$t,$f){return $f();}}
}
namespace {
require_once __DIR__.'/fixtures/lang.php';
function app($c){return new $c;}function user(){return (object)['id'=>7];}function report($e){}
function abort_unless($ok,$code,$message=''){if(!$ok)throw new \RuntimeException((string)$code);}function abort_if($ok,$code,$message=''){abort_unless(!$ok,$code,$message);}
require __DIR__.'/../src/Services/JsonFile.php';require __DIR__.'/../src/Services/InstallProgress.php';
use Simplify\Panel\Services\InstallProgress as I;use App\Enums\ServerState as S;use App\Repositories\Daemon\DaemonServerRepository as D;use Illuminate\Support\Facades\Storage;
function check($ok,$message){if(!$ok)throw new \RuntimeException($message);echo "PASS $message\n";}
function expectFail(callable $f,string $code){try{$f();}catch(\RuntimeException $e){if($e->getMessage()===$code)return;throw $e;}throw new \LogicException("Expected $code");}

$s=new \App\Models\Server;$s->status=S::Installing;
check(I::log($s)===['Installing','Done'],'the installation log so far is read without colour codes');
// A reinstall of a server that already has files must never be killed: that would delete them.
$s->installed_at=new \DateTimeImmutable('-1 day');expectFail(fn()=>I::kill($s),'409');check(D::$calls===[],'a reinstall cannot be killed');
$s->installed_at=null;I::kill($s);
check(D::$calls===['delete']&&$s->status===S::InstallFailed&&I::killed($s),'Kill removes the server from Wings (stopping the installer) and keeps it as a failed installation');
// Wings reports the failed installation afterwards and sets installed_at; the server still counts as new.
$s->installed_at=new \DateTimeImmutable();check(I::fresh($s),'a killed server stays a new server');
expectFail(fn()=>I::kill($s),'409');
expectFail(fn()=>I::retry($s),'429');check(!in_array('create:stay',D::$calls,true),'Try again waits until Wings removed the killed installer');
Storage::$files['simplify/install/u1.json']=json_encode(['killed_at'=>time()-I::RETRY_AFTER]);
D::$fail=true;try{I::retry($s);}catch(\RuntimeException){}check($s->status===S::InstallFailed&&I::killed($s),'a failed retry keeps the stopped state');
D::$fail=false;I::retry($s);
check(end(D::$calls)==='create:stay'&&$s->status===S::Installing&&!I::killed($s),'Try again after Kill creates the server on Wings again (installing, not started)');
expectFail(fn()=>I::retry($s),'409');
$s->status=S::InstallFailed;I::retry($s);check(\App\Services\Servers\ReinstallServerService::$runs===1,'Try again after an ordinary failure uses Pelican\'s reinstall');
$s->status=null;check(I::info($s)===[],'a finished server has no installation data');
}
