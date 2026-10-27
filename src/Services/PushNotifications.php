<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
/**
 * App notifications: the devices each account turned notifications on for (installed app or browser), what the
 * account wants to get, and sending through the browsers' push services (WebPush). One small file per account.
 */
final class PushNotifications
{
    public const KEYS='simplify/push/keys.json';
    public const DIR='simplify/push/accounts/';
    public const DEVICE_LIMIT=10;
    /** Per device, the keys of the last messages it received once (announcements), so none arrives twice. */
    public const RECEIVED_LIMIT=100;
    /** Kinds of notifications an account can turn off; updates only reach root admins. */
    public const KINDS=['announcements'=>true,'updates'=>true];

    /** The panel's public VAPID key (made once), which the browser needs to subscribe. */
    public static function publicKey(): string { return self::keys()['public']; }

    /** This account's devices (the one with $endpoint marked as this device) and choices. */
    public static function settings(object $user,?string $endpoint=null): array
    {
        $data=self::read($user);$current=$endpoint?self::id($endpoint):null;
        return ['preferences'=>$data['preferences'],'devices'=>array_map(fn(array $d):array=>['id'=>$d['id'],'label'=>$d['label'],'created_at'=>$d['created_at'],'last_sent_at'=>$d['last_sent_at'],'current'=>$d['id']===$current],$data['devices'])];
    }

    /** Stores a browser subscription (PushSubscription.toJSON()) for this account; the same browser replaces its old entry. */
    public static function subscribe(object $user,array $subscription,string $label,?int $now=null): array
    {
        $endpoint=is_string($subscription['endpoint']??null)?$subscription['endpoint']:'';
        $p256dh=is_string($subscription['keys']['p256dh']??null)?$subscription['keys']['p256dh']:'';
        $auth=is_string($subscription['keys']['auth']??null)?$subscription['keys']['auth']:'';
        if(!WebPush::endpointAllowed($endpoint))throw new \InvalidArgumentException('This browser uses a push service the panel does not send to.');
        if(!WebPush::keysValid($p256dh,$auth))throw new \InvalidArgumentException('The browser sent invalid notification keys.');
        $data=self::read($user);$id=self::id($endpoint);
        // The same browser keeps what it already received.
        $received=current(array_filter($data['devices'],fn(array $d):bool=>$d['id']===$id))['received']??[];
        $data['devices']=array_values(array_filter($data['devices'],fn(array $d):bool=>$d['id']!==$id));
        array_unshift($data['devices'],['id'=>$id,'endpoint'=>$endpoint,'p256dh'=>$p256dh,'auth'=>$auth,'label'=>self::label($label),'created_at'=>$now??time(),'last_sent_at'=>null,'received'=>$received]);
        $data['devices']=array_slice($data['devices'],0,self::DEVICE_LIMIT);
        self::write($user,$data);
        return self::settings($user,$endpoint);
    }

    /** Removes a device by its id (or its endpoint, for "turn off on this device"). */
    public static function remove(object $user,string $idOrEndpoint): array
    {
        $id=str_starts_with($idOrEndpoint,'https://')?self::id($idOrEndpoint):$idOrEndpoint;
        $data=self::read($user);
        $data['devices']=array_values(array_filter($data['devices'],fn(array $d):bool=>$d['id']!==$id));
        self::write($user,$data);
        return self::settings($user);
    }

    public static function savePreferences(object $user,array $preferences): array
    {
        $data=self::read($user);
        foreach(self::KINDS as $kind=>$default)if(array_key_exists($kind,$preferences))$data['preferences'][$kind]=(bool)$preferences[$kind];
        self::write($user,$data);
        return $data['preferences'];
    }

    /** Sends a test notification to this device only. */
    public static function test(object $user,string $endpoint): array
    {
        $id=self::id($endpoint);
        $device=current(array_filter(self::read($user)['devices'],fn(array $d):bool=>$d['id']===$id));
        if(!$device)return ['ok'=>false,'message'=>'Notifications are not on for this device yet.'];
        $result=self::deliver($user,$device,['title'=>'Test notification','body'=>'Notifications work on this device.','url'=>self::home(),'tag'=>'simplify-test']);
        return match($result){
            'sent'=>['ok'=>true,'message'=>'Test sent. It should appear within a few seconds.'],
            'gone'=>['ok'=>false,'message'=>'The browser no longer accepts notifications for this device. Turn them on again.'],
            default=>['ok'=>false,'message'=>'The push service did not accept the test. Try again in a minute.'],
        };
    }

    /**
     * Sends to every device of this account that wants this kind; returns how many accepted it.
     * With $once, a device that already received that key is skipped, and the key is remembered after it accepts.
     */
    public static function send(object $user,string $kind,array $message,?string $once=null): int
    {
        $data=self::read($user);
        if(!($data['preferences'][$kind]??false))return 0;
        $sent=0;
        foreach($data['devices'] as $device){
            if($once!==null&&in_array($once,$device['received'],true))continue;
            if(self::deliver($user,$device,$message,$once)==='sent')$sent++;
        }
        return $sent;
    }

    /** True when Pelican's scheduler has not run for five minutes: the test still arrives, real notifications do not. */
    public static function schedulerStalled(?int $now=null): bool
    {
        $last=max(array_map(fn(string $key):int=>(int)Cache::get($key,0),['simplify.announcements-at','simplify.plugin-check-at','simplify.panel-updates-at']));
        return $last<($now??time())-300;
    }

    /** Account ids that have at least one device. */
    public static function accounts(): array
    {
        $ids=[];
        foreach(Storage::disk('local')->files(rtrim(self::DIR,'/')) as $path){
            if(preg_match('#/(\d+)\.json$#',$path,$m))$ids[]=(int)$m[1];
        }
        return $ids;
    }

    /** "sent", "gone" (the subscription expired; it is removed) or "failed". */
    private static function deliver(object $user,array $device,array $message,?string $once=null): string
    {
        try{
            $request=WebPush::request($device,json_encode($message,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),self::keys(),self::subject());
            $response=Http::timeout(8)->connectTimeout(4)->withHeaders($request['headers'])->withBody($request['body'],'application/octet-stream')->post($request['url']);
            $status=$response->status();
        }catch(\Throwable $e){
            report($e);
            return 'failed';
        }
        if(in_array($status,[404,410],true)){self::remove($user,$device['id']);return 'gone';}
        if($status<200||$status>=300)return 'failed';
        $data=self::read($user);
        foreach($data['devices'] as &$known)if($known['id']===$device['id']){
            $known['last_sent_at']=time();
            if($once!==null)$known['received']=array_slice([$once,...array_diff($known['received'],[$once])],0,self::RECEIVED_LIMIT);
        }
        unset($known);
        self::write($user,$data);
        return 'sent';
    }

    /** A contact for the push services, required by VAPID: the panel's https address, else its mail sender. */
    private static function subject(): string
    {
        $url=rtrim((string)url('/'),'/');
        if(str_starts_with($url,'https://'))return $url;
        $mail=(string)config('mail.from.address');
        return 'mailto:'.($mail!==''?$mail:'admin@localhost');
    }

    private static function home(): string
    {
        try{return \Simplify\Panel\Filament\App\Pages\Dashboard::getUrl(panel:'app');}catch(\Throwable){return url('/');}
    }

    private static function keys(): array
    {
        $saved=JsonFile::read(self::KEYS);
        if(is_string($saved['public']??null)&&is_string($saved['private']??null)){
            try{return ['public'=>$saved['public'],'private'=>decrypt($saved['private'])];}catch(\Throwable $e){report($e);}
        }
        $keys=WebPush::createKeys();
        // A new key pair means every browser has to subscribe again; the old devices are useless now.
        if(!Storage::disk('local')->put(self::KEYS,json_encode(['public'=>$keys['public'],'private'=>encrypt($keys['private'])],JSON_THROW_ON_ERROR)))throw new \RuntimeException('Could not save the keys for app notifications.');
        return $keys;
    }

    private static function read(object $user): array
    {
        $raw=JsonFile::read(self::DIR.(int)$user->id.'.json');
        $devices=[];
        foreach(is_array($raw['devices']??null)?$raw['devices']:[] as $d){
            if(!is_array($d)||!is_string($d['endpoint']??null)||!WebPush::endpointAllowed($d['endpoint'])||!is_string($d['p256dh']??null)||!is_string($d['auth']??null))continue;
            $devices[]=['id'=>self::id($d['endpoint']),'endpoint'=>$d['endpoint'],'p256dh'=>$d['p256dh'],'auth'=>$d['auth'],'label'=>self::label($d['label']??''),
                'created_at'=>is_int($d['created_at']??null)?$d['created_at']:0,'last_sent_at'=>is_int($d['last_sent_at']??null)?$d['last_sent_at']:null,
                'received'=>array_slice(array_values(array_filter(is_array($d['received']??null)?$d['received']:[],fn($k):bool=>is_string($k)&&strlen($k)<=80)),0,self::RECEIVED_LIMIT)];
        }
        $preferences=[];
        foreach(self::KINDS as $kind=>$default)$preferences[$kind]=is_bool($raw['preferences'][$kind]??null)?$raw['preferences'][$kind]:$default;
        return ['devices'=>array_slice($devices,0,self::DEVICE_LIMIT),'preferences'=>$preferences];
    }

    private static function write(object $user,array $data): void
    {
        if(!Storage::disk('local')->put(self::DIR.(int)$user->id.'.json',json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)))throw new \RuntimeException('Could not save your notification settings.');
    }

    private static function id(string $endpoint): string { return substr(hash('sha256',$endpoint),0,16); }

    private static function label(mixed $label): string
    {
        $label=is_string($label)?trim((string)preg_replace('/\s+/u',' ',strip_tags($label))):'';
        return mb_substr($label,0,60)?:'Browser';
    }
}
