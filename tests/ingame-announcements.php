<?php
namespace Illuminate\Support\Facades {
class Storage {public static array $records=[];public static function disk($name){return new self;}public function exists($path){return isset(self::$records[$path]);}public function get($path){return self::$records[$path]??null;}public function put($path,$value){self::$records[$path]=$value;return true;}}
class Cache {public static array $keys=[];public static function add($key,$value,$ttl){if(isset(self::$keys[$key]))return false;self::$keys[$key]=true;return true;}}
class Http {public static array $sent=[];public static array $status=[];public static array $down=[];private $node;
    public static function daemon($node){$h=new self;$h->node=$node;return $h;}public function connectTimeout($s){return $this;}public function timeout($s){return $this;}
    public function post($url,array $data){if(in_array($this->node->id,self::$down,true))throw new \RuntimeException('down');self::$sent[]=['node'=>$this->node->id,'url'=>$url,'commands'=>$data['commands']];
        $ok=(self::$status[$url]??204)<300;return new class($ok){public function __construct(private bool $ok){}public function successful(){return $this->ok;}};}}
}
namespace App\Models {
final class Server {public static array $rows=[];public static array $lastWhere=[];
    public static function query(){return new class{private ?array $uuids=null;public function with($r){return $this;}public function whereIn($c,array $v){$this->uuids=$v;Server::$lastWhere=$v;return $this;}public function orderBy($c){return $this;}public function limit($n){return $this;}
        public function get(){$rows=Server::$rows;if($this->uuids!==null)$rows=array_values(array_filter($rows,fn($s)=>in_array($s->uuid,$this->uuids,true)));return new class($rows){public function __construct(private array $rows){}public function all(){return $this->rows;}};}};}}
final class User {public static array $rows=[];public static function query(){return new class{public function find($id){return User::$rows[$id]??null;}};}}
}
namespace Simplify\Panel\Services {
final class WingsServers {public static array $states=[];public static function forNode($node): ?array {return isset(self::$states[$node->id])?['at'=>0,'servers'=>self::$states[$node->id]]:null;}}
final class OverviewGroups {public static array $groups=[];public static function get(object $user): ?array {return ['groups'=>self::$groups[$user->id]??[],'order'=>'name'];}}
}
namespace Simplify\Panel\Filament\App\Pages {final class Dashboard {public static function getUrl(array $parameters=[],?string $panel=null): string {return '/simplify';}}}
namespace {
function report($e){}
require_once __DIR__.'/../src/Services/JsonFile.php';
require __DIR__.'/../src/Services/InGameAnnouncements.php';
require __DIR__.'/../src/Services/Announcements.php';
use Simplify\Panel\Services\Announcements as A;
use Simplify\Panel\Services\InGameAnnouncements as G;
use Simplify\Panel\Services\WingsServers;
use Simplify\Panel\Services\OverviewGroups;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Server;
use App\Models\User;
function check($condition,$message){if(!$condition)throw new \Exception($message);}
function fails(callable $run,string $text){try{$run();}catch(\InvalidArgumentException $e){return str_contains($e->getMessage(),$text);}return false;}
$now=1792000000;

// The chat command per egg: Minecraft and its loaders, proxies, Source games, Rust, Terraria, Zomboid; unknown games are skipped.
foreach(['Paper'=>'say {message}','Forge Enhanced'=>'say {message}','Fabric'=>'say {message}','Vanilla Minecraft'=>'say {message}','FTB Evolution 2'=>'say {message}','Minecraft Bedrock'=>'say {message}',
    'BungeeCord'=>'alert {message}','Waterfall'=>'alert {message}','Velocity'=>null,'Counter-Strike 2'=>'say {message}',"Garry's Mod"=>'say {message}','Team Fortress 2'=>'say {message}',
    'Rust'=>'say {message}','Terraria Vanilla'=>'say {message}','tModLoader'=>'say {message}','Unturned'=>'say {message}','Project Zomboid'=>'servermsg "{message}"','Factorio'=>'{message}',
    'Palworld'=>null,'Valheim'=>null,'Trusted Rustic'=>null] as $egg=>$command)check(G::defaultCommand($egg)===$command,'default command for '.$egg);

// What players read: one line, no control characters or double quotes, at most 240 characters.
check(G::message(['title'=>'Restart at 20:00','text'=>"Back in 5 minutes.\n\nSave your \"builds\"."])==="[Announcement] Restart at 20:00 - Back in 5 minutes. Save your 'builds'.",'title and text on one line, double quotes made single');
check(G::message(['title'=>"Bad\x07title",'text'=>''])==='[Announcement] Badtitle','control characters are removed; no dash without text');
$long=G::message(['title'=>'T','text'=>str_repeat('word ',100)]);
check(mb_strlen($long)<=G::MESSAGE_LENGTH&&str_ends_with($long,'…'),'long messages are cut at 240 characters');
check(G::render('servermsg "{message}"','Hi')==='servermsg "Hi"','the message goes in place of {message}');

// Own commands: one line with {message}, or "-" to skip; empty means automatic.
check(fails(fn()=>G::saveCommands([3=>'say hello']),'{message}'),'an own command needs {message}');
check(fails(fn()=>G::saveCommands([3=>"say {message}\nstop"]),'single line'),'an own command is one line');
G::saveCommands([3=>' broadcast {message} ',4=>'-',5=>'','x'=>'say {message}']);
check(G::commands()===[3=>'broadcast {message}',4=>'-'],'own commands are saved per egg, empty ones are left out');
check(G::commandFor(3,'Valheim')==='broadcast {message}'&&G::commandFor(4,'Paper')===null&&G::commandFor(9,'Paper')==='say {message}','own command first, "-" skips, otherwise automatic');

// Settings from the form: all servers, or picked servers and groups; at least one when picking.
check(fails(fn()=>G::fromInput(['ingame'=>true,'ingame_targets'=>'pick']),'at least one'),'picking needs a server or group');
$set=G::fromInput(['ingame'=>true,'ingame_targets'=>'pick','ingame_servers'=>['aaaa-1','../x','aaaa-1'],'ingame_groups'=>['g1'],'ingame_repeat'=>'10'],null,7);
check($set===['enabled'=>true,'targets'=>'pick','servers'=>['aaaa-1'],'groups'=>['g1'],'repeat'=>10,'groups_of'=>7],'servers are uuids, groups belong to the admin who picked them');
check(G::fromInput(['ingame_repeat'=>7])['repeat']===0&&G::fromInput([])['enabled']===false,'unknown repeats become "only once"; off by default');
check(G::fromInput(['ingame'=>$set])===$set,'a saved row passes through (End now keeps it)');

// Saved with the announcement and kept when another admin edits it.
$restart=A::save(['title'=>'Restart at 20:00','text'=>'Back in 5 minutes.','starts_at'=>$now+600,'ends_at'=>$now+3600,'ingame'=>true,'ingame_targets'=>'pick','ingame_servers'=>['aaaa-1'],'ingame_groups'=>['g1'],'ingame_repeat'=>10,'ingame_groups_of'=>1],null,'jera',$now,1);
check(A::find($restart['id'])['ingame']['groups_of']===1,'stored with the announcement');
$restart=A::save(['title'=>'Restart at 20:00','text'=>'Back in 5 minutes.','starts_at'=>$now+600,'ends_at'=>$now+3600,'ingame'=>true,'ingame_targets'=>'pick','ingame_servers'=>['aaaa-1'],'ingame_groups'=>['g1'],'ingame_repeat'=>10],$restart['id'],'sam',$now+1,2);
check($restart['ingame']['groups_of']===1,'the groups stay those of the admin who picked them');
$plain=A::save(['title'=>'No game'],null,'jera',$now);
check(A::find($plain['id'])['ingame']['enabled']===false,'off unless asked');

// When it goes out: at "Show from", then every 10 minutes while it shows; never after it ends.
check(G::due(A::all(),$now)===[]&&array_column(G::due(A::all(),$now+600),'id')===[$restart['id']],'sent when it starts');
check(G::next(A::find($restart['id']),$now)===$now+600,'next time is the start');

// Sending: running servers only, the egg's command, picked servers plus the group's servers, failures counted.
$node1=(object)['id'=>1];$node2=(object)['id'=>2];
$server=fn($uuid,$egg,$eggName,$node)=>(object)['uuid'=>$uuid,'egg_id'=>$egg,'egg'=>(object)['name'=>$eggName],'node'=>$node,'node_id'=>$node?->id];
Server::$rows=[$server('aaaa-1',9,'Paper',$node1),$server('bbbb-2',10,'Counter-Strike 2',$node1),$server('cccc-3',11,'Palworld',$node1),$server('dddd-4',9,'Paper',$node2),$server('eeee-5',9,'Paper',$node1),$server('ffff-6',9,'Paper',$node1)];
User::$rows[1]=(object)['id'=>1];
OverviewGroups::$groups[1]=[['id'=>'g1','servers'=>['bbbb-2','cccc-3','dddd-4','eeee-5']],['id'=>'g2','servers'=>['ffff-6']]];
WingsServers::$states=[1=>['aaaa-1'=>['state'=>'running'],'bbbb-2'=>['state'=>'running'],'cccc-3'=>['state'=>'running'],'eeee-5'=>['state'=>'offline']],2=>['dddd-4'=>['state'=>'running']]];
Http::$status['/api/servers/dddd-4/commands']=502;
$results=G::run($now+600);
check($results['Restart at 20:00']===['sent'=>2,'offline'=>1,'unsupported'=>1,'failed'=>1],'2 sent, 1 not running, 1 without chat command, 1 failed');
check(Http::$sent[0]===['node'=>1,'url'=>'/api/servers/aaaa-1/commands','commands'=>['say [Announcement] Restart at 20:00 - Back in 5 minutes.']]&&Http::$sent[1]['url']==='/api/servers/bbbb-2/commands','the egg\'s command through Wings');
check(!in_array('ffff-6',Server::$lastWhere,true),'only the picked groups');
check(G::run($now+630)===[]&&G::due(A::all(),$now+1150)===[]&&count(G::due(A::all(),$now+1200))===1,'not again in the same minute; again after 10 minutes');
check(G::next(A::find($restart['id']),$now+700)===$now+1200,'the next time is shown');
check(G::sent()[$restart['id']]['at']===$now+600&&G::summary(G::sent()[$restart['id']])===['sent to 2 servers','1 not running','1 without chat command','1 failed'],'the result is kept for the list');
check(G::due(A::all(),$now+3600)===[]&&G::next(A::find($restart['id']),$now+3600)===null,'nothing after it ends');

// A node that does not answer counts as failed for its servers, the other node still gets it.
Http::$sent=[];Http::$down=[2];Http::$status=[];
$all=A::save(['title'=>'Event tonight','ingame'=>true,'ingame_targets'=>'all'],null,'jera',$now+700,1);
$results=G::run($now+700);
check($results['Event tonight']===['sent'=>2,'offline'=>2,'unsupported'=>1,'failed'=>1]&&count(Http::$sent)===2,'all servers: a down node fails, the rest gets it');
check(G::due(A::all(),$now+5000)===[],'"only once" is sent once');
G::forget($all['id']);
check(count(G::due(A::all(),$now+5000))===1,'forgetting sends it again (start moved or switched on later)');
Storage::$records[G::SENT_FILE]='{broken';
check(G::sent()===[],'a broken file reads as nothing sent');
echo "PASS ingame-announcements: chat command per egg, own commands, one-line message, picked servers and groups, running servers only, repeats while it shows, failures counted\n";
}
