<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
/**
 * Automatic subdomains: a server named "oak" becomes oak.<domain>, a CNAME to the shared game address
 * (plus an SRV record for Minecraft, so Java players need no port). The Cloudflare token is entered in Simplify
 * settings and stored encrypted (never shown again); the zone ID is found from the domain. Panels that set
 * CLOUDFLARE_API_TOKEN and CLOUDFLARE_ZONE_ID in .env before keep working until a token is saved here.
 * Cloudflare is called after the response is sent; simplify:subdomains retries failures every few minutes.
 */
final class Subdomains
{
    public const FILE='simplify/subdomains.json';
    public const STATES='simplify/subdomains';
    public const RESERVED=['www','mail','smtp','imap','pop','ftp','ns1','ns2','mx','panel','node','api','admin','status','autodiscover'];
    private const DEFAULTS=['domain'=>'','target'=>'','port_min'=>25565,'port_max'=>25600,'zone_id'=>''];
    /** @var null|callable(string,string,?array):array Replaced by tests. */
    public static $transport=null;
    private static ?array $settings=null;

    public static function settings(): array
    {
        if(self::$settings!==null)return self::$settings;
        $saved=JsonFile::read(self::FILE);
        return self::$settings=[...array_replace(self::DEFAULTS,array_intersect_key($saved,self::DEFAULTS)),'has_token'=>is_string($saved['token']??null)&&$saved['token']!==''];
    }
    /** Saves the addresses; a typed token replaces the saved one, an empty token keeps it, $removeToken deletes it. */
    public static function save(array $input,bool $removeToken=false): void
    {
        $values=validator(array_intersect_key($input,self::DEFAULTS),[
            'domain'=>['nullable','string','max:200','regex:/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/'],
            'target'=>['nullable','string','max:253','regex:/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/'],
            'port_min'=>'required|integer|min:1|max:65535','port_max'=>'required|integer|min:1|max:65535|gte:port_min',
            'zone_id'=>['nullable','string','regex:/^[a-f0-9]{32}$/'],
        ],[],['domain'=>'domain','target'=>'game address','port_min'=>'first port','port_max'=>'last port','zone_id'=>__('pelican-simplify::settings.subdomains_form.zone_id')])->validate();
        $next=['domain'=>strtolower((string)($values['domain']??'')),'target'=>strtolower((string)($values['target']??'')),'port_min'=>(int)$values['port_min'],'port_max'=>(int)$values['port_max'],'zone_id'=>(string)($values['zone_id']??'')];
        $token=trim((string)($input['token']??''));
        $saved=JsonFile::read(self::FILE)['token']??'';
        if($removeToken)$saved='';
        elseif($token!=='')$saved=encrypt($token);
        if($saved!=='')$next['token']=$saved;
        if(!Storage::disk('local')->put(self::FILE,json_encode($next,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)))throw new \RuntimeException('Could not save subdomain settings.');
        self::$settings=null;
    }
    /** [token, zone ID]: the token saved in Simplify settings, otherwise the environment or .env (older setups). */
    public static function credentials(): array
    {
        $saved=JsonFile::read(self::FILE);
        if(is_string($saved['token']??null)&&$saved['token']!==''){
            try{return [(string)decrypt($saved['token']),(string)($saved['zone_id']??'')];}catch(\Throwable $e){report($e);return ['',''];}
        }
        static $file=null;
        $read=function(string $key)use(&$file): string {
            $value=(string)(env($key)??'');
            if($value!=='')return $value;
            if($file===null){$file=[];$path=base_path('.env');if(is_readable($path))foreach(file($path,FILE_IGNORE_NEW_LINES)?:[] as $line)if(preg_match('/^\s*(CLOUDFLARE_API_TOKEN|CLOUDFLARE_ZONE_ID)\s*=\s*"?([^"\s#]*)"?/',$line,$m))$file[$m[1]]=$m[2];}
            return $file[$key]??'';
        };
        return [$read('CLOUDFLARE_API_TOKEN'),$read('CLOUDFLARE_ZONE_ID')];
    }
    /** What is still missing before subdomains work; empty when ready. */
    public static function missing(): array
    {
        $s=self::settings();[$token,$zone]=self::credentials();
        return array_keys(array_filter(['domain'=>$s['domain']==='','target'=>$s['target']==='','token'=>$token==='','zone'=>$zone==='']));
    }
    /**
     * Checks a token and finds the zone of the domain (example.com for play.example.com too).
     * Returns ['ok'=>bool,'message'=>string,'zone_id'=>?string]; an empty token tests the saved one.
     */
    public static function test(string $token,string $domain): array
    {
        $token=trim($token);
        if($token==='')[$token]=self::credentials();
        $domain=strtolower(trim($domain));
        if($token==='')return ['ok'=>false,'message'=>__('pelican-simplify::settings.subdomains_test.token_first'),'zone_id'=>null];
        if($domain==='')return ['ok'=>false,'message'=>__('pelican-simplify::settings.subdomains_test.domain_first'),'zone_id'=>null];
        $send=self::$transport??CloudflareDns::http($token);
        try{
            $labels=explode('.',$domain);
            for($i=0;$i<count($labels)-1;$i++){
                $name=implode('.',array_slice($labels,$i));
                $result=$send('GET','/zones?name='.rawurlencode($name),null);
                if(!($result['success']??false))return ['ok'=>false,'message'=>__('pelican-simplify::settings.subdomains_test.refused',['error'=>(string)($result['errors'][0]['message']??__('pelican-simplify::settings.subdomains_test.unknown_error'))]),'zone_id'=>null];
                $zone=$result['result'][0]['id']??null;
                if(is_string($zone)&&preg_match('/^[a-f0-9]{32}$/',$zone))return ['ok'=>true,'message'=>__('pelican-simplify::settings.subdomains_test.works',['zone'=>$name]),'zone_id'=>$zone];
            }
        }catch(\Throwable $e){report($e);return ['ok'=>false,'message'=>__('pelican-simplify::settings.subdomains_test.unreachable'),'zone_id'=>null];}
        return ['ok'=>false,'message'=>__('pelican-simplify::settings.subdomains_test.cannot_see',['domain'=>$domain]),'zone_id'=>null];
    }
    public static function active(): bool { return FeatureSettings::enabled('subdomains')&&self::missing()===[]; }

    /** A free subdomain from a server name: "Oak Survival" becomes "oak-survival", or "oak-survival-2" when taken. */
    public static function suggest(string $serverName,?string $uuid=null): string
    {
        $ascii=class_exists(\Illuminate\Support\Str::class)?\Illuminate\Support\Str::ascii($serverName):$serverName;
        $base=trim(substr(trim((string)preg_replace('/[^a-z0-9]+/','-',strtolower($ascii)),'-'),0,57),'-');
        if($base==='')return '';
        for($i=1;$i<20;$i++){$name=$i===1?$base:$base.'-'.$i;if(self::check($name,$uuid)===null)return $name;}
        return '';
    }
    /** null when $name may be used by this server, otherwise the reason. */
    public static function check(string $name,?string $uuid=null): ?string
    {
        if($name==='')return null;
        if(strlen($name)>63||!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',$name))return __('pelican-simplify::maker.subdomains.use_lowercase_letters_numbers_and_hyphens');
        $s=self::settings();
        $own=str_ends_with($s['target'],'.'.$s['domain'])?substr($s['target'],0,-strlen($s['domain'])-1):'';
        if(in_array($name,self::RESERVED,true)||$name===$own)return __('pelican-simplify::maker.subdomains.this_name_is_reserved');
        foreach(Storage::disk('local')->files(self::STATES) as $path){
            $other=pathinfo($path,PATHINFO_FILENAME);
            if($other!==$uuid&&(self::read($other)['name']??'')===$name)return __('pelican-simplify::maker.subdomains.another_server_already_uses_this_name');
        }
        return null;
    }
    public static function forwarded(int $port): bool { $s=self::settings();return $port>=$s['port_min']&&$port<=$s['port_max']; }
    /** First free allocation on the node whose port is forwarded, so a new server can get its subdomain. */
    public static function allocationFor(int $nodeId): ?int
    {
        $s=self::settings();
        return \App\Models\Allocation::query()->where('node_id',$nodeId)->whereNull('server_id')->whereBetween('port',[$s['port_min'],$s['port_max']])->orderBy('port')->value('id');
    }

    public static function state(Server $server): array
    {
        $state=self::read($server->uuid);
        return ['name'=>(string)($state['name']??''),'status'=>(string)($state['status']??''),'message'=>(string)($state['message']??''),'domain'=>self::settings()['domain']];
    }
    /** The join address when the subdomain works: oak.example.com for Minecraft, oak.example.com:25570 otherwise. */
    public static function address(Server $server): ?string
    {
        if(!self::active())return null;
        $state=self::read($server->uuid);
        if(($state['status']??'')!=='active'||empty($state['name']))return null;
        $host=$state['name'].'.'.self::settings()['domain'];
        return ManagedSettings::minecraft($server)?$host:$host.':'.(int)($server->allocation?->port??0);
    }
    /** Saves the owner's choice and connects it after the response; an empty name removes the subdomain. */
    public static function request(Server $server,string $name): void
    {
        $name=strtolower(trim($name));
        if($name===''&&self::read($server->uuid)===[])return;
        if($error=self::check($name,$server->uuid))throw \Illuminate\Validation\ValidationException::withMessages(['name'=>$error]);
        Cache::lock('simplify.subdomains',10)->block(5,function()use($server,$name):void{
            if($error=self::check($name,$server->uuid))throw \Illuminate\Validation\ValidationException::withMessages(['name'=>$error]);
            self::write($server->uuid,['name'=>$name,'status'=>'pending','message'=>'']);
        });
        $uuid=$server->uuid;
        self::later(fn()=>($s=Server::with(['allocation','egg'])->where('uuid',$uuid)->first())?self::sync($s):null);
    }
    public static function sync(Server $server): string
    {
        $state=self::read($server->uuid);$name=(string)($state['name']??'');$port=(int)($server->allocation?->port??0);
        if(!self::active())return self::write($server->uuid,['name'=>$name,'status'=>'error','message'=>__('pelican-simplify::maker.subdomains.subdomains_are_not_set_up_yet')]);
        $dns=self::client();
        if($name!==''&&!self::forwarded($port)){
            $s=self::settings();$dns->remove($server->uuid);
            return self::write($server->uuid,['name'=>$name,'status'=>'error','message'=>__('pelican-simplify::maker.subdomains.not_forwarded', ['port'=>$port,'min'=>$s['port_min'],'max'=>$s['port_max']])]);
        }
        $error=$dns->sync($server->uuid,$name,$port,ManagedSettings::minecraft($server));
        if($name===''&&$error===null){Storage::disk('local')->delete(self::STATES.'/'.$server->uuid.'.json');return 'removed';}
        return self::write($server->uuid,['name'=>$name,'status'=>$error?'error':'active','message'=>(string)$error,'port'=>$port]);
    }
    /** Removes a deleted server's records; kept as "removing" for the retry command if Cloudflare fails. */
    public static function forget(string $uuid): void
    {
        if(!Storage::disk('local')->exists(self::STATES.'/'.$uuid.'.json'))return;
        if(!self::active()||self::client()->remove($uuid)!==null){self::write($uuid,['name'=>'','status'=>'removing','message'=>'']);return;}
        Storage::disk('local')->delete(self::STATES.'/'.$uuid.'.json');
    }
    /** Retries pending and failed servers, follows port changes and removes records of deleted servers. */
    public static function tick(): void
    {
        if(!self::active())return;
        $due=[];
        foreach(Storage::disk('local')->files(self::STATES) as $path){$uuid=pathinfo($path,PATHINFO_FILENAME);$due[$uuid]=self::read($uuid);}
        if(!$due)return;
        $servers=Server::with(['allocation','egg'])->whereIn('uuid',array_keys($due))->get()->keyBy('uuid');
        foreach($due as $uuid=>$state){
            $server=$servers[$uuid]??null;
            if(!$server){self::forget((string)$uuid);continue;}
            if(($state['status']??'')!=='active'||(int)($state['port']??0)!==(int)($server->allocation?->port??0))self::sync($server);
        }
    }

    private static function client(): CloudflareDns
    {
        [$token,$zone]=self::credentials();$s=self::settings();
        return new CloudflareDns($zone,self::$transport??CloudflareDns::http($token),$s['domain'],$s['target']);
    }
    private static function later(callable $work): void
    {
        $run=static function()use($work):void{try{$work();}catch(\Throwable $e){report($e);}};
        app()->runningInConsole()?$run():app()->terminating($run);
    }
    private static function read(string $uuid): array
    {
        if(!preg_match('/^[a-f0-9-]{36}$/i',$uuid))return [];
        try{$raw=Storage::disk('local')->get(self::STATES.'/'.$uuid.'.json');$data=$raw?json_decode((string)$raw,true,8,JSON_THROW_ON_ERROR):[];}
        catch(\Throwable){$data=[];}
        return is_array($data)?$data:[];
    }
    private static function write(string $uuid,array $state): string
    {
        if(!Storage::disk('local')->put(self::STATES.'/'.$uuid.'.json',json_encode($state+['updated_at'=>time()],JSON_THROW_ON_ERROR)))throw new \RuntimeException('Could not save the subdomain.');
        return (string)$state['status'];
    }
}
