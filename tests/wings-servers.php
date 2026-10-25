<?php
// One Wings request per node feeds Pelican's per-server status and resource caches (WingsServers).
namespace App\Enums {enum ContainerStatus: string {case Running='running';case Offline='offline';case Missing='missing';}}
namespace App\Models {class Node {public static int $requests=0;public static bool $maintenance=false;public function __construct(public int $id=1){}public function isUnderMaintenance(){return self::$maintenance;}}class Server {public function __construct(public string $uuid,public ?Node $node,public int $node_id=1){}}}
namespace Illuminate\Support\Facades {
 class Cache {public static array $store=[];public static array $ttl=[];public static function get($k,$d=null){return self::$store[$k]??$d;}public static function put($k,$v,$t=null){self::$store[$k]=$v;self::$ttl[$k]=$t;return true;}public static function forget($k){unset(self::$store[$k]);}}
 class Http {public static $list=null;public static bool $ok=true;public static function daemon($node){\App\Models\Node::$requests++;return new self;}public function connectTimeout($s){return $this;}public function timeout($s){return $this;}public function get($u){return $this;}public function successful(){return self::$ok;}public function json(){return self::$list;}}
}
namespace {
 use App\Models\Node;use App\Models\Server;use Illuminate\Support\Facades\Cache;use Illuminate\Support\Facades\Http;use Simplify\Panel\Services\WingsServers;use App\Enums\ContainerStatus;
 require __DIR__.'/../src/Services/Remember.php';require __DIR__.'/../src/Services/WingsServers.php';
 $check=function($ok,$what){if(!$ok)throw new RuntimeException('WingsServers: '.$what);};
 $node=new Node(1);$a=new Server('aaaa',$node);$b=new Server('bbbb',$node);$gone=new Server('gone',$node);$other=new Server('cccc',new Node(2),2);
 Http::$list=[['state'=>'running','utilization'=>['cpu_absolute'=>12.5,'memory_bytes'=>1024,'network'=>['rx_bytes'=>10,'tx_bytes'=>20]],'configuration'=>['uuid'=>'aaaa']],['state'=>'offline','utilization'=>['cpu_absolute'=>0],'configuration'=>['uuid'=>'bbbb']]];
 WingsServers::warm([$a,$b,$gone]);
 $check(Node::$requests===1,'one request for three servers on one node');
 $check(Cache::get('servers.aaaa.status')===ContainerStatus::Running&&Cache::get('servers.bbbb.status')===ContainerStatus::Offline,'status cached as Pelican\'s enum');
 $check(Cache::get('servers.aaaa.resources')['cpu_absolute']===12.5&&Cache::get('servers.bbbb.resources')===['cpu_absolute'=>0],'utilization cached as Pelican\'s resources');
 $check(!isset(Cache::$store['servers.gone.status']),'a server Wings does not list is left to Pelican');
 $check(Cache::$ttl['servers.aaaa.status']>=14&&Cache::$ttl['servers.aaaa.status']<=15,'cache lives as long as Pelican\'s own (15 s)');
 WingsServers::warm([$a,$b]);$check(Node::$requests===1,'the node list is reused within its lifetime');
 $check(WingsServers::forNode($node)['servers']['aaaa']['state']==='running','host health reads the same list');
 // A second node means a second request; an unreachable node is one failed request, remembered as a miss.
 Http::$ok=false;WingsServers::warm([$other]);WingsServers::warm([$other]);$check(Node::$requests===2,'an unreachable node is asked once, not once per server');
 $check(!isset(Cache::$store['servers.cccc.status']),'nothing is cached for an unreachable node');
 Http::$ok=true;Node::$maintenance=true;$check(WingsServers::forNode(new Node(3))===null&&Node::$requests===2,'a node under maintenance is not asked');
 Node::$maintenance=false;Http::$list=['not'=>'a list'];$check(WingsServers::forNode(new Node(4))===null,'an unexpected answer reads as no list');
 Http::$list=[['state'=>'weird','configuration'=>['uuid'=>'dddd']]];$d=new Server('dddd',new Node(5),5);WingsServers::warm([$d]);$check(Cache::get('servers.dddd.status')===ContainerStatus::Missing&&Cache::get('servers.dddd.resources')===[],'unknown state is Missing, absent utilization is empty');
 echo "PASS one Wings request per node fills status and resources, reuse, unreachable/maintenance nodes and odd answers\n";
}
