<?php
declare(strict_types=1);
namespace Illuminate\Support\Facades {
    class Storage {public static array $records=[];public static function disk($d){return new self;}public function get($p){return self::$records[$p]??null;}public function exists($p){return isset(self::$records[$p]);}public function put($p,$v){self::$records[$p]=$v;return true;}}
    class Cache {public static function lock(...$args){return new self;}public function block($n,$fn){return $fn();}}
}
namespace App\Models {
    class BackupHost {public static function doesntHave($r){return new class{public function exists(){return false;}};}}
    class Server {
        public int $id=1;public string $uuid='test';public $installed_at='yes';public $status=null;public int $backup_limit=2;public $node;
        public function __construct(){$this->node=new class{public bool $available=true;public function backupHosts(){return new class($this->available){public function __construct(private bool $ok){}public function exists(){return $this->ok;}};}};}
        public function backups(){return new \Query;}
    }
}
namespace App\Services\Backups {
    class DeleteBackupService {public static array $deleted=[];public function handle($b){self::$deleted[]=$b->id;unset($GLOBALS['items'][$b->id]);}}
    class InitiateBackupService {public static int $started=0;public static int $temporaryLimit=0;public function setIsScheduled($v){return $this;}public function setIsLocked($v){return $this;}public function handle($s,$name){self::$started++;self::$temporaryLimit=$s->backup_limit;$b=(object)['id'=>3,'is_successful'=>false,'is_locked'=>false,'completed_at'=>null,'created_at'=>date('Y-m-d H:i:s')];$GLOBALS['items'][3]=$b;return $b;}}
}
namespace {
require_once __DIR__.'/fixtures/lang.php';
    class Query {
        private array $filters=[];private bool $desc=false;
        public function where($k,$v){$this->filters[]=fn($r)=>$r->$k===$v;return $this;}
        public function whereNull($k){return $this->where($k,null);}
        public function whereNotNull($k){$this->filters[]=fn($r)=>$r->$k!==null;return $this;}
        public function orderByDesc($k){$this->desc=true;return $this;}
        private function rows(){$r=$GLOBALS['items'];foreach($this->filters as $f)$r=array_filter($r,$f);if($this->desc)usort($r,fn($a,$b)=>strcmp($b->created_at,$a->created_at));return array_values($r);}
        public function nonFailed(){$this->filters[]=fn($r)=>$r->completed_at===null || $r->is_successful;return $this;}
        public function get(){return new class($this->rows()){public function __construct(private array $r){}public function slice($n){return array_slice($this->r,$n);}};}
        public function exists(){return count($this->rows())>0;}public function count(){return count($this->rows());}public function max($k){return $this->rows()?max(array_column($this->rows(),$k)):null;}
    }
    function app($c){return new $c;}
    function now(){return new class{public function format($f){return date($f);}};}
    function check($c,$m){if(!$c)throw new \RuntimeException($m);echo "PASS $m\n";}
    require_once __DIR__.'/../src/Services/JsonFile.php';
    require __DIR__.'/../src/Services/PerformanceSettings.php';
    require __DIR__.'/../src/Services/AutomaticBackups.php';
    use Simplify\Panel\Services\AutomaticBackups as A;
    use App\Services\Backups\InitiateBackupService as Start;
    use App\Services\Backups\DeleteBackupService as Delete;
    $s=new \App\Models\Server;
    check(A::settings($s)['interval_minutes']===360 && A::settings($s)['retention']===2 && A::settings($s)['only_when_used']===true,'default is every six hours with two recovery points, skipped while unused');
    $items=[];foreach([1=>4000,2=>2500]as$id=>$age)$items[$id]=(object)['id'=>$id,'is_successful'=>true,'is_locked'=>false,'completed_at'=>'yes','created_at'=>date('Y-m-d H:i:s',time()-$age)];
    \Illuminate\Support\Facades\Storage::$records['simplify/backups/test.json']=json_encode(['interval_minutes'=>30]);
    A::tick($s);check(Start::$started===1,'due automatic backup starts');check(count(Delete::$deleted)===0,'existing recovery points kept while new backup runs');check(Start::$temporaryLimit===3 && $s->backup_limit===2,'temporary in-flight slot does not change native allocation');
    A::tick($s);check(Start::$started===1,'running backup is not duplicated');
    $items[3]->completed_at='yes';A::tick($s);check(isset($items[1],$items[2]),'failed backup preserves both successful recovery points');
    $f='simplify/backups/test.json';$r=json_decode(\Illuminate\Support\Facades\Storage::$records[$f],true);$r['last_attempt']=time()-1900;\Illuminate\Support\Facades\Storage::$records[$f]=json_encode($r);A::tick($s);check(Start::$started===2,'the next due backup starts after a failed one');$items[3]->completed_at='yes';$items[3]->is_successful=true;A::tick($s);check(!isset($items[1]) && isset($items[2],$items[3]),'oldest successful backup removed only after replacement succeeds');
    $items[2]->is_locked=true;$items[2]->created_at=date('Y-m-d H:i:s',time()-5000);$items[4]=(object)['id'=>4,'is_successful'=>true,'is_locked'=>false,'completed_at'=>'yes','created_at'=>date('Y-m-d H:i:s')];A::tick($s);check(isset($items[2]),'locked legacy backups are never deleted');
    $s->status='installing';$before=Start::$started;A::tick($s);check(Start::$started===$before,'installation skips automatic backup');$s->status=null;
    $s->node->available=false;\Illuminate\Support\Facades\Storage::$records=['simplify/backups/test.json'=>json_encode(['interval_minutes'=>30])];A::tick($s);check(Start::$started===$before,'missing backup storage does not trigger a failed backup attempt');
    $s->node->available=true;$items=[1=>(object)['id'=>1,'is_successful'=>true,'is_locked'=>false,'completed_at'=>'yes','created_at'=>date('Y-m-d H:i:s',time()-4000)]];
    \Illuminate\Support\Facades\Storage::$records=['simplify/backups/test.json'=>json_encode(['interval_minutes'=>30,'last_probe'=>time(),'last_active'=>time()-5000])];A::tick($s);check(Start::$started===$before,'a server that has not run since its last backup is skipped');
    $saved=json_decode(\Illuminate\Support\Facades\Storage::$records['simplify/backups/test.json'],true);check($saved['last_attempt']>time()-1800,'a skipped check waits before querying again');
}
