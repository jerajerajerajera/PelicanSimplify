<?php
namespace Illuminate\Support\Facades {class Storage {public static array $records=[];public static function disk($name){return new self;}public function exists($path){return isset(self::$records[$path]);}public function get($path){return self::$records[$path]??null;}public function put($path,$value){self::$records[$path]=$value;return true;}}}
namespace Simplify\Panel\Services {final class PanelUpdates {public static array $items=[];public static function announcements(?int $now=null): array {return self::$items;}}
final class PanelNotifications {public static array $read=[];public static array $unread=[];public static function markRead(array $ids): void {array_push(self::$read,...$ids);}public static function markUnread(array $ids): void {array_push(self::$unread,...$ids);}}}
namespace Simplify\Panel\Filament\App\Pages {final class Dashboard {public static function getUrl(array $parameters=[],?string $panel=null): string {return '/simplify?server='.$parameters['server'];}}}
namespace Boy132\Announcements\Models {final class Announcement {public static array $rows=[];public static function query(){return new class{public function get(){return Announcement::$rows;}};}}}
namespace {
function report($e){}
require_once __DIR__.'/../src/Services/JsonFile.php';
require __DIR__.'/../src/Services/InGameAnnouncements.php';
require __DIR__.'/../src/Services/Announcements.php';
use Simplify\Panel\Services\Announcements as A;
use Simplify\Panel\Services\PanelUpdates;
use Illuminate\Support\Facades\Storage;
function check($condition,$message){if(!$condition)throw new \Exception($message);}
function fails(callable $run,string $text){try{$run();}catch(\InvalidArgumentException $e){return str_contains($e->getMessage(),$text);}return false;}
date_default_timezone_set('Europe/Amsterdam');
$now=1792000000;
$person=new class{public $id=5;public function isAdmin(){return false;}public function isRootAdmin(){return false;}};
$admin=new class{public $id=1;public function isAdmin(){return true;}public function isRootAdmin(){return true;}};

// Saving: a title is required, the end comes after the start, links are https or panel paths, servers are uuids.
check(fails(fn()=>A::save(['title'=>'  '],null,'jera',$now),'title'),'a title is required');
check(fails(fn()=>A::save(['title'=>'X','starts_at'=>'2026-10-31 18:00','ends_at'=>'2026-10-31 17:00'],null,'jera',$now),'after'),'the end must be after the start');
check(fails(fn()=>A::save(['title'=>'X','link_type'=>'url','url'=>'javascript:alert(1)'],null,'jera',$now),'https://'),'only https links or panel paths');
check(fails(fn()=>A::save(['title'=>'X','link_type'=>'url','url'=>'//evil.example'],null,'jera',$now),'https://'),'no protocol-relative links');
check(fails(fn()=>A::save(['title'=>'X','link_type'=>'server','server'=>'../x'],null,'jera',$now),'server'),'a server button needs a server');
check(Storage::$records===[],'nothing is stored for a refused announcement');
$warning=A::save(['category'=>'warning','title'=>' Maintenance   on Sunday ','text'=>"Restart between 03:00 and 04:00.\r\n\r\n\r\nSave your builds.",'ends_at'=>$now+3600,'link_type'=>'none'],null,'jera',$now);
check($warning['category']==='warning'&&$warning['title']==='Maintenance on Sunday'&&$warning['text']==="Restart between 03:00 and 04:00.\n\nSave your builds."&&$warning['button']===null&&$warning['created_by']==='jera','title and text are tidied, line breaks kept (at most one empty line)');
$event=A::save(['category'=>'party','title'=>'Halloween','starts_at'=>$now+86400,'audience'=>'admins','link_type'=>'server','server'=>'1111-2222','button_label'=>''],null,'jera',$now+5);
check($event['category']==='info'&&$event['audience']==='admins'&&$event['button']===['label'=>'Open','server'=>'1111-2222'],'unknown categories become Info; an empty button text becomes Open');
$link=A::save(['category'=>'success','title'=>'New modpack','link_type'=>'url','url'=>'https://example.com/atm10','button_label'=>'Read more'],null,'jera',$now+10);
check(array_column(A::all(),'title')===['New modpack','Halloween','Maintenance on Sunday'],'newest first');
check(A::state($warning,$now)==='live'&&A::state($event,$now)==='scheduled'&&A::state($warning,$now+3600)==='ended','live, scheduled and ended');

// Editing keeps the id, author and creation time.
$edited=A::save(['category'=>'warning','title'=>'Maintenance moved','ends_at'=>$now+7200],$warning['id'],'someone',$now+20);
check($edited['id']===$warning['id']&&$edited['created_by']==='jera'&&$edited['created_at']===$now&&$edited['updated_at']===$now+20&&count(A::all())===3,'editing replaces the same announcement');
check(fails(fn()=>A::save(['title'=>'X'],'0000000000000000','jera',$now),'no longer exists'),'editing a deleted announcement is refused');

// What an account sees: live ones only, admins-only ones for admins, the plugin's and (root admins) the Panel's.
check(array_column(A::forUser($person,$now),'title')===['New modpack','Maintenance moved'],'a person sees live announcements for everyone');
$own=A::forUser($person,$now)[0];
check($own['tone']==='ok'&&$own['action']===['label'=>'Read more','url'=>'https://example.com/atm10']&&$own['from']==='jera'&&$own['dismissed']===false&&$own['key']==='own-'.$link['id'],'the dashboard gets tone, button, author and key');
check(A::forUser($person,$now)[1]['until']==='until '.date('D j M H:i',$now+7200),'the end time is shown');
$later=A::forUser($admin,$now+86400+60);
check(in_array('Halloween',array_column($later,'title'),true)&&($later[0]['action']['url']??null)==='/simplify?server=1111-2222','admins see admin announcements once they start; a server button opens that server');
check(!in_array('Halloween',array_column(A::forUser($person,$now+86400+60),'title'),true),'admins-only announcements stay hidden from others');
PanelUpdates::$items=[['key'=>'panel-simplify-0.5.1','title'=>'Simplify 0.5.1 is available','at'=>$now+30,'source'=>'panel']];
check(A::forUser($admin,$now+40)[0]['title']==='Simplify 0.5.1 is available'&&!in_array('Simplify 0.5.1 is available',array_column(A::forUser($person,$now+40),'title'),true),'the Panel\'s update announcements are for root admins only');
\Boy132\Announcements\Models\Announcement::$rows=[
    (object)['id'=>3,'title'=>'<b>From the plugin</b>','body'=>'Hello','type'=>'danger','panels'=>[],'valid_from'=>null,'valid_to'=>null,'url_label'=>'Go','url_link'=>'javascript:x','created_at'=>date('Y-m-d H:i:s',$now-60)],
    (object)['id'=>4,'title'=>'Admin panel only','body'=>'','type'=>'info','panels'=>['admin'],'valid_from'=>null,'valid_to'=>null,'url_label'=>null,'url_link'=>null,'created_at'=>null],
    (object)['id'=>5,'title'=>'Over','body'=>'','type'=>'info','panels'=>[],'valid_from'=>null,'valid_to'=>date('Y-m-d H:i:s',$now-1),'url_label'=>null,'url_link'=>null,'created_at'=>null],
];
$plugin=array_values(array_filter(A::forUser($person,$now),fn($i)=>$i['source']==='plugin'));
check(count($plugin)===1&&$plugin[0]['title']==='From the plugin'&&$plugin[0]['tone']==='danger'&&$plugin[0]['action']===null&&$plugin[0]['key']==='plugin-3','the Announcements plugin shows too: app panel, live, plain text, safe links only');

// Unpinning is per account and survives a reload; unknown keys are ignored.
A::dismiss($person,'own-'.$link['id']);A::dismiss($person,'own-'.$link['id']);A::dismiss($person,'<script>');
check(A::dismissedKeys($person)===['own-'.$link['id']],'one entry per hidden announcement, junk keys refused');
$mine=A::forUser($person,$now);
check($mine[array_search('New modpack',array_column($mine,'title'))]['dismissed']===true&&!array_filter(A::forUser($admin,$now),fn($i)=>$i['dismissed']),'unpinned for this account only');
A::dismiss($person,'own-0000000000000000');A::restore($person,'own-0000000000000000');A::restore($person,'<script>');
check(A::dismissedKeys($person)===['own-'.$link['id']],'pinning again takes it off the list, other keys stay');
A::restore($person,'own-'.$link['id']);A::dismiss($person,'own-'.$link['id']);
check(A::dismissedKeys($person)===['own-'.$link['id']],'unpin, pin, unpin again leaves one entry');

// Pinned to everyone's notifications by an admin; read and unread per account ("Mark all read").
$pin=A::save(['title'=>'Rules','pinned'=>true],null,'jera',$now+50);
check(A::find($pin['id'])['pinned']===true&&A::find($edited['id'])['pinned']===false,'pinning to everyone is saved per announcement');
$seen=A::forUser($person,$now+60);
$rules=$seen[array_search('Rules',array_column($seen,'title'))];
check($rules['pinned']===true&&$rules['unread']===true&&$rules['ends_at']===null,'a pinned announcement reaches everyone, unread at first');
check(!array_filter($seen,fn($i)=>$i['title']==='New modpack'&&$i['unread']),'an unpinned bar counts as read');
A::markAllRead($person,$now+60);
check(!array_filter(A::forUser($person,$now+60),fn($i)=>$i['unread'])&&array_filter(A::forUser($admin,$now+60),fn($i)=>$i['unread']),'mark all read is per account');
A::save(['title'=>'Later news'],null,'jera',$now+120);
check(array_column(array_filter(A::forUser($person,$now+130),fn($i)=>$i['unread']),'title')===['Later news'],'newer announcements are unread again');
check(A::forUser($person,$now+130)[array_search('Maintenance moved',array_column(A::forUser($person,$now+130),'title'))]['ends_at']===$now+7200,'the end time goes along as Unix time, for the browser\'s time zone');
// Clear read: read ones move to Archived for this account; unread and pinned ones stay; Mark all read keeps the archive.
A::archiveRead($person,$now+140);
$after=A::forUser($person,$now+140);
check(array_column(array_filter($after,fn($i)=>$i['archived']),'title')===['New modpack','Maintenance moved','From the plugin'],'read announcements are archived, also the plugin\'s');
check(!$after[array_search('Later news',array_column($after,'title'))]['archived']&&!$after[array_search('Rules',array_column($after,'title'))]['archived'],'unread and pinned ones stay');
check(A::archivedAt($person)===$now+140&&A::archivedAt($admin)===0&&!array_filter(A::forUser($admin,$now+140),fn($i)=>$i['archived']),'archiving is per account');
A::markAllRead($person,$now+150);
check(A::archivedAt($person)===$now+140&&count(A::readState($person)['archived'])===3&&A::readAt($person)===$now+150,'mark all read and clear read keep each other\'s state');
// ✕ on one: an unread announcement moves to Archived and counts as read; a Pelican notification by id, marked read.
$later=A::forUser($person,$now+160)[array_search('Later news',array_column(A::forUser($person,$now+160),'title'))];
A::archiveOne($person,$later['key']);
$later=A::forUser($person,$now+160)[array_search('Later news',array_column(A::forUser($person,$now+160),'title'))];
check($later['archived']&&!$later['unread'],'one announcement is cleared to Archived, read');
$id='9d1c2e3f-0000-4000-8000-000000000001';
A::archiveOne($person,$id,true);A::archiveOne($person,'../x',true);
check(A::readState($person)['archived_ids']===[$id]&&\Simplify\Panel\Services\PanelNotifications::$read===[$id],'one Pelican notification is archived by id and marked read');
check(A::hasArchive($person)&&!A::hasArchive($admin),'the archive is offered per account');
// Mark as unread: back out of Archived and unread; Mark read on one; Mark all read clears both.
$find=fn($title,$at)=>A::forUser($person,$at)[array_search($title,array_column(A::forUser($person,$at),'title'))];
A::markOne($person,$later['key'],true);
check(!$find('Later news',$now+160)['archived']&&$find('Later news',$now+160)['unread'],'mark as unread brings an archived announcement back, unread');
A::markOne($person,$later['key'],false);
check(!$find('Later news',$now+160)['unread'],'mark read on one');
$modpack=$find('New modpack',$now+160);A::markOne($person,$modpack['key'],true);
check($find('New modpack',$now+160)['unread'],'an unpinned (read) one can be unread again');
A::markAllRead($person,$now+170);
check(!$find('New modpack',$now+170)['unread']&&A::readState($person)['unread_keys']===[],'mark all read reads it again');
A::markPelicanUnread($person,$id);
check(A::readState($person)['archived_ids']===[]&&\Simplify\Panel\Services\PanelNotifications::$unread===[$id],'a Pelican notification is unread again and leaves Archived');
A::delete($pin['id']);
foreach(A::all() as $row)if($row['title']==='Later news')A::delete($row['id']);

// App notifications: due once live and only when asked for; marked once sent, and editing keeps that mark.
$pushed=A::save(['title'=>'Server restart','push'=>true,'starts_at'=>$now+600],null,'jera',$now);
check(!in_array($pushed['id'],array_column(A::duePush($now),'id'),true)&&in_array($pushed['id'],array_column(A::duePush($now+600),'id'),true),'a scheduled announcement is pushed when it starts');
check(!in_array($edited['id'],array_column(A::duePush($now),'id'),true),'announcements without push are never sent');
A::markPushed($pushed['id'],$now+600);
A::save(['title'=>'Server restart moved','push'=>true],$pushed['id'],'jera',$now+700);
check(A::duePush($now+800)===[]&&A::find($pushed['id'])['pushed_at']===$now+600,'sent once; editing does not send it again');
check(A::find($pushed['id'])['pushed_to']===null,'device count unknown until sending finished');
A::markPushed($pushed['id'],$now+600,0);
check(A::find($pushed['id'])['pushed_to']===0&&A::duePush($now+900)===[],'a send that reached no device is recorded and not repeated');
A::delete($pushed['id']);

// Deleting, broken files and limits.
A::delete($link['id']);check(count(A::all())===2,'delete removes it');
Storage::$records[A::FILE]='{broken';check(A::all()===[],'a broken file reads as no announcements');
Storage::$records[A::FILE]=json_encode([['id'=>'nothex','title'=>'X'],['id'=>'abcdefabcdefabcd','title'=>'Ok','button'=>['label'=>'Bad','url'=>'http://plain.example']]]);
check(count(A::all())===1&&A::all()[0]['button']===null,'damaged rows are skipped; an unsafe stored link is dropped');
Storage::$records[A::FILE]=json_encode(array_map(fn($i)=>['id'=>sprintf('%016x',$i),'title'=>'T'.$i,'created_at'=>$i],range(1,A::LIMIT)));
check(fails(fn()=>A::save(['title'=>'One more'],null,'jera',$now),'Delete an old one'),'at most '.A::LIMIT.' announcements');
check(A::ago($now-30,$now)==='just now'&&A::ago($now-120,$now)==='2 minutes ago'&&A::ago($now-3600,$now)==='1 hour ago'&&A::ago($now-86400,$now)==='yesterday'&&A::ago($now-3*86400,$now)==='3 days ago','relative times');
echo "PASS announcements: validation, tidy text, live/scheduled/ended, audiences, server and https buttons, Announcements plugin and Panel updates, hiding per account, pinned to everyone, mark all read per account, clear read to Archived, app notifications once, broken files and limits\n";
}
