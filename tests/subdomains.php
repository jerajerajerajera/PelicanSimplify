<?php
declare(strict_types=1);
namespace Illuminate\Validation {class ValidationException extends \RuntimeException {public array $messages=[];public static function withMessages($messages){$e=new self(json_encode($messages));$e->messages=$messages;return $e;}}}
namespace Illuminate\Support\Facades {
 class Storage {public static array $files=[];public static function disk($d){return new self;}public function exists($k){return isset(self::$files[$k]);}public function get($k){return self::$files[$k]??null;}public function put($k,$v){self::$files[$k]=$v;return true;}public function delete($k){unset(self::$files[$k]);return true;}
  public function files($dir){return array_values(array_filter(array_keys(self::$files),fn($k)=>dirname($k)===$dir));}}
 class Cache {public static function lock(...$a){return new self;}public function block($s,$c){return $c();}}
}
namespace App\Models {
 class Server {public static array $all=[];public $uuid;public $allocation;public $egg;public function __construct(string $uuid,int $port,string $egg='Paper'){$this->uuid=$uuid;$this->allocation=(object)['port'=>$port];$this->egg=(object)['name'=>$egg];}
  public static function with($r){return new ServerQuery;}}
 class ServerQuery {private array $uuids=[];public function where($c,$v){$this->uuids=[$v];return $this;}public function whereIn($c,$v){$this->uuids=$v;return $this;}
  public function first(){return Server::$all[$this->uuids[0]]??null;}public function get(){return new ServerList(array_intersect_key(Server::$all,array_flip($this->uuids)));}}
 class ServerList {public function __construct(private array $items){}public function keyBy($k){return $this->items;}}
}
namespace Simplify\Panel\Services {
 class FeatureSettings {public static bool $on=true;public static function enabled($k){return $k==='subdomains'&&self::$on;}}
 class ManagedSettings {public static function minecraft($s){return str_contains(strtolower((string)$s->egg->name),'paper');}}
}
namespace {
require __DIR__.'/../src/Services/CloudflareDns.php';
require __DIR__.'/../src/Services/Subdomains.php';
use Simplify\Panel\Services\{Subdomains as D,CloudflareDns,FeatureSettings};
use Illuminate\Support\Facades\Storage;
use App\Models\Server;
function app($c=null){return new class{public function runningInConsole(){return true;}};}
function report($e){}
function env($k){return ['CLOUDFLARE_API_TOKEN'=>'test-token','CLOUDFLARE_ZONE_ID'=>'zone'][$k]??null;}
function base_path($p=''){return '/nonexistent/'.$p;}
function validator($data,$rules,$m=[],$a=[]){return new class($data){public function __construct(private $d){}public function validate(){return $this->d;}};}
function check($ok,$message){if(!$ok)throw new \RuntimeException('FAIL '.$message);echo "PASS $message\n";}
// Fake Cloudflare: records in memory, with the comment and name filters the client uses.
final class FakeCloudflare {
    public array $records=[];public array $calls=[];public bool $fail=false;private int $id=0;
    public function __invoke(string $m,string $p,?array $b): array {
        $this->calls[]=$m;
        if($this->fail)return ['success'=>false,'errors'=>[['message'=>'unavailable']]];
        if($m==='GET'){parse_str((string)parse_url($p,PHP_URL_QUERY),$q);return ['success'=>true,'result'=>array_values(array_filter($this->records,fn($r)=>(!isset($q['comment_exact'])||($r['comment']??'')===$q['comment_exact'])&&(!isset($q['name_exact'])||$r['name']===$q['name_exact'])))];}
        $id=basename($p);
        if($m==='POST'){$b['id']='r'.(++$this->id);$this->records[$b['id']]=$b;}
        if($m==='PUT')$this->records[$id]=$b+['id'=>$id];
        if($m==='DELETE')unset($this->records[$id]);
        return ['success'=>true];
    }
    public function names(): array { $n=array_map(fn($r)=>$r['type'].' '.$r['name'],$this->records);sort($n);return $n; }
    public function srv(): ?array { foreach($this->records as $r)if($r['type']==='SRV')return $r;return null; }
}
$cf=new FakeCloudflare;D::$transport=$cf;
$a='aaaaaaaa-0000-0000-0000-000000000001';$b='bbbbbbbb-0000-0000-0000-000000000002';$c='cccccccc-0000-0000-0000-000000000003';
Server::$all=[$a=>new Server($a,25566),$b=>new Server($b,25570,'Valheim'),$c=>new Server($c,30000)];

check(!D::active()&&in_array('domain',D::missing(),true),'not active until a domain and game address are set');
D::save(['domain'=>'example.com','target'=>'play.example.com','port_min'=>25565,'port_max'=>25600]);
check(D::active()&&D::missing()===[],'active with settings and the token and zone ID from the environment');
FeatureSettings::$on=false;check(!D::active(),'off while the add-on is switched off');FeatureSettings::$on=true;

check(D::check('oak')===null,'a valid name passes');
check(D::check('Oak')!==null&&D::check('-oak')!==null&&D::check(str_repeat('a',64))!==null,'invalid names are refused');
check(D::check('www')!==null&&D::check('play')!==null,'reserved names and the game address itself are refused');

D::request(Server::$all[$a],'oak');
check(D::state(Server::$all[$a])['status']==='active','a requested subdomain is connected');
check($cf->names()===['CNAME oak.example.com','SRV _minecraft._tcp.oak.example.com'],'Minecraft gets a CNAME and an SRV record');
check($cf->srv()['data']['port']===25566&&$cf->srv()['data']['target']==='play.example.com','the SRV record points to the game address on the server port');
check(array_values(array_filter($cf->records,fn($r)=>$r['type']==='CNAME'))[0]['proxied']===false,'records are DNS only');
check(D::address(Server::$all[$a])==='oak.example.com','Minecraft players join without a port');
check(D::check('oak',$b)!==null&&D::check('oak',$a)===null,'a name used by another server is refused, its own name is not');
$caught=null;try{D::request(Server::$all[$b],'oak');}catch(\Illuminate\Validation\ValidationException $e){$caught=$e;}
check($caught!==null&&str_contains($caught->messages['name'],'Another server'),'requesting a taken name fails with a message');

D::request(Server::$all[$b],'viking');
check(in_array('CNAME viking.example.com',$cf->names(),true)&&!in_array('SRV _minecraft._tcp.viking.example.com',$cf->names(),true),'other games get only a CNAME');
check(D::address(Server::$all[$b])==='viking.example.com:25570','other games join with the port');

$cf->calls=[];D::tick();check(!in_array('POST',$cf->calls,true)&&!in_array('PUT',$cf->calls,true)&&!in_array('GET',$cf->calls,true),'the retry command leaves connected servers alone');
Server::$all[$a]->allocation->port=25580;D::tick();check($cf->srv()['data']['port']===25580&&count($cf->records)===3,'a port change updates the SRV record in place');

D::request(Server::$all[$a],'elm');
check(!in_array('CNAME oak.example.com',$cf->names(),true)&&in_array('SRV _minecraft._tcp.elm.example.com',$cf->names(),true),'renaming removes the old records');

D::request(Server::$all[$c],'birch');
check(D::state(Server::$all[$c])['status']==='error'&&str_contains(D::state(Server::$all[$c])['message'],'not forwarded'),'a port outside the forwarded range is explained, not connected');
check(!in_array('CNAME birch.example.com',$cf->names(),true)&&D::address(Server::$all[$c])===null,'no records or address for an unforwarded port');

$cf->records['m1']=['id'=>'m1','type'=>'CNAME','name'=>'pine.example.com','content'=>'play.example.com'];
D::request(Server::$all[$c],'');Server::$all[$c]->allocation->port=25590;D::request(Server::$all[$c],'pine');
check(D::state(Server::$all[$c])['status']==='error'&&str_contains(D::state(Server::$all[$c])['message'],'not created by this panel'),'a record made by hand is never taken over');
check(isset($cf->records['m1'])&&!isset($cf->records['m1']['comment'])&&$cf->records['m1']['content']==='play.example.com','the hand-made record is untouched');

D::request(Server::$all[$a],'');
check(!in_array('CNAME elm.example.com',$cf->names(),true)&&!isset(Storage::$files[D::STATES.'/'.$a.'.json'])&&D::address(Server::$all[$a])===null,'an empty name removes the subdomain');

$cf->fail=true;D::forget($b);
check(isset(Storage::$files[D::STATES.'/'.$b.'.json'])&&json_decode(Storage::$files[D::STATES.'/'.$b.'.json'],true)['status']==='removing','a deleted server is kept for retry while Cloudflare fails');
$cf->fail=false;unset(Server::$all[$b]);D::tick();
check(!in_array('CNAME viking.example.com',$cf->names(),true)&&!isset(Storage::$files[D::STATES.'/'.$b.'.json']),'the retry command removes records of deleted servers');

$cf->fail=true;Server::$all[$a]=new Server($a,25566);D::request(Server::$all[$a],'ash');
check(D::state(Server::$all[$a])['status']==='error'&&str_contains(D::state(Server::$all[$a])['message'],'unavailable'),'a Cloudflare failure is shown and kept for retry');
$cf->fail=false;D::tick();check(D::state(Server::$all[$a])['status']==='active','the retry command connects it once Cloudflare answers');

check(D::suggest('Oak Survival')==='oak-survival'&&D::suggest('  --Big__World!! ')==='big-world','a server name becomes a DNS-safe suggestion');
check(D::suggest('Ash')==='ash-2'&&D::suggest('Ash',$a)==='ash','a taken suggestion gets a number, its own name does not');
check(D::suggest('www')==='www-2'&&D::suggest('!!!')==='','reserved names are skipped and names without letters suggest nothing');
check(strlen(D::suggest(str_repeat('a',80)))<=63,'suggestions fit a DNS label');
check(CloudflareDns::diff([],[])===[],'nothing to do for a server without a subdomain');
echo "All subdomain checks passed.\n";
}
