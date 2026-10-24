<?php
namespace Illuminate\Support\Facades {class Storage {public static array $records=[];public static function disk($name){return new self;}public function exists($path){return isset(self::$records[$path]);}public function get($path){return self::$records[$path];}public function put($path,$value){self::$records[$path]=$value;return true;}}}
namespace {
function report($e){}
require __DIR__.'/../src/Services/OverviewGroups.php';
use Simplify\Panel\Services\OverviewGroups as Groups;
use Illuminate\Support\Facades\Storage;
function check($condition,$message){if(!$condition)throw new \Exception($message);}
$jeroen=(object)['id'=>7];$other=(object)['id'=>8];
check(Groups::get($jeroen)===null,'an account that never saved groups gets null, so the browser can hand its groups over');
$saved=Groups::save($jeroen,[['id'=>'g1','name'=>'  Survival   &  co ','servers'=>['a','b','a']],['id'=>'g2','name'=>'','servers'=>['b','c',5,'../x']],['id'=>'g1','name'=>'Dup','servers'=>['d']],['id'=>'bad id!','servers'=>[]],'x',['name'=>'no id']]);
check($saved===[['id'=>'g1','name'=>'Survival & co','servers'=>['a','b']],['id'=>'g2','name'=>'Group','servers'=>['c']]],'groups are cleaned: one group per server, valid ids and uuids, a name');
check(Groups::get($jeroen)===$saved,'saved groups come back on the next page load');
check(Groups::get($other)===null,'accounts do not share groups');
check(array_keys(Storage::$records)===['simplify/overview-groups/7.json'],'one file per account id');
$many=Groups::save($jeroen,array_map(fn($i)=>['id'=>'g'.$i,'name'=>str_repeat('x',80),'servers'=>[]],range(1,40)));
check(count($many)===30 && mb_strlen($many[0]['name'])===40,'at most 30 groups, names at most 40 characters');
check(Groups::save($jeroen,[])===[] && Groups::get($jeroen)===[],'removing every group saves an empty list, not null');
Storage::$records['simplify/overview-groups/8.json']='{bad';check(Groups::get($other)===[],'a broken file reads as no groups');
echo "PASS server groups saved per account: hand-over signal, cleaning, limits, isolation and broken files\n";
}
