<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;

final class SettingsDifference
{
    public static function properties(array $requested, array $current): array
    {
        $changed = [];
        foreach ($requested as $key => $value) {
            if (!array_key_exists($key,$current) || self::normalized($key,$value) !== self::normalized($key,$current[$key])) $changed[$key]=$value;
        }
        return $changed;
    }
    private static function normalized(string $key, mixed $value): string
    {
        $value=(string)$value;
        $choices=match($key){'difficulty'=>['peaceful','easy','normal','hard'],'gamemode'=>['survival','creative','adventure','spectator'],default=>null};
        if($choices && preg_match('/^[0-3]$/',$value))return $choices[(int)$value];
        if(in_array($key,['max-players','view-distance','spawn-protection'],true)&&is_numeric($value))return (string)(int)$value;
        if(in_array($key,['pvp','white-list','allow-flight'],true))return strtolower($value);
        return $key==='motd'?str_replace("\r\n","\n",$value):$value;
    }
    public static function resources(array $requested, object $server): bool
    {
        return (int)round((float)$requested['memory_gb']*1024) !== (int)$server->memory
            || (int)$requested['cpu'] !== (int)$server->cpu || (int)$requested['disk_mb'] !== (int)$server->disk;
    }
}
