<?php
namespace Illuminate\Support\Facades {class Cache {public static array $store=[];public static function get($k){return self::$store[$k]??null;}public static function put($k,$v,$t=null){self::$store[$k]=$v;return true;}public static function add($k,$v,$t=null){if(isset(self::$store[$k]))return false;self::$store[$k]=$v;return true;}public static function forget($k){unset(self::$store[$k]);}}}
namespace Simplify\Panel\Services {
final class BuildInfo {public static ?string $version='0.5.0';public static function version(): ?string {return self::$version;}}
final class UpdateCheck {public static ?array $status=null;public static function status(): ?array {return self::$status;}}
final class ReleaseNotes {public static array $asked=[];
    public static function release(string $repository,string $version): ?array {self::$asked[]=$repository.'@'.$version;return $repository==='pelican-dev/panel'?['lines'=>['Faster server list','Fix: backups on S3'],'url'=>'https://github.com/pelican-dev/panel/releases/tag/v'.$version]:null;}
    public static function changelog(string $url,string $version,?string $page=null): ?array {self::$asked[]=$url.'@'.$version;return str_contains($url,'PelicanSimplify/main/')?['lines'=>['Notifications show everything now.'],'url'=>$page]:null;}
    public static function forPlugin($update,$page,string $version): ?array {return is_string($update)?self::changelog($update,$version):null;}}
final class FeatureSettings {public static function enabled(string $f): bool {return false;}}
final class PluginUpdater {public const REPOSITORY='https://raw.githubusercontent.com/jerajerajerajera/PelicanSimplify/';public static array $plugins=[];public static function available(): array {return self::$plugins;}public static function latestVersion($p): ?string {return $p->latest;}public static function panelVersion(): string {return '1.0.0-rc.2';}public static function isNewer(string $a,string $b): bool {return version_compare($a,$b,'>');}}
}
namespace App\Services\Helpers {final class SoftwareVersionService {public function latestWingsVersion(){return 'v1.0.1';}public function latestPanelVersion(){return 'v1.0.0';}}}
namespace App\Models {final class Node {public static array $nodes=[];public static function query(){return new class{public function get(){return Node::$nodes;}};}}}
namespace {
function now(){return new class{public function addDays($d){return $d;}public function addMinutes($m){return $m;}};}
function app($class){return new $class;}
require __DIR__.'/../src/Services/Announcements.php';
require __DIR__.'/../src/Services/SimplifyReplaced.php';
require __DIR__.'/../src/Services/PanelUpdates.php';
use Simplify\Panel\Services\{PanelUpdates as P,UpdateCheck,BuildInfo,PluginUpdater};
use Illuminate\Support\Facades\Cache;
function check($condition,$message){if(!$condition)throw new \Exception($message);}
/** The update announcements, without PelicanSimplify's last message (checked on its own at the end). */
function updates(array $list): array {return array_values(array_filter($list,fn(array $item):bool=>$item['key']!==\Simplify\Panel\Services\SimplifyReplaced::KEY));}
check(P::version(fn()=>'v1.2.3')==='1.2.3'&&P::version(fn()=>'1.0.0-rc.2')==='1.0.0-rc.2'&&P::version(fn()=>'develop')===null&&P::version(fn()=>'error')===null&&P::version(fn()=>throw new \RuntimeException('down'))===null,'versions: v-prefix dropped, develop builds and errors ignored');

// Before the first scheduled check only Simplify's own update shows, and it goes once that version runs.
UpdateCheck::$status=['current'=>'0.5.0','version'=>'0.5.1','available'=>true,'url'=>'/admin/simplify-plugins'];
$first=updates(P::announcements(1000));
check(count($first)===1&&$first[0]['title']==='Simplify 0.5.1 is available'&&$first[0]['from']==='Panel'&&$first[0]['audience']==='admins'&&$first[0]['action']===['label'=>'View update','url'=>'/admin/simplify-plugins'],'Simplify update without the scheduler');
BuildInfo::$version='0.5.1';check(updates(P::announcements(1000))===[],'gone once 0.5.1 runs, also when Pelican\'s own updater installed it');
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
$shown=updates(P::announcements(500+7200));
check(count($shown)===4&&$shown[3]['time']==='2 hours ago','the bell says when an update was first seen');
// What's new: Simplify from its channel's CHANGELOG, Pelican and Wings from their GitHub releases, once per version.
check($shown[0]['notes']===['Notifications show everything now.']&&$shown[0]['notes_url']==='https://github.com/jerajerajerajera/PelicanSimplify/blob/main/CHANGELOG.md','Simplify\'s update says what is new, from the main branch for a stable version');
check($shown[3]['notes']===['Faster server list','Fix: backups on S3']&&str_ends_with($shown[3]['notes_url'],'/v1.0.0'),'Pelican\'s update has the release notes and a link');
check($shown[1]['notes']===[]&&$shown[2]['notes']===[]&&$shown[2]['notes_url']===null,'no notes when they cannot be read');
check(in_array('pelican-dev/wings@1.0.1',\Simplify\Panel\Services\ReleaseNotes::$asked,true)&&count(array_filter(\Simplify\Panel\Services\ReleaseNotes::$asked,fn($a)=>str_starts_with($a,'pelican-dev/wings')))===1,'Wings notes are read once for all nodes');
P::forget();UpdateCheck::$status=null;check(updates(P::announcements())===[],'after an update the list is empty until the next check');

// PelicanSimplify's last message: always first, for root admins: end of life, the beta invite, the upgrade guide, the logo.
$last=P::announcements(1000)[0];
check($last['key']==='panel-simplify-replaces-pelicansimplify'&&$last['title']==='PelicanSimplify is end of life'&&$last['audience']==='admins','the end-of-life message comes first, for admins');
check(str_starts_with($last['text'],'This plugin is end of life and will not be updated.')&&str_contains($last['text'],'hub.pelican.dev')&&str_contains($last['text'],'Discord ID to Jera')&&str_contains($last['text'],'keep using this plugin'),'it says end of life, how to join and that waiting is fine');
check($last['action']===['label'=>'How to upgrade','url'=>'https://github.com/jerajerajerajera/PelicanSimplify/blob/main/UPGRADE.md'],'its button opens the upgrade guide');
check(count($last['notes'])<=8&&$last['notes_url']===$last['action']['url']&&!array_filter($last['notes'],fn(string $line):bool=>mb_strlen($line)>160),'what\'s new fits the bell (8 lines of at most 160 characters)');
check($last['logo']===true&&str_starts_with(\Simplify\Panel\Services\SimplifyReplaced::logo(),'data:image/svg+xml;base64,')&&!array_filter(updates(P::announcements(1000)),fn(array $item):bool=>$item['logo']),'only this message carries the Simplify logo');
BuildInfo::$version='9.9.9';check(P::announcements(1000)[0]['key']===$last['key'],'it stays whatever version runs');
echo "PASS panel updates: version parsing, Simplify update without the scheduler, plugins, Wings per node and Pelican, stable keys, first-seen times, what's new, PelicanSimplify's end-of-life message\n";
}
