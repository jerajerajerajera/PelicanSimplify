<?php
declare(strict_types=1);
namespace Illuminate\Support\Facades {
class Storage {public static array $files=[];public static function disk($d){return new class{public function get($p){return Storage::$files[$p]??null;}public function put($p,$c){Storage::$files[$p]=$c;return true;}};}}
class Cache {public static function get($k){throw new \RuntimeException('cache down');}public static function put($k,$v,$t=null){throw new \RuntimeException('cache down');}}
class RateLimiter {public static array $hits=[];public static function tooManyAttempts($k,$max){return (self::$hits[$k]??0)>=$max;}public static function hit($k,$decay){self::$hits[$k]=(self::$hits[$k]??0)+1;}public static function availableIn($k){return 300;}}
class Http {public static array $sent=[];public static $reply;public static function withHeaders(array $h){return new class($h){public function __construct(public array $h){}public function acceptJson(){return $this;}public function asJson(){return $this;}public function connectTimeout($s){return $this;}public function timeout($s){return $this;}
    public function post($url,$body){Http::$sent[]=['headers'=>$this->h,'body'=>$body];[$status,$json]=Http::$reply;return new class($status,$json){public function __construct(public int $s,public array $j){}public function json(){return $this->j;}public function successful(){return $this->s<300;}public function status(){return $this->s;}};}};}}
}
namespace Illuminate\Http {class Request {public function __construct(public string $method='GET',public array $data=[]){}public function isMethod($m){return strtoupper($m)===$this->method;}public function input($k){return $this->data[$k]??null;}public function filled($k){return ($this->data[$k]??'')!=='';}}}
namespace App\Models {class Server {}class Node {public static bool $down=false;public static function query(){if(self::$down)throw new \RuntimeException('SQLSTATE[HY000] [2002] Connection refused');
    return new class{public function orderBy($c){return $this;}public function get(){return new class{public function keyBy($k){return $this;}public function all(){return [3=>(object)['id'=>3,'name'=>'Beelink <home>']];}};}};}}}
namespace App\Repositories\Daemon {final class DaemonSystemRepository {public static $node;public function setNode($n){self::$node=$n;return $this;}public function getDiagnostics($l,$e,$g){return new class{public function throw(){return $this;}public function body(){return "Latest Wings logs\nERROR failed to start container";}};}}}
namespace {
$GLOBALS['user']=null;
function user(){return $GLOBALS['user'];}
function csrf_token(){return 'csrf-123';}
function encrypt($v){return 'enc:'.strrev($v);}
function decrypt($v){if(!str_starts_with($v,'enc:'))throw new \RuntimeException('bad');return strrev(substr($v,4));}
function abort_unless($c,$code){if(!$c)throw new \RuntimeException('abort '.$code);}
function app($class){return new $class;}
function report($e){}
function now(){return new class{public function addMinutes($m){return $m;}};}
function response($body,$status,$headers){return (object)['body'=>$body,'status'=>$status,'headers'=>$headers];}
foreach(['Services/JsonFile','Services/ErrorExplainer','Services/EmergencyAccess','Http/Controllers/EmergencyExplainController'] as $file)require __DIR__.'/../src/'.$file.'.php';
use Simplify\Panel\Services\{ErrorExplainer as E,EmergencyAccess as Access};
use Simplify\Panel\Http\Controllers\EmergencyExplainController as Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Storage,Http,RateLimiter};
function check($condition,$message){if(!$condition)throw new \Exception($message);}
$person=fn(bool $root,int $id=7)=>new class($root,$id){public function __construct(public bool $r,public int $id){}public function isRootAdmin(){return $this->r;}public function isAdmin(){return $this->r;}};
$page=new Page;

// Temporary addresses: only a hash on disk, one at a time, an hour long.
$token=Access::issue(1000);
check(preg_match('/^[0-9a-f]{40}$/',$token)&&!str_contains(Storage::$files[Access::FILE],$token),'random token, only its hash stored');
check(Access::valid($token,1000+3599)&&!Access::valid($token,1000+3601),'valid for an hour');
check(!Access::valid(str_repeat('0',40),1000)&&!Access::valid('',1000)&&!Access::valid($token.'x',1000),'other tokens refused');
$newer=Access::issue(1000);check(!Access::valid($token,1000)&&Access::valid($newer,1000),'a new address replaces the old one');
$token=Access::issue();

// Who may use the page: signed-in root admins, or the temporary address. Answers are plain HTML, never 500.
$out=$page->session(new Request);check($out->status===401&&str_contains($out->body,'Sign in')&&str_contains($out->body,'simplify:explain --url'),'signed out: sign in, or the terminal');
$GLOBALS['user']=$person(false);check($page->session(new Request)->status===403,'other admins: 403');
check($page->token(new Request,str_repeat('a',40))->status===403,'wrong token: 403');
$GLOBALS['user']=$person(true);
$out=$page->session(new Request);check($out->status===200&&str_contains($out->body,'No Anthropic API key')&&str_contains($out->body,'php artisan simplify:explain'),'no key: where to add one');
Storage::$files[E::FILE]=json_encode(['key'=>encrypt('sk-ant-saved'),'model'=>'claude-opus-5-5']);
$out=$page->session(new Request);
check($out->status===200&&str_contains($out->body,'Check the panel log')&&str_contains($out->body,'Check Wings log of Beelink &lt;home&gt;')&&str_contains($out->body,'value="csrf-123"'),'choices: panel log and each node, escaped, with the CSRF token');
check($out->headers['Referrer-Policy']==='no-referrer'&&$out->headers['Cache-Control']==='no-store'&&str_contains($out->headers['Content-Security-Policy'],"default-src 'none'")&&!str_contains($out->body,'<script'),'no referrer, no cache, no scripts or outside files');

// Asking: the panel log, then a Wings log, with the saved key; the cache store is down in this test.
$GLOBALS['logs']=sys_get_temp_dir().'/simplify-emergency-'.getmypid().'-';E::$panelLogs=$GLOBALS['logs'].'*.log';
$h=fopen($GLOBALS['logs'].'laravel.log','w');fprintf($h,'%s',"[2026-10-27 09:00:00] production.ERROR: Livewire\\Exceptions\\ComponentNotFoundException: Unable to find component <x>\n#0 frame");fclose($h);
$reply=fn(array $answer)=>['stop_reason'=>'end_turn','content'=>[['type'=>'text','text'=>json_encode($answer)]]];
Http::$reply=[200,$reply(['found'=>true,'summary'=>'A plugin <b>broke</b> Livewire.','issues'=>[['title'=>'Broken component','cause'=>'Plugin X','steps'=>['Disable plugin X','Run php artisan optimize:clear']]]])];
$out=$page->session(new Request('POST',['source'=>'panel']));
$question=Http::$sent[0]['body']['messages'][0]['content'];
check(Http::$sent[0]['headers']['x-api-key']==='sk-ant-saved'&&str_contains($question,'ComponentNotFoundException')&&str_contains($question,'emergency page'),'panel log sent with the saved key and the emergency context');
check(str_contains($out->body,'Panel log: Something needs attention')&&str_contains($out->body,'A plugin &lt;b&gt;broke&lt;/b&gt; Livewire.')&&str_contains($out->body,'<details open><summary>Broken component')&&str_contains($out->body,'<li>Run php artisan optimize:clear</li>'),'answer shown, escaped, issues open');
check(RateLimiter::$hits['simplify.explain.user.7']===1,'counts against the person');
Http::$reply=[200,$reply(['found'=>false,'summary'=>'All fine.','issues'=>[['title'=>'My panel feels slow','cause'=>'Harmless','steps'=>[]]]])];
$GLOBALS['user']=null;
$out=$page->token(new Request('POST',['node'=>'3']),$token);
check(App\Repositories\Daemon\DaemonSystemRepository::$node->id===3&&str_contains($out->body,'Wings log of Beelink &lt;home&gt;: Everything seems in order')&&str_contains($out->body,'Noticed something?')&&str_contains($out->body,'<details><summary>My panel feels slow')&&!str_contains($out->body,'csrf-123'),'Wings log of the chosen node through the temporary address');
check(RateLimiter::$hits['simplify.explain.access']===1,'the address has its own limit');
check(str_contains($page->token(new Request('POST',['node'=>'99']),$token)->body,'Choose a node.'),'unknown node');

// The database is down: the panel log still works, the Wings choices say why they are missing.
App\Models\Node::$down=true;
$out=$page->token(new Request,$token);
check($out->status===200&&str_contains($out->body,'database could not be reached')&&!str_contains($out->body,'Check Wings log'),'no database: panel log only');
// Anything else that fails is shown on the page instead of a 500, without addresses.
$GLOBALS['user']=new class{public function isRootAdmin(){throw new \RuntimeException('Session store at 10.0.0.9 refused');}};
$out=$page->session(new Request);
check($out->status===200&&str_contains($out->body,'This page could not finish')&&str_contains($out->body,'[hidden ip]')&&!str_contains($out->body,'10.0.0.9'),'own failures shown, sanitized');
$h=fopen($GLOBALS['logs'].'laravel.log','w');fclose($h);
echo "PASS emergency explain page: temporary addresses, access, plain HTML without scripts, panel and Wings logs, broken database, cache and session\n";
}
