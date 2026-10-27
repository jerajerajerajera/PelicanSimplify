<?php
declare(strict_types=1);
namespace Illuminate\Support\Facades {
 class Storage {public static array $files=[];public static function disk($d){return new self;}public function get($k){return self::$files[$k]??null;}public function put($k,$v){self::$files[$k]=$v;return true;}}
 class Cache {public static array $forgot=[];public static function forget($k){self::$forgot[]=$k;}public static function lock(...$a){return new self;}public function block($s,$c){return $c();}}
}
namespace App\Models {
 class Server {public $uuid='u1';public $allocation;public $egg;public function __construct(?string $ip,?string $alias,int $port){$this->allocation=(object)['ip'=>$ip,'ip_alias'=>$alias,'port'=>$port];$this->egg=(object)['name'=>'Paper'];}}
}
namespace Simplify\Panel\Services {
 class FeatureSettings {public static bool $on=true;public static function enabled($k){return $k==='website_embed'&&self::$on;}}
 class Subdomains {public static ?string $address=null;public static array $settings=['target'=>'','port_min'=>25565,'port_max'=>25600];
  public static function address($s){return self::$address;}public static function settings(){return self::$settings;}public static function forwarded(int $p){return $p>=self::$settings['port_min']&&$p<=self::$settings['port_max'];}}
}
namespace {
require_once __DIR__.'/../src/Services/JsonFile.php';
require __DIR__.'/../src/Services/ServerMetadata.php';
require __DIR__.'/../src/Services/WebsiteEmbed.php';
use Simplify\Panel\Services\{WebsiteEmbed as E,Subdomains};
use App\Models\Server;
function report($e){}
function check($ok,$message){if(!$ok)throw new \RuntimeException('FAIL '.$message);echo "PASS $message\n";}

// Visitors only ever get an address they can reach from the internet.
foreach(['play.example.com','203.0.113.24:25565','oak.example.com:25570','[2a01:4f8::1]:25565'] as $a)check(E::reachable($a),"public address $a is published");
foreach(['192.168.1.20:25565','10.0.0.5:25565','172.16.3.1:25565','100.101.102.103:25565','127.0.0.1:25565','0.0.0.0:25565','box.tailnet.ts.net:25565','server.local:25565','localhost:25565','[fd7a:115c:a1e0::1]:25565','[::1]:25565','nas:25565']as $a)check(!E::reachable($a),"private address $a is never published");

// Address order: subdomain, forwarded game address, allocation alias, allocation IP; the first public one wins.
check(E::address(new Server('100.90.1.2','',25565))===null,'a Tailscale-only allocation shows no join address');
check(E::address(new Server('0.0.0.0','203.0.113.24',25565))==='203.0.113.24:25565','the public alias of a wildcard allocation is used');
Subdomains::$settings['target']='play.example.com';
check(E::address(new Server('192.168.1.20','',25570))==='play.example.com:25570','a forwarded port uses the game address');
check(E::address(new Server('192.168.1.20','',30000))===null,'a port outside the forwarded range is not given the game address');
Subdomains::$address='oak.example.com';
check(E::address(new Server('192.168.1.20','',25570))==='oak.example.com','a connected subdomain comes first');
Subdomains::$address=null;

// Options are cleaned: unknown values fall back, colours must be #rrggbb, the title has no control characters.
$o=E::normalize(['style'=>'neon','accent'=>'red','corners'=>'square','players'=>0,'title'=>"Our\x07 servers"]);
check($o['style']==='auto'&&$o['accent']===''&&$o['corners']==='square'&&$o['players']===false&&$o['modpack']===true&&$o['title']==='Our servers','options are normalised');
check(E::normalize(['accent'=>'#0D9488'])['accent']==='#0d9488','accent colour is kept in lower case');

// Saving settings or a server's choice empties the one-minute cache, so websites follow within a minute.
E::save(['style'=>'dark']);
check(E::settings()['style']==='dark'&&in_array(E::CACHE,\Illuminate\Support\Facades\Cache::$forgot,true),'settings are stored and the cached list is refreshed');
$s=new Server('203.0.113.24','',25565);
check(!E::visible($s),'servers are not shown until someone chooses to');
E::setVisible($s,true);
check(E::visible($s),'"Show on the website" is stored with the server');

$code=E::snippet('https://panel.example.com/');
check(str_contains($code['script'],'src="https://panel.example.com/simplify/embed.js"')&&str_contains($code['script'],'id="simplify-servers"'),'the script snippet points at this panel');
check(str_contains($code['iframe'],'src="https://panel.example.com/simplify/embed"'),'the iframe snippet points at this panel');

// One code per page: a single server (also as its own status page), one game, or a hand-picked set such as a server group.
$one=E::snippet('https://panel.example.com',['server'=>'1a2b3c4d']);
check(str_contains($one['script'],'data-server="1a2b3c4d"')&&str_contains($one['iframe'],'/simplify/embed?server=1a2b3c4d')&&$one['page']==='https://panel.example.com/simplify/embed?server=1a2b3c4d&page=1','one server: script, iframe and status page');
$game=E::snippet('https://panel.example.com',['game'=>'Valheim Plus']);
check(str_contains($game['script'],'data-game="Valheim Plus"')&&str_contains($game['iframe'],'?game=Valheim%20Plus'),'one game is kept with its spaces');
$set=E::snippet('https://panel.example.com',['servers'=>['aa11','bb22','bad id"']]);
check(str_contains($set['script'],'data-servers="aa11,bb22"')&&str_contains($set['iframe'],'?servers=aa11%2Cbb22'),'a hand-picked set keeps only valid short IDs');
check(!str_contains(E::snippet('https://panel.example.com',['game'=>'<script>'])['script'],'<script>"'),'a game name with markup is not put in the code');
echo "All website embed checks passed.\n";
}
