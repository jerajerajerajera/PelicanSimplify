<?php
namespace Illuminate\Support\Facades {
class Storage {public static array $records=[];public static function disk($name){return new self;}public function get($path){return self::$records[$path]??null;}public function put($path,$value){self::$records[$path]=$value;return true;}
    public function files($dir){return array_values(array_filter(array_keys(self::$records),fn($p)=>str_starts_with($p,$dir.'/')));}}
class Http {public static array $sent=[];public static int $status=201;public static bool $throw=false;private array $headers=[];private string $body='';
    public static function timeout($s){return new self;}public function connectTimeout($s){return $this;}public function withHeaders(array $h){$this->headers=$h;return $this;}public function withBody($b,$type){$this->body=$b;return $this;}
    public function post($url){if(self::$throw)throw new \RuntimeException('down');self::$sent[]=['url'=>$url,'headers'=>$this->headers,'body'=>$this->body];$status=self::$status;return new class($status){public function __construct(private int $s){}public function status(){return $this->s;}};}}
}
namespace Simplify\Panel\Filament\App\Pages {final class Dashboard {public static function getUrl(array $parameters=[],?string $panel=null): string {return 'https://panel.example/simplify';}}}
namespace {
function report($e){}
function url($path='/'){return 'https://panel.example';}
function config($key){return null;}
function encrypt($value){return 'enc:'.base64_encode($value);}
function decrypt($value){return base64_decode(substr($value,4));}
require_once __DIR__.'/../src/Services/JsonFile.php';
require __DIR__.'/../src/Services/WebPush.php';
require __DIR__.'/../src/Services/PushNotifications.php';
use Simplify\Panel\Services\PushNotifications as P;
use Simplify\Panel\Services\WebPush;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
function check($condition,$message){if(!$condition)throw new \Exception($message);}
function fails(callable $run,string $text){try{$run();}catch(\InvalidArgumentException $e){return str_contains($e->getMessage(),$text);}return false;}
function browser(string $endpoint): array {$key=openssl_pkey_new(['curve_name'=>'prime256v1','private_key_type'=>OPENSSL_KEYTYPE_EC]);$d=openssl_pkey_get_details($key)['ec'];
    return ['endpoint'=>$endpoint,'keys'=>['p256dh'=>WebPush::b64("\x04".str_pad($d['x'],32,"\0",STR_PAD_LEFT).str_pad($d['y'],32,"\0",STR_PAD_LEFT)),'auth'=>WebPush::b64(random_bytes(16))]];}
$now=1792000000;
$jera=new class{public $id=1;};$sam=new class{public $id=2;};

// Keys: made once, private key stored encrypted, the same public key afterwards.
$public=P::publicKey();
check(strlen(WebPush::unb64($public))===65&&P::publicKey()===$public,'one VAPID key pair, reused');
check(!str_contains(Storage::$records[P::KEYS],'PRIVATE KEY'),'the private key is not stored in plain text');

// Subscribing: only browser push services and valid keys; the same browser replaces its entry.
check(fails(fn()=>P::subscribe($jera,browser('https://evil.example/push'),'Chrome',$now),'push service'),'unknown push hosts are refused');
check(fails(fn()=>P::subscribe($jera,['endpoint'=>'https://fcm.googleapis.com/fcm/send/x','keys'=>['p256dh'=>'abc','auth'=>'def']],'Chrome',$now),'invalid'),'broken keys are refused');
$phone=browser('https://fcm.googleapis.com/fcm/send/phone');$laptop=browser('https://updates.push.services.mozilla.com/wpush/v2/laptop');
P::subscribe($jera,$phone,'<b>Chrome</b> on   Android',$now);
$settings=P::subscribe($jera,$laptop,'',$now+5);
check(count($settings['devices'])===2&&$settings['devices'][0]['current']&&$settings['devices'][0]['label']==='Browser'&&$settings['devices'][1]['label']==='Chrome on Android','newest first, this device marked, labels tidied');
check(!array_key_exists('endpoint',$settings['devices'][0])&&!array_key_exists('auth',$settings['devices'][0]),'endpoints and keys never go back to the page');
P::subscribe($jera,$phone,'Chrome on Android',$now+10);
check(count(P::settings($jera)['devices'])===2,'subscribing the same browser again replaces it');
check(P::accounts()===[1],'accounts with devices');

// Preferences: both on by default, unknown kinds ignored.
check(P::settings($jera)['preferences']===['announcements'=>true,'updates'=>true],'everything on by default');
check(P::savePreferences($jera,['updates'=>false,'spam'=>true])===['announcements'=>true,'updates'=>false],'one kind turned off');

// Sending: encrypted to every device that wants it; test goes to this device only.
Http::$sent=[];
check(P::send($jera,'announcements',['title'=>'Hi','body'=>'There'])===2&&count(Http::$sent)===2,'sent to both devices');
$request=Http::$sent[0];
check(str_starts_with($request['headers']['Authorization'],'vapid t=')&&str_ends_with($request['headers']['Authorization'],'k='.$public)&&$request['headers']['Content-Encoding']==='aes128gcm'&&!str_contains($request['body'],'There'),'VAPID header and an encrypted body');
check(P::send($jera,'updates',['title'=>'Update'])===0&&count(Http::$sent)===2,'nothing for a kind that is turned off');
check(P::settings($jera)['devices'][0]['last_sent_at']!==null,'the last notification time is kept');
Http::$sent=[];
check(P::test($jera,$laptop['endpoint'])['ok']&&count(Http::$sent)===1&&Http::$sent[0]['url']===$laptop['endpoint'],'the test goes to this device only');
check(!P::test($sam,$laptop['endpoint'])['ok'],'no test for a device of someone else');
Http::$status=500;check(!P::test($jera,$laptop['endpoint'])['ok']&&count(P::settings($jera)['devices'])===2,'a failing push service keeps the device');
Http::$throw=true;check(!P::test($jera,$laptop['endpoint'])['ok'],'a network error is a failed test');Http::$throw=false;
Http::$status=410;$gone=P::test($jera,$laptop['endpoint']);
check(!$gone['ok']&&str_contains($gone['message'],'again')&&count(P::settings($jera)['devices'])===1,'an expired subscription is removed');
Http::$status=201;

// Removing by id or endpoint; damaged files read as nothing.
P::remove($jera,$phone['endpoint']);check(P::settings($jera)['devices']===[],'turned off on this device');
Storage::$records[P::DIR.'2.json']='{broken';check(P::settings($sam)['devices']===[],'a broken file reads as no devices');
Storage::$records[P::DIR.'2.json']=json_encode(['devices'=>[['endpoint'=>'https://evil.example/x','p256dh'=>'a','auth'=>'b']]]);
check(P::settings($sam)['devices']===[],'a stored endpoint outside the push services is ignored');
echo "PASS push-notifications: VAPID keys once and encrypted, push-host allowlist, devices per account, preferences, encrypted sending, test to this device, expired devices removed\n";
}
