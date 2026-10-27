<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
/**
 * The "Website embed" add-on: a public, read-only list of the servers whose owner chose "Show on website", for a
 * server list on any website (embed.js or an iframe). Only what a visitor needs is published: name, game, modpack,
 * status, players and a public join address. Never IDs, owners, nodes or private (LAN, Tailscale) addresses.
 */
final class WebsiteEmbed
{
    public const FILE='simplify/website-embed.json';
    public const CACHE='simplify.embed.servers';
    public const TTL=60;
    /** At most this many servers are published, so one request never walks a whole host. */
    public const LIMIT=100;
    private const DEFAULTS=['style'=>'auto','accent'=>'','corners'=>'round','players'=>true,'modpack'=>true,'join'=>true,'title'=>''];

    public static function enabled(): bool { return FeatureSettings::enabled('website_embed'); }
    public static function settings(): array
    {
        $saved=JsonFile::read(self::FILE);
        return self::normalize(is_array($saved)?$saved:[]);
    }
    public static function normalize(array $input): array
    {
        $out=self::DEFAULTS;
        if(in_array($input['style']??null,['auto','light','dark'],true))$out['style']=$input['style'];
        if(in_array($input['corners']??null,['round','square'],true))$out['corners']=$input['corners'];
        if(is_string($input['accent']??null)&&preg_match('/^#[0-9a-fA-F]{6}$/',$input['accent']))$out['accent']=strtolower($input['accent']);
        foreach(['players','modpack','join'] as $key)if(array_key_exists($key,$input))$out[$key]=(bool)$input[$key];
        if(is_string($input['title']??null))$out['title']=mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u','',$input['title'])??''),0,80);
        return $out;
    }
    public static function save(array $input): void
    {
        if(!Storage::disk('local')->put(self::FILE,json_encode(self::normalize($input),JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)))throw new \RuntimeException('Could not save the website embed settings.');
        self::refresh();
    }
    public static function refresh(): void { Cache::forget(self::CACHE); }

    /** Simplify's own "Show on website" choice, used while the website plugin (server-directory) is not installed. */
    public static function visible(Server $server): bool { return (ServerMetadata::read($server)['show_on_website']??false)===true; }
    public static function setVisible(Server $server,bool $visible): void
    {
        ServerMetadata::save($server,['show_on_website'=>$visible]);
        self::refresh();
    }

    /** What embed.js and the iframe receive. Cached for a minute, so visitors never reach Wings or a game server. */
    public static function payload(): array
    {
        $servers=Cache::remember(self::CACHE,self::TTL,fn():array=>self::collect());
        $options=self::settings();
        // Without its own colour the list takes the panel's accent, unless that is still the neutral default.
        if($options['accent']===''){$panel=AppearanceSettings::all()['accent_color']??'';$options['accent']=$panel!==''&&$panel!==AppearanceSettings::defaults()['accent_color']?$panel:'#0d9488';}
        return ['options'=>$options,'servers'=>$servers,'name'=>(string)config('app.name','Pelican')];
    }
    private static function collect(): array
    {
        $candidates=Server::query()->with(['allocation','egg','node'])->orderBy('name')->limit(500)->get()
            ->filter(fn(Server $s)=>self::shown($s))->take(self::LIMIT)->values();
        WingsServers::warm($candidates);
        $out=[];
        foreach($candidates as $s){try{$out[]=self::entry($s);}catch(\Throwable $e){report($e);}}
        return $out;
    }
    private static function shown(Server $s): bool
    {
        try{return WebsiteListing::visible($s);}catch(\Throwable $e){report($e);return false;}
    }
    /** One server as visitors see it. */
    public static function entry(Server $s): array
    {
        $meta=ManagedSettings::content($s);$minecraft=ManagedSettings::minecraft($s);
        $setup=(string)$s->status?->value;
        $state=$setup!==''?(str_contains($setup,'install')?'installing':'offline'):match((string)$s->retrieveStatus()->value){'running'=>'online','starting'=>'starting','stopping'=>'stopping',default=>'offline'};
        $status=$minecraft&&$state==='online'?MinecraftStatus::get($s):[];
        $pack=null;
        if(!empty($meta['modpack_name'])){
            $pack=['name'=>mb_substr((string)$meta['modpack_name'],0,80),'version'=>mb_substr((string)($meta['modpack_version']??''),0,40),
                'url'=>ModpackCatalog::safeUrl($meta['modpack_url']??null),'icon'=>ModpackCatalog::safeUrl($meta['modpack_icon_url']??null)];
        }
        $alias=(string)(ServerMetadata::read($s)['server_alias']??'');
        return [
            'id'=>(string)$s->uuid_short,'name'=>mb_substr($alias!==''?$alias:(string)$s->name,0,80),
            'game'=>$minecraft?'Minecraft':mb_substr((string)($s->egg?->name??'Game'),0,60),'software'=>$minecraft&&!$pack?mb_substr((string)$s->egg?->name,0,60):null,
            'version'=>mb_substr((string)($status['version']??''),0,40),'modpack'=>$pack,'state'=>$state,
            'players'=>isset($status['players'])?(int)$status['players']:null,'max_players'=>isset($status['max_players'])?(int)$status['max_players']:null,
            'motd'=>mb_substr((string)($status['motd']??''),0,200),'address'=>self::address($s),
            'icon'=>$pack['icon']??ModpackCatalog::safeUrl($meta['icon_url']??null)??ModpackCatalog::safeUrl($s->egg?->icon??null),
        ];
    }
    /** The first address players outside the network can reach: subdomain, the forwarded game address, then the allocation. */
    public static function address(Server $s): ?string
    {
        $port=(int)($s->allocation?->port??0);
        $candidates=[];
        if(class_exists(Subdomains::class)){
            $candidates[]=(string)(Subdomains::address($s)??'');
            $target=(string)(Subdomains::settings()['target']??'');
            if($target!==''&&$port&&Subdomains::forwarded($port))$candidates[]=$target.':'.$port;
        }
        foreach([$s->allocation?->ip_alias,$s->allocation?->ip] as $host){
            $host=trim((string)$host);
            if($host!==''&&$port)$candidates[]=(str_contains($host,':')?'['.$host.']':$host).':'.$port;
        }
        foreach($candidates as $candidate)if($candidate!==''&&self::reachable($candidate))return $candidate;
        return null;
    }
    /** False for addresses players outside the network cannot reach: private, CGNAT/Tailscale, loopback, wildcard, .ts.net, .local. */
    public static function reachable(string $address): bool
    {
        $host=preg_match('/^\[([^\]]+)\]/',$address,$m)?$m[1]:(substr_count($address,':')>1?$address:explode(':',$address)[0]);
        $host=strtolower(trim($host));
        if($host===''||$host==='localhost'||str_ends_with($host,'.ts.net')||str_ends_with($host,'.local')||str_ends_with($host,'.internal'))return false;
        if(!filter_var($host,FILTER_VALIDATE_IP))return str_contains($host,'.');
        if(!filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return false;
        // 100.64.0.0/10 (carrier-grade NAT, used by Tailscale) is not covered by the flags above.
        if(filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)){$n=ip2long($host);return !($n>=ip2long('100.64.0.0')&&$n<=ip2long('100.127.255.255'));}
        return !str_starts_with($host,'fd7a:115c:a1e0');
    }
    /** The lines to paste into a website; $origin is the panel's address. */
    public static function snippet(string $origin): array
    {
        $origin=rtrim($origin,'/');
        return ['script'=>'<div id="simplify-servers"></div>'."\n".'<script src="'.$origin.'/simplify/embed.js" defer></script>',
            'iframe'=>'<iframe src="'.$origin.'/simplify/embed" title="Game servers" style="width:100%;height:420px;border:0" loading="lazy"></iframe>'];
    }
}
