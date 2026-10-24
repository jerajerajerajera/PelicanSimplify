<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Storage;
/**
 * Server groups a user makes in Customize overview, saved per account so every device shows the same.
 * A server sits in one group at most; null means this account never saved groups (the browser then
 * hands over the groups it kept itself before 0.5.0-snapshot.13).
 */
final class OverviewGroups
{
    public const DIR='simplify/overview-groups/';
    public const LIMIT=30;
    public const NAME_LENGTH=40;
    private const SERVERS=500;

    public static function get(object $user): ?array
    {
        $disk=Storage::disk('local');
        $path=self::path($user);
        if(!$disk->exists($path))return null;
        try{$saved=json_decode((string)$disk->get($path),true,8,JSON_THROW_ON_ERROR);}
        catch(\Throwable $e){report($e);return [];}
        return self::clean(is_array($saved)?$saved:[]);
    }

    public static function save(object $user,array $groups): array
    {
        $groups=self::clean($groups);
        if(!Storage::disk('local')->put(self::path($user),json_encode($groups,JSON_THROW_ON_ERROR)))throw new \RuntimeException('Could not save your server groups.');
        return $groups;
    }

    /** Same rules as the dashboard: unique ids, a name, at most one group per server. */
    public static function clean(array $input): array
    {
        $groups=[];$ids=[];$seen=[];
        foreach(array_values($input) as $group){
            if(count($groups)>=self::LIMIT)break;
            if(!is_array($group)||!is_string($group['id']??null)||!preg_match('/^[A-Za-z0-9_-]{1,40}$/',$group['id'])||isset($ids[$group['id']]))continue;
            $name=is_string($group['name']??null)?trim(preg_replace('/\s+/u',' ',$group['name'])??''):'';
            $name=mb_substr($name,0,self::NAME_LENGTH)?:'Group';
            $servers=[];
            foreach(is_array($group['servers']??null)?$group['servers']:[] as $uuid){
                if(count($seen)>=self::SERVERS)break;
                if(!is_string($uuid)||!preg_match('/^[A-Za-z0-9-]{1,64}$/',$uuid)||isset($seen[$uuid]))continue;
                $seen[$uuid]=true;$servers[]=$uuid;
            }
            $ids[$group['id']]=true;
            $groups[]=['id'=>$group['id'],'name'=>$name,'servers'=>$servers];
        }
        return $groups;
    }

    private static function path(object $user): string
    {
        return self::DIR.((int)$user->id).'.json';
    }
}
