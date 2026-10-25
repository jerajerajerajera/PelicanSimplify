<?php
namespace Illuminate\Support\Facades {class Storage {public static array $records=[];public static function disk($name){return new self;}public function exists($path){return isset(self::$records[$path]);}public function get($path){return self::$records[$path]??null;}public function put($path,$value){self::$records[$path]=$value;return true;}}}
namespace {
function report($e){}
require_once __DIR__.'/../src/Services/JsonFile.php';
require __DIR__.'/../src/Services/OverviewGroups.php';
use Simplify\Panel\Services\OverviewGroups as Groups;
use Illuminate\Support\Facades\Storage;
function check($condition,$message){if(!$condition)throw new \Exception($message);}
$jeroen=(object)['id'=>7];$other=(object)['id'=>8];
check(Groups::get($jeroen)===null,'an account that never saved groups gets null, so the browser can hand its groups over');
$saved=Groups::save($jeroen,[['id'=>'g1','name'=>'  Survival   &  co ','servers'=>['a','b','a'],'sort'=>'manual'],['id'=>'g2','name'=>'','servers'=>['b','c',5,'../x'],'sort'=>'random'],['id'=>'g1','name'=>'Dup','servers'=>['d']],['id'=>'bad id!','servers'=>[]],'x',['name'=>'no id']],'port');
check($saved===['groups'=>[['id'=>'g1','name'=>'Survival & co','servers'=>['a','b'],'sort'=>'manual'],['id'=>'g2','name'=>'Group','servers'=>['c'],'sort'=>'overview']],'order'=>'port'],'groups are cleaned: one group per server, valid ids, uuids and orders, a name');
check(Groups::get($jeroen)===$saved,'saved groups and order come back on the next page load');
check(Groups::get($other)===null,'accounts do not share groups');
check(array_keys(Storage::$records)===['simplify/overview-groups/7.json'],'one file per account id');
check(Groups::save($jeroen,[],'sideways')['order']==='name','an unknown server order falls back to Name');
$many=Groups::save($jeroen,array_map(fn($i)=>['id'=>'g'.$i,'name'=>str_repeat('x',80),'servers'=>[]],range(1,40)))['groups'];
check(count($many)===30 && mb_strlen($many[0]['name'])===40,'at most 30 groups, names at most 40 characters');
check(Groups::save($jeroen,[])['groups']===[] && Groups::get($jeroen)===['groups'=>[],'order'=>'name'],'removing every group saves an empty list, not null');
Storage::$records['simplify/overview-groups/7.json']=json_encode([['id'=>'old','name'=>'Old','servers'=>['a']]]);
check(Groups::get($jeroen)===['groups'=>[['id'=>'old','name'=>'Old','servers'=>['a'],'sort'=>'overview']],'order'=>'name'],'files from 0.5.0-snapshot.13 / beta.6 (just a list) still load');
Storage::$records['simplify/overview-groups/8.json']='{bad';check(Groups::get($other)===['groups'=>[],'order'=>'name'],'a broken file reads as no groups');
echo "PASS server groups saved per account: hand-over signal, cleaning, server and group order, limits, isolation, older files and broken files\n";
}
