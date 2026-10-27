<?php
declare(strict_types=1);
namespace Filament\Pages {class Page {}}
namespace Livewire {trait WithFileUploads {}}
namespace App\Enums {enum SubuserPermission:string {case ControlStart='control.start';case ControlStop='control.stop';case ControlRestart='control.restart';}}
namespace App\Models {class Server {public bool $conflict=false;public function __construct(public string $uuid){}public function isInConflictState(){return $this->conflict;}}}
namespace App\Repositories\Daemon {class DaemonServerRepository {public static array $actions=[];private $server;public function setServer($s){$this->server=$s;return $this;}public function power($action){self::$actions[]=[$this->server->uuid,$action];return $this;}public function throw(){}}}
namespace Simplify\Panel\Jobs {class ApplyServerProperties {public static array $ran=[];public static function runNow($s){self::$ran[]=$s->uuid;return false;}}}
namespace Simplify\Panel\Services {
class Access {public static array $servers=[];public static bool $granted=true;public static function server($id){if(!isset(self::$servers[$id]))throw new \RuntimeException('403');return self::$servers[$id];}public static function permission($server,$permission){if(!self::$granted)throw new \RuntimeException('403');}}
class PendingChanges {public static ?array $pending=null;public static array $requests=[];public static function read($s){return self::$pending;}public static function requestRestart($s,$minutes){self::$requests[]=[$s->uuid,'restart'];}public static function requestStop($s){self::$requests[]=[$s->uuid,'stop'];}}
}
namespace {
require __DIR__.'/../src/Filament/App/Pages/Dashboard.php';use Simplify\Panel\Services\Access as A;use Simplify\Panel\Services\PendingChanges as P;use App\Repositories\Daemon\DaemonServerRepository as D;
function app($class){return new $class;}function report($e){throw $e;}function abort_unless($ok,$code){if(!$ok)throw new \RuntimeException((string)$code);}function abort_if($ok,$code,$message=''){abort_unless(!$ok,$code);}function check($ok,$message){if(!$ok)throw new \RuntimeException($message);echo "PASS $message\n";}
$server=new \App\Models\Server('owned');A::$servers=['owned'=>$server];$page=new \Simplify\Panel\Filament\App\Pages\Dashboard;$page->overviewPower('owned','start');check(D::$actions===[['owned','start']],'overview targets the authorized server rather than a selected dashboard server');
check(\Simplify\Panel\Jobs\ApplyServerProperties::$ran===['owned'],'a start first applies the creator settings still waiting in the queue (such as the accepted EULA)');
foreach([['foreign','start'],['owned','destroy']] as [$id,$action]){try{$page->overviewPower($id,$action);throw new \LogicException('Unsafe action accepted');}catch(\RuntimeException){}}
A::$granted=false;try{$page->overviewPower('owned','kill');throw new \LogicException('Permission bypassed');}catch(\RuntimeException){}A::$granted=true;
$server->conflict=true;try{$page->overviewPower('owned','start');throw new \LogicException('Install conflict bypassed');}catch(\RuntimeException){}$server->conflict=false;
check(count(D::$actions)===1,'foreign servers, invalid actions, denied permissions and install conflicts do not reach Wings');
P::$pending=['pending'=>true];$page->overviewPower('owned','restart');$page->overviewPower('owned','stop');check(P::$requests===[['owned','restart'],['owned','stop']]&&count(D::$actions)===1,'overview power keeps staged-change restart and stop semantics');
}
