<?php
declare(strict_types=1);
// Role permissions: migration from earlier settings, combining Everyone with roles, admins always allowed.
namespace Illuminate\Support\Facades {
 class Storage {public static array $files=[];public static function disk($d){return new self;}public function exists($k){return isset(self::$files[$k]);}public function get($k){return self::$files[$k]??null;}public function put($k,$v){self::$files[$k]=$v;return true;}}
 class Cache {public static function lock(...$a){return new self;}public function block($s,$c){return $c();}}
}
namespace Simplify\Panel\Services {
 class FeatureSettings {public static array $values=[];public static function all(){return self::$values;}}
 class CreatorAccess {public static array $config=[];public static function configuration(){return self::$config;}}
}
namespace {
require_once __DIR__.'/../src/Services/JsonFile.php';
require __DIR__.'/../src/Services/RoleAccess.php';
use Simplify\Panel\Services\RoleAccess as R;
use Simplify\Panel\Services\FeatureSettings as F;
use Simplify\Panel\Services\CreatorAccess as C;
use Illuminate\Support\Facades\Storage;
$GLOBALS['instances']=[];$GLOBALS['user']=null;
function app($c){return $GLOBALS['instances'][$c]??=new $c;}
function report($e){}
function user(){return $GLOBALS['user'];}
function abort_unless($c,$code){if(!$c)throw new \RuntimeException((string)$code);}
function check($ok,$m){if(!$ok)throw new \RuntimeException($m);echo "PASS $m\n";}
function person(bool $admin,array $roles){return new class($admin,$roles){public array $roles;public int $id=5;public function __construct(private bool $admin,array $roles){$this->roles=array_map(fn($id)=>(object)['id'=>$id],$roles);}public function isAdmin(){return $this->admin;}public function isRootAdmin(){return $this->admin;}};}

F::$values=['user_files'=>true,'user_players'=>false,'user_legacy'=>true,'user_settings'=>true];
C::$config=['role_ids'=>[7],'allow_egg_import'=>true,'max_servers'=>4,'max_memory_mb'=>8192,'max_disk_mb'=>40960,'max_cpu'=>200,'max_backups'=>3,'role_limits'=>[['role_id'=>8,'max_servers'=>9]]];
$all=R::all();
check(in_array('files',$all['everyone']['abilities'],true)&&in_array('legacy',$all['everyone']['abilities'],true)&&!in_array('players',$all['everyone']['abilities'],true),'earlier user toggles become the Everyone profile');
$roles=array_column($all['roles'],null,'role_id');
check(in_array('create',$roles[7]['abilities'],true)&&in_array('install_eggs',$roles[7]['abilities'],true)&&$roles[7]['max_servers']===4,'earlier creator roles keep creating, with their egg permission and limits');
check($roles[8]['abilities']===[]&&$roles[8]['max_servers']===9,'earlier role limits migrate without granting creation');

Storage::$files[R::FILE]=json_encode(['everyone'=>['abilities'=>['files','bogus'],'max_servers'=>500],'roles'=>[['role_id'=>3,'abilities'=>['backups','create'],'max_servers'=>2],['role_id'=>4,'abilities'=>['players'],'max_servers'=>6]]]);$GLOBALS['instances']=[];
$all=R::all();check($all['everyone']['abilities']===['files']&&$all['everyone']['max_servers']===100,'unknown abilities dropped and limits clamped');
$GLOBALS['user']=person(false,[]);check(R::can('files')&&!R::can('create'),'users without a role get Everyone');
$GLOBALS['user']=person(false,[3,4]);$p=R::forUser(user());
check(R::can('files')&&R::can('backups')&&R::can('players')&&R::can('create'),'abilities of Everyone and all roles combine');
check($p['max_servers']===6,'with several roles the highest limit wins');
$GLOBALS['user']=person(true,[]);check(R::can('advanced')&&R::can('create'),'administrators can always do everything');
$GLOBALS['user']=person(false,[]);try{R::save([]);check(false,'user saved roles');}catch(\RuntimeException $e){check($e->getMessage()==='403','only administrators save role permissions');}
}
