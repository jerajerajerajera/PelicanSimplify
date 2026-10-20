<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
/**
 * Automatic subdomains: a server named "oak" becomes oak.<domain>, a CNAME to the shared game address
 * (plus an SRV record for Minecraft, so Java players need no port). The Cloudflare token and zone ID are read
 * from the panel's .env (CLOUDFLARE_API_TOKEN, CLOUDFLARE_ZONE_ID) and never stored or shown by Simplify.
 * Cloudflare is called after the response is sent; simplify:subdomains retries failures every few minutes.
 */
final class Subdomains
{
    public const FILE='simplify/subdomains.json';
    public const STATES='simplify/subdomains';
    public const RESERVED=['www','mail','smtp','imap','pop','ftp','ns1','ns2','mx','panel','node','api','admin','status','autodiscover'];
    private const DEFAULTS=['domain'=>'','target'=>'','port_min'=>25565,'port_max'=>25600];
    /** @var null|callable(string,string,?array):array Replaced by tests. */
    public static $transport=null;
    private static ?array $settings=null;

    public static function settings(): array
    {
        if(self::$settings!==null)return self::$settings;
        try{$saved=Storage::disk('local')->exists(self::FILE)?json_decode((string)Storage::disk('local')->get(self::FILE),true,8,JSON_THROW_ON_ERROR):[];}
        catch(\Throwable $e){report($e);$saved=[];}
        return self::$settings=array_replace(self::DEFAULTS,array_intersect_key(is_array($saved)?$saved:[],self::DEFAULTS));
    }
    public static function save(array $input): void
    {
        $values=validator(array_intersect_key($input,self::DEFAULTS),[
            'domain'=>['nullable','string','max:200','regex:/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/'],
            'target'=>['nullable','string','max:253','regex:/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/'],
            'port_min'=>'required|integer|min:1|max:65535','port_max'=>'required|integer|min:1|max:65535|gte:port_min',
        ],[],['domain'=>'domain','target'=>'game address','port_min'=>'first port','port_max'=>'last port'])->validate();
        $next=['domain'=>strtolower((string)($values['domain']??'')),'target'=>strtolower((string)($values['target']??'')),'port_min'=>(int)$values['port_min'],'port_max'=>(int)$values['port_max']];
        if(!Storage::disk('local')->put(self::FILE,json_encode($next,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)))throw new \RuntimeException('Could not save subdomain settings.');
        self::$settings=$next;
    }
    /** [token, zone ID] from the environment, or from .env when the configuration is cached. */
    public static function credentials(): array
    {
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
        return array_keys(array_filter(['domain'=>$s['domain']==='','game address'=>$s['target']==='','CLOUDFLARE_API_TOKEN in .env'=>$token==='','CLOUDFLARE_ZONE_ID in .env'=>$zone==='']));
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
        if(strlen($name)>63||!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',$name))return 'Use lowercase letters, numbers and hyphens (up to 63).';
        $s=self::settings();
        $own=str_ends_with($s['target'],'.'.$s['domain'])?substr($s['target'],0,-strlen($s['domain'])-1):'';
        if(in_array($name,self::RESERVED,true)||$name===$own)return 'This name is reserved.';
        foreach(Storage::disk('local')->files(self::STATES) as $path){
            $other=pathinfo($path,PATHINFO_FILENAME);
            if($other!==$uuid&&(self::read($other)['name']??'')===$name)return 'Another server already uses this name.';
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
        if(!self::active())return self::write($server->uuid,['name'=>$name,'status'=>'error','message'=>'Subdomains are not set up yet.']);
        $dns=self::client();
        if($name!==''&&!self::forwarded($port)){
            $s=self::settings();$dns->remove($server->uuid);
            return self::write($server->uuid,['name'=>$name,'status'=>'error','message'=>'Port '.$port.' is not forwarded ('.$s['port_min'].'-'.$s['port_max'].'). Ask an administrator for a port in that range.']);
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
