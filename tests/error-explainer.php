<?php
declare(strict_types=1);
namespace Illuminate\Support\Facades {
class Storage {public static array $files=[];public static function disk($d){return new class{public function get($p){return Storage::$files[$p]??null;}public function put($p,$c){Storage::$files[$p]=$c;return true;}};}}
class Cache {public static array $store=[];public static function get($k){return self::$store[$k]??null;}public static function put($k,$v,$t=null){self::$store[$k]=$v;return true;}}
class RateLimiter {public static array $hits=[];public static function tooManyAttempts($k,$max){return (self::$hits[$k]??0)>=$max;}public static function hit($k,$decay){self::$hits[$k]=(self::$hits[$k]??0)+1;}public static function availableIn($k){return 300;}}
class Http {public static array $sent=[];public static $reply;public static function withHeaders(array $h){return new class($h){public function __construct(public array $h){}public function acceptJson(){return $this;}public function asJson(){return $this;}public function connectTimeout($s){return $this;}public function timeout($s){return $this;}
    public function post($url,$body){Http::$sent[]=['url'=>$url,'headers'=>$this->h,'body'=>$body];[$status,$json]=Http::$reply;return new class($status,$json){public function __construct(public int $s,public array $j){}public function json(){return $this->j;}public function successful(){return $this->s<300;}public function status(){return $this->s;}};}};}}
}
namespace App\Repositories\Daemon {final class DaemonServerRepository {public static array $lines=[];public function setServer($s){return $this;}public function getInstallLogs(){return "install line 1\ninstall failed: disk full";}
    public function getHttpClient(){return new class{public function timeout($s){return $this;}public function get($url,$q){return new class{public function throw(){return $this;}public function json($k){return DaemonServerRepository::$lines;}};}};}}}
namespace App\Models {class Server {public function __construct(public string $uuid='abc',public $status=null,public $egg=null,public $node=null){}}}
namespace App\Repositories\Daemon {final class DaemonSystemRepository {public static string $report='';public function setNode($n){return $this;}public function getDiagnostics($l,$e,$g){return new class{public function throw(){return $this;}public function body(){return DaemonSystemRepository::$report;}};}}}
namespace Simplify\Panel\Services {require __DIR__.'/../src/Services/JsonFile.php';}
namespace {
$GLOBALS['user']=null;
function user(){return $GLOBALS['user'];}
function encrypt($v){return 'enc:'.strrev($v);}
function decrypt($v){if(!str_starts_with($v,'enc:'))throw new \RuntimeException('bad');return strrev(substr($v,4));}
function abort_unless($c,$code){if(!$c)throw new \RuntimeException('abort '.$code);}
function app($class){return new $class;}
function report($e){}
function now(){return new class{public function addMinutes($m){return $m;}};}
require __DIR__.'/../src/Services/ErrorExplainer.php';
use Simplify\Panel\Services\ErrorExplainer as E;
use Illuminate\Support\Facades\{Storage,Http,Cache,RateLimiter};
use App\Repositories\Daemon\DaemonSystemRepository;
function check($condition,$message){if(!$condition)throw new \Exception($message);}
$person=fn(bool $root,bool $admin,int $id=7)=>new class($root,$admin,$id){public function __construct(public bool $r,public bool $a,public int $id){}public function isRootAdmin(){return $this->r;}public function isAdmin(){return $this->a;}};
$server=new \App\Models\Server('abc',null,(object)['name'=>'Forge Minecraft']);

// Off until a root admin saves a key; the key is stored encrypted and never shown again.
check(E::settings()===['model'=>'claude-opus-5-5','audience'=>'everyone','has_key'=>false],'defaults without a key');
check(!E::availableFor($person(false,false)),'no button without a key');
$GLOBALS['user']=$person(false,false);
try{E::save(['key'=>'sk-ant-x']);check(false,'non-admins cannot save');}catch(\RuntimeException $e){check($e->getMessage()==='abort 403','non-admins get a 403');}
$GLOBALS['user']=$person(true,true);
E::save(['key'=>'  sk-ant-secret-1234  ','model'=>'claude-haiku-5-5','audience'=>'admins']);
$raw=Storage::$files[E::FILE];
check(!str_contains($raw,'sk-ant-secret-1234')&&str_contains($raw,'enc:'),'key stored encrypted only');
check(E::settings()===['model'=>'claude-haiku-5-5','audience'=>'admins','has_key'=>true],'saved choices');
E::save(['key'=>'','model'=>'unknown-model','audience'=>'everyone']);
check(E::settings()['has_key']&&E::settings()['model']==='claude-haiku-5-5'&&E::settings()['audience']==='everyone','empty key keeps the saved key; an unknown model keeps the current one');
check(E::availableFor($person(false,false)),'everyone who can see the console');
E::save(['audience'=>'admins']);
check(!E::availableFor($person(false,false))&&E::availableFor($person(false,true)),'administrators only');
E::save(['audience'=>'everyone','model'=>'claude-opus-5-5']);

// Privacy: addresses, e-mail addresses, passwords and tokens never leave the panel; game details stay.
$clean=E::sanitize(["\e[33m[12:01:02] [Server thread/WARN]: Can't keep up!\e[0m",'Player joined from /203.0.113.7:51234','IPv6 2001:db8:85a3::8a2e:370:7334 and fe80:0:0:0:200:f8ff:fe21:67cf','rcon.password=hunter2 token: abc123','mail admin@example.com','key ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcd','UUID of player Steve is 069a79f4-44e9-4726-a5be-fca90e38aaf5','Loading mod create-1.20.1-0.5.1.f.jar from /home/container/mods/some_long_folder_name/another_long_name','   ','java.lang.OutOfMemoryError: Java heap space']);
check($clean[0]==="[12:01:02] [Server thread/WARN]: Can't keep up!",'colour codes removed, timestamps kept');
check(!str_contains(implode("\n",$clean),'203.0.113.7')&&str_contains($clean[1],'[hidden ip]'),'IPv4 hidden');
check(!str_contains($clean[2],'fe80:0:0:0'),'IPv6 hidden');
check($clean[3]==='rcon.password=[hidden] token: [hidden]','passwords and tokens hidden, names kept');
check($clean[4]==='mail [hidden email]','e-mail hidden');
check($clean[5]==='key [hidden]'&&!str_contains($clean[6],'069a79f4'),'long tokens and UUIDs hidden');
check(str_contains($clean[7],'create-1.20.1-0.5.1.f.jar')&&str_contains($clean[7],'/home/container/mods/some_long_folder_name/another_long_name'),'mod names and paths stay readable');
check(count($clean)===9&&end($clean)==='java.lang.OutOfMemoryError: Java heap space','blank lines dropped');
check(count(E::sanitize(array_fill(0,250,'line')))===E::LINES&&mb_strlen(E::sanitize([str_repeat('a ',800)])[0])===600,'at most 100 lines of 600 characters');

// The request: structured answer, low effort, fallbacks only where Anthropic offers them.
$payload=E::payload(['line one','line two'],'claude-opus-5-5','Game or software: Paper');
check($payload['model']==='claude-opus-5-5'&&$payload['output_config']['effort']==='low'&&$payload['output_config']['format']['type']==='json_schema','model, effort and JSON answer');
check(!isset($payload['thinking'],$payload['temperature'])&&$payload['fallbacks']==='default','no thinking or sampling settings; fallbacks on for Opus');
check(str_contains($payload['messages'][0]['content'],"Game or software: Paper\n")&&str_contains($payload['messages'][0]['content'],"<log>\nline one\nline two\n</log>"),'console in the question');
check(!isset(E::payload(['x'],'claude-haiku-5-5')['fallbacks']),'Haiku has no fallbacks');
check($payload['output_config']['format']['schema']['additionalProperties']===false,'strict schema');

// Reading answers and failures.
$reply=fn(array $answer,string $stop='end_turn')=>['stop_reason'=>$stop,'content'=>[['type'=>'thinking','thinking'=>''],['type'=>'text','text'=>json_encode($answer)]]];
check(E::answer($reply(['found'=>true,'problem'=>' Out of memory. ','cause'=>'Heap too small','steps'=>['Give it 6 GB','',' Remove a mod ','3','4','5','6']]))===['found'=>true,'problem'=>'Out of memory.','cause'=>'Heap too small','steps'=>['Give it 6 GB','Remove a mod','3','4','5']],'answer trimmed, at most five steps');
check(E::answer($reply(['problem'=>'x'],'refusal'))===null&&E::answer(['content'=>[['type'=>'text','text'=>'not json']]])===null,'refusals and broken answers give null');
check(str_contains(E::failure(401,[]),'not valid')&&str_contains(E::failure(400,['error'=>['type'=>'invalid_request_error','message'=>'Your credit balance is too low']]),'no credit')&&str_contains(E::failure(529,[]),'temporarily')&&str_contains(E::failure(429,[]),'busy'),'plain failure messages');

// Explaining a console: read from Wings, sent once, cached, rate limited.
\App\Repositories\Daemon\DaemonServerRepository::$lines=['[INFO] Starting','[ERROR] Mod foo requires bar 2.0','connect from 198.51.100.4'];
$GLOBALS['user']=$person(false,false,9);
Http::$reply=[200,$reply(['found'=>true,'problem'=>'A mod is missing a dependency.','cause'=>'foo needs bar 2.0','steps'=>['Install bar 2.0']])];
$result=E::explain($server);
check($result['ok']&&$result['problem']==='A mod is missing a dependency.'&&$result['lines']===3&&$result['model']==='Claude Opus 5.5','explained');
$sent=Http::$sent[0];
check($sent['url']==='https://api.anthropic.com/v1/messages'&&$sent['headers']['x-api-key']==='sk-ant-secret-1234'&&$sent['headers']['anthropic-version']==='2023-06-01'&&$sent['headers']['anthropic-beta']==='server-side-fallback-2026-07-01','decrypted key only in the request header');
check(!str_contains($sent['body']['messages'][0]['content'],'198.51.100.4')&&str_contains($sent['body']['messages'][0]['content'],'Forge Minecraft'),'sanitized lines and the game');
check(E::explain($server)['ok']&&count(Http::$sent)===1,'same lines: cached answer, no second request');
for($i=0;$i<10;$i++){\App\Repositories\Daemon\DaemonServerRepository::$lines=['new error '.$i];E::explain($server);}
check(count(Http::$sent)===E::PER_USER&&str_contains(E::explain($server)['error'],'a lot of questions'),'per-person limit');
RateLimiter::$hits=[];Http::$sent=[];
Http::$reply=[401,['type'=>'error','error'=>['type'=>'authentication_error','message'=>'invalid x-api-key']]];
\App\Repositories\Daemon\DaemonServerRepository::$lines=['another'];
$failed=E::explain($server);
check(!$failed['ok']&&str_contains($failed['error'],'not valid')&&!str_contains(json_encode($failed),'sk-ant'),'failure without the key');
\App\Repositories\Daemon\DaemonServerRepository::$lines=[];
check(str_contains(E::explain($server)['error'],'empty'),'empty console');
$installing=new \App\Models\Server('abc',(object)['value'=>'install_failed']);
Http::$reply=[200,$reply(['found'=>true,'problem'=>'Disk full.','cause'=>'','steps'=>[]])];
check(E::explain($installing)['problem']==='Disk full.'&&str_contains(end(Http::$sent)['body']['messages'][0]['content'],'install failed: disk full'),'failed installs use the install log');

// Removing the key turns it off; a key that no longer decrypts (new APP_KEY) counts as missing.
$GLOBALS['user']=$person(true,true);
E::save([],true);
check(!E::settings()['has_key']&&!E::availableFor($person(false,true)),'removed');
check(E::sourcesFor($person(true,true),$server)===[],'no key: nothing');
Storage::$files[E::FILE]=json_encode(['key'=>'garbage','model'=>'claude-opus-5-5']);
check(str_contains(E::explain($server)['error'],'not set up'),'undecryptable key');
check(E::test('')['message']==='Enter an API key first.','test without a key');
Http::$reply=[200,['stop_reason'=>'max_tokens','content'=>[]]];
$test=E::test('sk-ant-typed','claude-sonnet-5-5');
check($test['ok']&&$test['message']==='The key works with Claude Sonnet 5.5.'&&end(Http::$sent)['body']['model']==='claude-sonnet-5-5'&&!isset(end(Http::$sent)['headers']['anthropic-beta']),'test uses the typed key and model');
// Root admins: the panel log (warnings and errors only, newest file) and the Wings log of the node.
$GLOBALS['user']=$person(true,true);E::save(['key'=>'sk-ant-secret-1234']);RateLimiter::$hits=[];
check(E::sourcesFor($person(false,false),$server)===['console']&&E::sourcesFor($person(false,true),$server)===['console']&&E::sourcesFor($person(true,true),$server)===['console','panel','wings']&&E::sourcesFor($person(true,true),null)===['panel'],'console for everyone, logs for root admins only, no Wings log without a server');
$log="[2026-10-27 07:00:00] production.INFO: Started\n[2026-10-27 07:01:00] production.ERROR: SQLSTATE[HY000] database is locked {\"exception\":\"[object] (PDOException(code: 5))\"}\n[stacktrace]\n#0 /var/www/pelican/vendor/laravel/framework/src/Illuminate/Database/Connection.php(587)\n".implode("\n",array_map(fn($i)=>"#$i frame $i",range(1,30)))."\n[2026-10-27 07:02:00] production.WARNING: Wings 10.0.0.5 did not answer\n[2026-10-27 07:03:00] production.DEBUG: noise";
$entries=E::panelEntries($log);
check($entries[0]==='[2026-10-27 07:01:00] production.ERROR: SQLSTATE[HY000] database is locked {"exception":"[object] (PDOException(code: 5))"}'&&count($entries)===13&&end($entries)==='[2026-10-27 07:02:00] production.WARNING: Wings 10.0.0.5 did not answer','errors and warnings only, twelve lines per entry');
check(E::panelEntries("[2026-10-27 07:00:00] production.INFO: fine")===[]&&count(E::panelEntries(implode("\n",array_map(fn($i)=>"[2026-10-27 07:00:0$i] production.ERROR: e$i",range(0,9)))))===8,'nothing without errors, at most eight entries');
$GLOBALS['logs']=sys_get_temp_dir().'/simplify-'.getmypid().'-laravel-';
E::$panelLogs=$GLOBALS['logs'].'*.log';$old=$GLOBALS['logs'].'2026-10-26.log';$new=$GLOBALS['logs'].'2026-10-27.log';
$put=function($path,$text){$h=fopen($path,'w');fprintf($h,'%s',$text);fclose($h);};$put($old,'old');$put($new,str_repeat("x\n",200000).$log);
check(str_ends_with(E::panelLogText(),'production.DEBUG: noise')&&strlen(E::panelLogText())===262144,'newest file, last 256 KB');
Http::$reply=[200,$reply(['found'=>true,'problem'=>'The database is locked.','cause'=>'','steps'=>[]])];Http::$sent=[];
$panel=E::explain(null,'panel');$question=Http::$sent[0]['body']['messages'][0]['content'];
check($panel['ok']&&$panel['source']==='panel'&&str_contains($question,'Pelican panel log')&&str_contains($question,'database is locked')&&!str_contains($question,'10.0.0.5')&&!str_contains($question,'noise'),'panel log explained, sanitized');
DaemonSystemRepository::$report="Wings version 1.0.0\nDocker: 27\n\n------------ Latest Wings Logs ------------\nINFO booting\nERROR failed to pull image ghcr.io/pelican-eggs/yolks:java_21\n";
check(E::wingsLog(DaemonSystemRepository::$report)===['INFO booting','ERROR failed to pull image ghcr.io/pelican-eggs/yolks:java_21'],'only the log part of the diagnostics');
$wings=E::explain($server,'wings');
check($wings['ok']&&str_contains(end(Http::$sent)['body']['messages'][0]['content'],'Wings daemon log')&&str_contains(end(Http::$sent)['body']['messages'][0]['content'],'failed to pull image'),'Wings log explained');
$GLOBALS['user']=$person(false,true);
check(str_contains(E::explain(null,'panel')['error'],'not set up')&&str_contains(E::explain($server,'wings')['error'],'not set up'),'other admins cannot send the logs');
$GLOBALS['user']=$person(true,true);$put($new,"[2026-10-27 08:00:00] production.INFO: quiet");
check(E::explain(null,'panel')['error']==='The panel log has no warnings or errors right now.','quiet panel log');
$put($old,'');$put($new,'');
echo "PASS error explainer: encrypted key, audience, privacy filter, request shape, answers, failures, Wings and install logs, panel and Wings logs for root admins, cache and rate limits\n";
}
