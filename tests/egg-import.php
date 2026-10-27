<?php
namespace App\Enums {enum EggFormat {case JSON;}}
namespace App\Models {class Egg {public int $id=1; public string $name='';}}
namespace Illuminate\Contracts\Cache {class LockTimeoutException extends \Exception {}}
namespace Illuminate\Validation {class ValidationException extends \Exception {public static function withMessages($m){return new self(json_encode($m));}}}
namespace Illuminate\Support\Facades {class Cache {public static $race=null;public static function lock($key,$ttl){return new self;}public function block($time,$callback){if(self::$race){(self::$race)();self::$race=null;}return $callback();}}}
namespace App\Services\Eggs\Sharing {class EggImporterService {public array $calls=[]; public function fromContent($content,$format,$model){$d=json_decode($content,true);if(isset($d['uuid']))throw new \Exception('original UUID reused');if(!$model instanceof \App\Models\Egg)throw new \Exception('existing model risk');$model->name=$d['name'];$this->calls[]=$model->name;\Simplify\Panel\Services\MinecraftCatalog::$egg=$model;return $model;}}}
namespace Simplify\Panel\Services {class CreatorAccess {public static function canCreate(){return $GLOBALS['sscGrant']??false;}public static function canImportEggs(){return ($GLOBALS['authorized']??false)||(($GLOBALS['sscGrant']??false)&&($GLOBALS['eggImportAllowed']??false));}}class MinecraftCatalog {public static $egg=null;public static function eggForType($t,$s=null){return self::$egg;}public static function eggFile($t,$s=null){return ['vanilla'=>'egg-vanilla-minecraft.json','plus'=>$s==='purpur'?'egg-purpur.json':'egg-paper.json','loader'=>'egg-'.($s?:'fabric').'.json','bedrock'=>'egg-vanilla-bedrock.json','proxy'=>'egg-'.($s?:'velocity').'.json'][$t]??null;}}class ModpackCatalog {public static function egg($k){return MinecraftCatalog::$egg;}public static function split($key){if(!preg_match('/^(modrinth|ftb|curseforge|technic):([a-zA-Z0-9_-]+)$/',$key??'',$m))return null;return [$m[1],$m[2]];}}}
namespace {
$authorized=true;$importer=new \App\Services\Eggs\Sharing\EggImporterService;
function user(){return new class {public function can($p){return $GLOBALS['authorized'] && $p==='import egg';}};}
function app($c){return $GLOBALS['importer'];}
function report($e){}
function plugin_path($id,$path){return __DIR__.'/../'.$path;}
require __DIR__.'/../src/Services/EggInstaller.php';
use Simplify\Panel\Services\EggInstaller as I;
use Simplify\Panel\Services\MinecraftCatalog as C;
function check($pass,$name){if(!$pass)throw new \Exception($name);echo "PASS $name\n";}
$known=new \App\Models\Egg;$known->name='Custom existing Vanilla';C::$egg=$known;$authorized=false;
check(I::ensure(['server_type'=>'vanilla'])===$known && count($importer->calls)===0,'existing egg reused without import permission or modifications');
C::$egg=null;
try{I::ensure(['server_type'=>'plus']);throw new \Exception('permission bypass');}catch(\Illuminate\Validation\ValidationException $e){check(count($importer->calls)===0,'missing egg requires native import permission');}
$authorized=true;$states=[['server_type'=>'vanilla'],['server_type'=>'plus'],['server_type'=>'plus','server_software'=>'purpur'],['server_type'=>'loader','server_software'=>'fabric'],['server_type'=>'loader','server_software'=>'forge'],['server_type'=>'loader','server_software'=>'neoforge'],['server_type'=>'loader','server_software'=>'quilt'],['server_type'=>'bedrock'],['server_type'=>'proxy','server_software'=>'velocity'],['server_type'=>'proxy','server_software'=>'bungeecord']];
foreach(['modrinth:project','ftb:100','curseforge:123','technic:tekkit-2','technic:tekkitmain','technic:tekkit','technic:tekkit-legends','technic:tekkit-smp','technic:hexxit','technic:blightfall','technic:attack-of-the-bteam','technic:the-1122-pack','technic:the-1710-pack'] as $key){$states[]=['server_type'=>'modded','modpack_project'=>$key];}
foreach($states as $state){C::$egg=null;$before=count($importer->calls);$egg=I::ensure($state);check(count($importer->calls)===$before+1,'bundled import: '.$egg->name);I::ensure($state);check(count($importer->calls)===$before+1,'repeat selection reuses: '.$egg->name);}
C::$egg=null;\Illuminate\Support\Facades\Cache::$race=function()use($known){C::$egg=$known;};$before=count($importer->calls);
check(I::ensure(['server_type'=>'vanilla'])===$known && count($importer->calls)===$before,'concurrent import rechecked under lock');
check(I::file(['server_type'=>'modded','modpack_project'=>'technic:../../escape'])===null,'unsupported/path-like pack rejected');
C::$egg=null;$authorized=false;$sscGrant=true;$eggImportAllowed=false;$before=count($importer->calls);try{I::ensure(['server_type'=>'plus']);check(false,'creator without the egg-install permission must not import');}catch(\Illuminate\Validation\ValidationException $e){check(count($importer->calls)===$before,'refused import must not run');}
$eggImportAllowed=true;$before=count($importer->calls);I::ensure(['server_type'=>'plus']);check(count($importer->calls)===$before+1,'approved SSC client can import only a bundled missing egg');

}
