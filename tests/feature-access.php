<?php
declare(strict_types=1);
namespace Illuminate\Support\Facades {
 class Storage {
  public static array $files=[];public static int $reads=0;
  public static function disk($disk){return new self;}
  public function exists($key){return isset(self::$files[$key]);}
  public function get($key){self::$reads++;return self::$files[$key]??null;}
  public function put($key,$value){self::$files[$key]=$value;return true;}
 }
 class Cache {public static function lock(...$args){return new self;}public function block($seconds,$callback){return $callback();}}
}
namespace Illuminate\Database\Eloquent {
 class Builder {
  public array $rows;private array $conditions=[];
  public function __construct($rows=null){$this->rows=$rows??\App\Models\Server::$rows;}
  public function withExists($args){return $this;}public function orderBy($key){return $this;}
  public function where($key,$value=null){if(is_callable($key)){$sub=new self($this->rows);$key($sub);$this->conditions[]=fn($s)=>$sub->accepts($s);}else{$this->conditions[]=fn($s)=>$s->$key===$value;}return $this;}
  public function orWhereHas($name,$callback){$previous=array_pop($this->conditions);$this->conditions[]=fn($s)=>$previous($s)||$s->assigned;return $this;}
  public function accepts($s){foreach($this->conditions as $condition)if(!$condition($s))return false;return true;}
  public function get(){return array_values(array_filter($this->rows,fn($s)=>$this->accepts($s)));}
  public function firstOrFail(){return $this->get()[0]??throw new \RuntimeException('404');}
 }
}
namespace App\Models {
 class Server {
  public static array $rows=[];
  public function __construct(public string $uuid,public int $owner_id,public bool $assigned=false){}
  public static function with($relations){return new \Illuminate\Database\Eloquent\Builder;}
  public function subusers(){return new class($this->assigned){public function __construct(private bool $assigned){}public function where(...$args){return $this;}public function exists(){return $this->assigned;}};}
 }
}
namespace {
 require_once __DIR__.'/../src/Services/JsonFile.php';
 require __DIR__.'/../src/Services/FeatureSettings.php';
 require __DIR__.'/../src/Services/Access.php';
 require __DIR__.'/../src/Services/RoleAccess.php';
 require __DIR__.'/../src/Services/CreatorAccess.php';
 use Simplify\Panel\Services\FeatureSettings as F;
 use Simplify\Panel\Services\Access as A;
 use Illuminate\Support\Facades\Storage;
 $GLOBALS['instances']=[];$GLOBALS['admin']=false;
 function app($class){return $GLOBALS['instances'][$class]??=$class==='x'?new \stdClass:new $class;}
 function user(){return new class{public int $id=10;public function isRootAdmin(){return $GLOBALS['admin'];}public function isAdmin(){return $GLOBALS['admin'];}};}
 function abort_unless($condition,$code){if(!$condition)throw new \RuntimeException((string)$code);}
 function report($exception){}
 function check($condition,$message){if(!$condition)throw new \RuntimeException($message);echo "PASS $message\n";}
 function configure($values){Storage::$files[F::FILE]=json_encode($values);$GLOBALS['instances']=[];}
 $owner=new \App\Models\Server('owner',10);$assigned=new \App\Models\Server('assigned',11,true);$other=new \App\Models\Server('other',12);
 \App\Models\Server::$rows=[$owner,$assigned,$other];
 configure(['friends_mode'=>true]);check(count(A::overviewQuery()->get())===3,'friends mode shows every summary');
 check(A::linked($owner)&&A::linked($assigned)&&!A::linked($other),'summary visibility grants no server access');
 try{A::server('other');throw new \RuntimeException('unauthorized UUID accepted');}catch(\RuntimeException $e){check($e->getMessage()==='403','direct unassigned server rejected');}
 configure(['friends_mode'=>false]);check(count(A::overviewQuery()->get())===2,'friends off restricts summaries to owner and assigned');
 check(!A::feature('advanced')&&!A::feature('software'),'ordinary users have no advanced or software workspace by default');
 $GLOBALS['admin']=true;check(count(A::overviewQuery()->get())===3&&A::linked($other)&&A::feature('advanced'),'administrator sees all servers and advanced tools');
 configure(['friends_mode'=>false,'user_advanced'=>true]);$GLOBALS['admin']=false;check(A::feature('advanced'),'earlier user toggles migrate into the Everyone profile');
 Storage::$reads=0;F::all();F::all();check(Storage::$reads<=1,'feature file loaded at most once per request');
 $GLOBALS['admin']=true;F::save(['friends_mode'=>true,'unknown'=>true],true);$saved=json_decode(Storage::$files[F::FILE],true);check($saved['friends_mode']&&$saved['setup_completed']&&!isset($saved['unknown']),'save completes setup and discards unknown settings');
 $GLOBALS['admin']=false;try{F::save(['friends_mode'=>false]);throw new \RuntimeException('user changed global settings');}catch(\RuntimeException $e){check($e->getMessage()==='403','ordinary user cannot save global settings');}
 configure(['friends_mode'=>'true']);check(!F::enabled('friends_mode'),'stored nonboolean switch does not enable a feature');
 Storage::$files[F::FILE]='broken';$GLOBALS['instances']=[];check(F::enabled('friends_mode'),'damaged settings use stable defaults');
}
