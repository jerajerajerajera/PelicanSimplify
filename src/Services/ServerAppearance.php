<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Server;
use App\Enums\SubuserPermission as P;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
final class ServerAppearance
{
    public static function color(Server $server): ?string
    {
        $color=JsonFile::read('simplify/appearance/'.$server->uuid.'.json')['color']??null;
        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/',$color) ? strtolower($color) : null;
    }

    public static function defaultColor(): string
    {
        $choices=self::choices();
        if($choices)return array_key_first($choices);
        return '#'.bin2hex(random_bytes(3));
    }
    /** Rank suggestions by their hue distance from existing servers and each other. */
    public static function choices(?string $exceptUuid=null): array
    {
        $used=[];$hues=[];
        foreach (Server::query()->get(['uuid']) as $server) {
            if ($server->uuid===$exceptUuid) continue;
            $color=self::color($server);
            if ($color!==null) {$used[$color]=true;$hues[]=self::hue($color);continue;}
            $hash=2166136261;
            foreach (str_split($server->uuid) as $char) $hash=(($hash ^ ord($char))*16777619)&0xffffffff;
            $hues[]=$hash%360;
        }
        $candidates=[];
        $names=['Red','Orange','Gold','Lime','Green','Mint','Cyan','Blue','Indigo','Purple','Pink','Rose'];
        for ($h=0;$h<360;$h+=10) {
            $hex=self::hueColor($h);
            if (!isset($used[$hex])) $candidates[$hex]=['hue'=>$h,'name'=>$names[(int)floor(($h+15)/30)%12]];
        }
        $suggestions=[];
        // Greedily spread six suggestions over the largest unused gaps.
        while ($candidates && count($suggestions)<6) {
            $best=null;$bestDistance=-1;
            foreach ($candidates as $hex=>$candidate) {
                $distance=180;
                foreach ($hues as $taken) { $delta=abs($candidate['hue']-$taken);$distance=min($distance,$delta,360-$delta); }
                if ($distance>$bestDistance) {$best=$hex;$bestDistance=$distance;}
            }
            $suggestions[$best]=$candidates[$best]['name'];
            $hues[]=$candidates[$best]['hue'];unset($candidates[$best]);
        }
        return $suggestions;
    }
    private static function hue(string $hex): float
    {
        $rgb=array_map(fn($i)=>hexdec(substr($hex,$i,2))/255,[1,3,5]);
        [$r,$g,$b]=$rgb;$max=max($rgb);$delta=$max-min($rgb);
        $h=$delta==0?0:($max==$r?60*fmod(($g-$b)/$delta,6):($max==$g?60*(($b-$r)/$delta+2):60*(($r-$g)/$delta+4)));
        return fmod($h+360,360);
    }
    private static function hueColor(int $hue): string
    {
        $channel=function(int $n) use($hue): string {
            // Pastel suggestions: light, softly saturated. Custom colours stay free.
            $k=fmod($n+$hue/30,12);$l=.76;$a=.68*min($l,1-$l);
            return str_pad(dechex((int)round(255*($l-$a*max(-1,min($k-3,9-$k,1))))),2,'0',STR_PAD_LEFT);
        };
        return '#'.$channel(0).$channel(8).$channel(4);
    }
    public static function save(Server $server,string $color): string
    {
        Access::permission($server,P::SettingsRename);
        $data=validator(['color'=>$color],['color'=>'required|regex:/^#[0-9a-fA-F]{6}$/'])->validate();
        $color=strtolower($data['color']);
        Cache::lock('simplify.server-color.'.$server->uuid,10)->block(3,function()use($server,$color):void{
            if (!Storage::disk('local')->put('simplify/appearance/'.$server->uuid.'.json',json_encode(['color'=>$color],JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save server color.');
        });
        return $color;
    }
    public static function assignMissingColors(): void
    {
        abort_unless(user()?->isRootAdmin(),403);
        foreach (Server::query()->orderBy('uuid')->get() as $server) {
            Cache::lock('simplify.server-color.'.$server->uuid,10)->block(3,function()use($server):void{
                if (self::color($server) !== null) return;
                $choices = self::choices($server->uuid);
                $color = $choices ? array_key_first($choices) : '#'.bin2hex(random_bytes(3));
                if (!Storage::disk('local')->put('simplify/appearance/'.$server->uuid.'.json',json_encode(['color'=>$color],JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not assign server color.');
            });
        }
    }
}
