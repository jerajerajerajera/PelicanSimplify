<?php
namespace Illuminate\Support\Facades {class Storage {public static array $records=[];public static function disk($name){return new self;}public function exists($path){return isset(self::$records[$path]);}public function get($path){return self::$records[$path]??null;}public function put($path,$value){self::$records[$path]=$value;return true;}}}
namespace Illuminate\Support\Facades {class Cache {public static function lock(...$args){return new self;}public function block($seconds,$callback){return $callback();}}}
namespace App\Models {class Server {public static array $servers=[];public function __construct(public string $uuid){}public static function query(){return new self('query');}public function orderBy($column){return $this;}public function get($columns=null){return self::$servers;}}}
namespace {
function user(){return new class{function isRootAdmin(){return true;}};}
function abort_unless($ok,$code){if(!$ok)throw new \RuntimeException((string)$code);}
require_once __DIR__.'/../src/Services/JsonFile.php';
require __DIR__.'/../src/Services/ServerAppearance.php';
use Simplify\Panel\Services\ServerAppearance as Colors;
use App\Models\Server;
use Illuminate\Support\Facades\Storage;
function check($condition,$message){if(!$condition)throw new \Exception($message);}
$base=Colors::choices();check(count($base)===6,'empty panel should suggest six distinct colors');
$first=array_key_first($base);check(Colors::defaultColor()===$first,'default must select first suggestion');
Server::$servers=[new Server('a')];Storage::$records['simplify/appearance/a.json']=json_encode(['color'=>strtoupper($first)]);
$ranked=Colors::choices();check(!isset($ranked[$first]),'used color was offered');
$hue=new \ReflectionMethod(Colors::class,'hue');$hue->setAccessible(true);
$firstHue=$hue->invoke(null,$first);$suggestedHue=$hue->invoke(null,array_key_first($ranked));$delta=abs($firstHue-$suggestedHue);
check(min($delta,360-$delta)>=170,'first suggestion should be opposite the existing hue');
check(array_key_first(Colors::choices('a'))===$first,'current server must be excluded from contrast ranking');
Storage::$records=[];check(count(Colors::choices())===6,'automatic colors should still get six ranked suggestions');
check((bool)preg_match('/^#[0-9a-f]{6}$/',Colors::defaultColor()),'default must be a concrete valid color');
Server::$servers=[new Server('a'),new Server('b'),new Server('c')];
Storage::$records=['simplify/appearance/a.json'=>'{"color":"#abcdef"}'];
Colors::assignMissingColors();
check(Colors::color(Server::$servers[0])==='#abcdef','manual color must survive first setup');
$assigned=array_map(fn($server)=>Colors::color($server),Server::$servers);
check(count(array_unique($assigned))===3,'missing colors must become distinct saved colors');
$before=Storage::$records;Colors::assignMissingColors();check(Storage::$records===$before,'repeated setup must not recolor servers');
echo "PASS existing colors retained, missing server colors persisted distinctly, repeat assignment stable\n";
echo "PASS six contrasting suggestions, used-color exclusion, opposite hue first, current-server exception and automatic default\n";
}
