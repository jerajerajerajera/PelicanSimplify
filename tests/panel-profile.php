<?php
declare(strict_types=1);
namespace Illuminate\Support\Facades {
 class Storage {public static array $files=[];public static function disk($d){return new self;}public function get($k){return self::$files[$k]??null;}public function put($k,$v){self::$files[$k]=$v;return true;}}
}
namespace {
require_once __DIR__.'/../src/Services/JsonFile.php';
require __DIR__.'/../src/Services/PanelProfile.php';
use Simplify\Panel\Services\PanelProfile as P;
function report($e){}
function check($ok,$message){if(!$ok)throw new \RuntimeException('FAIL '.$message);echo "PASS $message\n";}

check(P::type()==='friends'&&!P::hosting(),'a panel without a saved type is for you and your friends');
// Hosting defaults: customers see only their own servers, no resource editing, host mode, updates wait for the admin.
check(P::FEATURES['hosting']===['friends_mode'=>false,'user_resources'=>false,'host_mode'=>true,'auto_update_plugins'=>false],'hosting company defaults');
check(array_keys(P::FEATURES['friends'])===array_keys(P::FEATURES['hosting']),'both types set the same switches, so switching back undoes the other');

$saved=P::save(['type'=>'hosting','client_area'=>'https://billing.example.com/clientarea.php','order'=>'javascript:alert(1)','upgrade'=>'https://billing.example.com/upgrade?service={external_id}&id={uuid}','support'=>' https://discord.gg/example ','support_label'=>"Open a\x07 ticket"]);
check(P::hosting()&&$saved['client_area']==='https://billing.example.com/clientarea.php','type and client area are stored');
check($saved['order']==='','only http(s) links are kept');
check($saved['support']==='https://discord.gg/example'&&$saved['support_label']==='Open a ticket','support link trimmed, label without control characters');
check(P::url('https://billing.example.com/{external_id}/upgrade',true)!==null&&P::url('ftp://billing.example.com/{uuid}',true)===null,'an upgrade template with placeholders is accepted, other schemes are not');

$server=(object)['uuid'=>'7f3a2b1c-0000-4000-8000-000000000001','uuid_short'=>'7f3a2b1c','id'=>12,'external_id'=>'svc 88'];
check(P::upgradeUrl($server)==='https://billing.example.com/upgrade?service=svc%2088&id=7f3a2b1c-0000-4000-8000-000000000001','the upgrade link is filled in per server, encoded');
P::save(['upgrade'=>'']);
check(P::upgradeUrl($server)===null&&P::settings()['client_area']!=='','an empty upgrade link shows no button and keeps the other links');
check(P::customerLinks()===['client_area'=>'https://billing.example.com/clientarea.php','order'=>'','support'=>'https://discord.gg/example','support_label'=>'Open a ticket'],'customers get the client area, order and support links');
P::save(['support_label'=>'']);check(P::customerLinks()['support_label']==='Open a ticket','the support button has a default text');
P::save(['type'=>'friends']);
check(P::customerLinks()===null,'a panel for friends shows customers no hosting links');
check(!P::hosting()&&P::settings()['support']==='https://discord.gg/example','switching back keeps the hosting links for later');
echo "All panel profile checks passed.\n";
}
