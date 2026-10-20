<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Server;
final class MinecraftStatus
{
    private static function vi(int $n): string { $b='';do{$c=$n&127;$n>>=7;$b.=chr($c|($n?128:0));}while($n);return $b; }
    private static function read($socket,int $n): string
    {
        $s='';while(strlen($s)<$n){$chunk=fread($socket,$n-strlen($s));if($chunk===false||$chunk==='')throw new \RuntimeException('Incomplete Minecraft status');$s.=$chunk;}return $s;
    }
    private static function num($socket): int { $v=0;for($i=0;$i<5;$i++){$b=ord(self::read($socket,1));$v|=($b&127)<<($i*7);if(!($b&128))return$v;}throw new \RuntimeException('Invalid status length'); }
    public static function get(Server $s): array
    {
        if(!ManagedSettings::minecraft($s))return[];
        $ttl=max(10,(int)PerformanceSettings::get('overview_seconds')-2);
        return \Illuminate\Support\Facades\Cache::remember('simplify.mcstatus.'.$s->uuid,$ttl,function()use($s):array {
            $port=(int)$s->allocation?->port;$socket=null;
            foreach($port?self::targets($s->allocation?->ip,$s->allocation?->ip_alias,(string)parse_url($s->node->getConnectionAddress(),PHP_URL_HOST)):[] as $host){
                $socket=@stream_socket_client('tcp://'.(str_contains($host,':')?'['.$host.']':$host).':'.$port,$errno,$error,0.7);
                if($socket)break;
            }
            if(!$socket)return[];
            try {
                stream_set_timeout($socket,1);
                $handshake=self::vi(0).self::vi(47).self::vi(strlen($host)).$host.pack('n',$port).self::vi(1);
                fwrite($socket,self::vi(strlen($handshake)).$handshake."\x01\x00");
                $len=self::num($socket);if($len>2097152||$len<1)return[];
                if(self::num($socket)!==0)return[];
                $jsonLen=self::num($socket);if($jsonLen>2097152||$jsonLen<1)return[];
                $data=json_decode(self::read($socket,$jsonLen),true,512,JSON_THROW_ON_ERROR);
                $motd=mb_substr(MinecraftMotd::legacy($data['description']??''),0,2048);
                \Illuminate\Support\Facades\Cache::put('simplify.public-motd.'.$s->uuid,$motd,86400);
                $favicon=$data['favicon']??'';
                if(is_string($favicon)&&str_starts_with($favicon,'data:image/png;base64,')&&strlen($favicon)<=150000)\Illuminate\Support\Facades\Cache::put('simplify.mcstatus-icon.'.$s->uuid,$favicon,86400);
                return ['motd'=>$motd,'online'=>true,'players'=>$data['players']['online']??0,'max_players'=>$data['players']['max']??0,
                    'names'=>array_values(array_filter($data['players']['sample']??[],fn($p)=>preg_match('/^[A-Za-z0-9_]{1,16}$/',$p['name']??''))),
                    'version'=>$data['version']['name']??''];
            }catch(\Throwable){return[];}finally{fclose($socket);}
        });
    }
    /**
     * Hosts to ping, in order. A wildcard allocation (0.0.0.0/::) has no address of its own: try loopback first
     * (panel and Wings on one machine, fastest), then its alias (the public address players use), then the node
     * FQDN, which may point at an HTTP-only proxy such as Cloudflare when the node is behind one.
     */
    public static function targets(?string $ip, ?string $alias, string $nodeHost): array
    {
        if(!in_array($ip,['','0.0.0.0','::',null],true))return[$ip];
        $alias=trim((string)$alias);$valid=fn(string $h):bool=>$h!==''&&(filter_var($h,FILTER_VALIDATE_IP)!==false||filter_var($h,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)!==false&&str_contains($h,'.'));
        return array_values(array_unique(array_filter([$ip==='::'?'::1':'127.0.0.1',$valid($alias)?$alias:'',$valid($nodeHost)?$nodeHost:''])));
    }
    /** The favicon from the latest status ping (data URI), kept for the icon route. */
    public static function favicon(Server $s): ?string
    {
        $icon=\Illuminate\Support\Facades\Cache::get('simplify.mcstatus-icon.'.$s->uuid);
        return is_string($icon)?$icon:null;
    }
}
