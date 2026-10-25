<?php
namespace Illuminate\Support\Facades {class Cache {public static array $store=[];public static function get($k){return self::$store[$k]??null;}public static function put($k,$v,$t=null){self::$store[$k]=$v;return true;}public static function add($k,$v,$t=null){if(isset(self::$store[$k]))return false;self::$store[$k]=$v;return true;}public static function forget($k){unset(self::$store[$k]);}}}
namespace Simplify\Panel\Services {
final class BuildInfo {public static ?string $version='0.5.0';public static function version(): ?string {return self::$version;}}
final class UpdateCheck {public static ?array $status=null;public static function status(): ?array {return self::$status;}}
final class FeatureSettings {public static function enabled(string $f): bool {return false;}}
final class PluginUpdater {public static array $plugins=[];public static function available(): array {return self::$plugins;}public static function latestVersion($p): ?string {return $p->latest;}public static function panelVersion(): string {return '1.0.0-rc.2';}}
}
namespace App\Services\Helpers {final class SoftwareVersionService {public function latestWingsVersion(){return 'v1.0.1';}public function latestPanelVersion(){return 'v1.0.0';}}}
namespace App\Models {final class Node {public static array $nodes=[];public static function query(){return new class{public function get(){return Node::$nodes;}};}}}
namespace {
function now(){return new class{public function addDays($d){return $d;}public function addMinutes($m){return $m;}};}
function app($class){return new $class;}
require __DIR__.'/../src/Services/Announcements.php';
require __DIR__.'/../src/Services/PanelUpdates.php';
use Simplify\Panel\Services\{PanelUpdates as P,UpdateCheck,BuildInfo,PluginUpdater};
use Illuminate\Support\Facades\Cache;
function check($condition,$message){if(!$condition)throw new \Exception($message);}
check(P::version(fn()=>'v1.2.3')==='1.2.3'&&P::version(fn()=>'1.0.0-rc.2')==='1.0.0-rc.2'&&P::version(fn()=>'develop')===null&&P::version(fn()=>'error')===null&&P::version(fn()=>throw new \RuntimeException('down'))===null,'versions: v-prefix dropped, develop builds and errors ignored');

// Before the first scheduled check only Simplify's own update shows, and it goes once that version runs.
UpdateCheck::$status=['current'=>'0.5.0','version'=>'0.5.1','available'=>true,'url'=>'/admin/simplify-plugins'];
$first=P::announcements(1000);
check(count($first)===1&&$first[0]['title']==='Simplify 0.5.1 is available'&&$first[0]['from']==='Panel'&&$first[0]['audience']==='admins'&&$first[0]['action']===['label'=>'View update','url'=>'/admin/simplify-plugins'],'Simplify update without the scheduler');
BuildInfo::$version='0.5.1';check(P::announcements(1000)===[],'gone once 0.5.1 runs, also when Pelican\'s own updater installed it');
BuildInfo::$version='0.5.0';

// The scheduled check: Simplify, other plugins, Wings per node and Pelican.
PluginUpdater::$plugins=[(object)['id'=>'pelican-simplify','name'=>'PelicanSimplify','version'=>'0.5.0','latest'=>'0.5.1'],(object)['id'=>'player-counter','name'=>'Player Counter','version'=>'1.2.0','latest'=>'1.3.0']];
\App\Models\Node::$nodes=[
    new class{public $id=1;public $name='node-eu-1';public function systemInformation(){return ['version'=>'1.0.1'];}},
    new class{public $id=2;public $name='node-eu-2';public function systemInformation(){return ['version'=>'v1.0.0'];}},
    new class{public $id=3;public $name='offline';public function systemInformation(){throw new \RuntimeException('unreachable');}},
    new class{public $id=4;public $name='dev';public function systemInformation(){return ['version'=>'develop'];}},
];
Cache::$store['simplify.panel-update-seen.panel-pelican-1.0.0']=500;
$list=P::refresh();
check(array_column($list,'title')===['Simplify 0.5.1 is available','Player Counter 1.3.0 is available','Wings 1.0.1 is available for node-eu-2','Pelican 1.0.0 is available'],'one announcement per update; unreachable and develop nodes are skipped');
check(array_column($list,'key')===['panel-simplify-0.5.1','panel-plugin-player-counter-1.3.0','panel-wings-2-1.0.1','panel-pelican-1.0.0'],'stable keys, so hiding one keeps it hidden');
$shown=P::announcements(500+7200);
check(count($shown)===4&&$shown[3]['time']==='2 hours ago','the bell says when an update was first seen');
P::forget();UpdateCheck::$status=null;check(P::announcements()===[],'after an update the list is empty until the next check');
echo "PASS panel updates: version parsing, Simplify update without the scheduler, plugins, Wings per node and Pelican, stable keys, first-seen times\n";
}
