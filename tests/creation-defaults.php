<?php
declare(strict_types=1);
namespace Illuminate\Support\Facades {
 class Storage {public static array $files=[];public static function disk($name){return new self;}public function exists($name){return isset(self::$files[$name]);}public function get($name){return self::$files[$name]??null;}public function put($name,$value){self::$files[$name]=$value;return true;}}
 class Cache {public static function lock(...$args){return new self;}public function block($seconds,$callback){$callback();}}
}
namespace {
 require_once __DIR__.'/../src/Services/JsonFile.php';
 require __DIR__.'/../src/Services/CreationDefaults.php';
 use Simplify\Panel\Services\CreationDefaults as D;
 use Illuminate\Support\Facades\Storage as S;
 $admin=true;$validationFails=false;
 function user(){return new class{function isRootAdmin(){return $GLOBALS['admin'];}};}
 function abort_unless($ok,$code){if(!$ok)throw new \RuntimeException((string)$code);}function report($e){}
 function validator($input,$rules){return new class{function validate(){if($GLOBALS['validationFails'])throw new \RuntimeException('invalid');}};}
 function check($ok){if(!$ok)throw new \RuntimeException('Creation defaults regression');}
 check(D::forType('modded')===['cpu'=>200,'ram_gb'=>4,'disk_mb'=>20480]);
 check(D::forType('vanilla')['cpu']===100&&D::forType('plus')['cpu']===150);
 $profiles=D::defaults();$profiles['modded']=['cpu'=>300,'ram_gb'=>8,'disk_mb'=>30720];$profiles['unexpected']=['secret'=>'ignored'];D::save($profiles);
 check(D::forType('modded')['cpu']===300&&D::forType('modded')['ram_gb']===8&&D::forType('modded')['disk_mb']===30720);
 check(!isset(json_decode(S::$files[D::FILE],true)['unexpected']));
 $before=S::$files;$admin=false;try{D::save($profiles);throw new \Exception('unrestricted write');}catch(\RuntimeException $e){check($e->getMessage()==='403');}check(S::$files===$before);
 $admin=true;$validationFails=true;try{D::save($profiles);throw new \Exception('failed validation wrote settings');}catch(\RuntimeException $e){check($e->getMessage()==='invalid');}check(S::$files===$before);
 S::$files[D::FILE]='broken';check(D::all()===D::defaults());
 S::$files[D::FILE]='{"modded":{"cpu":0,"ram_gb":500,"disk_mb":1}}';check(D::all()===D::defaults());
 echo "PASS original CPU/disk defaults, saved modpack profiles, dynamic summary, admin-only writes and corrupt/out-of-range fallback\n";
}
