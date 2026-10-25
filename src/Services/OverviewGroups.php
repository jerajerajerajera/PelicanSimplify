<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Storage;
/**
 * Server groups and server order a user picks under Server groups, saved per account so every device
 * shows the same. A server sits in one group at most; null means this account never saved groups (the
 * browser then hands over the groups it kept itself before 0.5.0-snapshot.13).
 */
final class OverviewGroups
{
    public const DIR='simplify/overview-groups/';
    public const LIMIT=30;
    public const NAME_LENGTH=40;
    private const SERVERS=500;
    public const ORDERS=['name','created','port'];
    public const GROUP_ORDERS=['overview','manual','name','created','port'];

    public static function get(object $user): ?array
    {
        $path=self::path($user);
        if(!Storage::disk('local')->exists($path))return null;
        $saved=JsonFile::read($path); // A damaged file reads as no groups.
        // Up to 0.5.0-snapshot.13 the file held only the list of groups.
        return array_is_list($saved)?['groups'=>self::clean($saved),'order'=>'name']:['groups'=>self::clean(is_array($saved['groups']??null)?$saved['groups']:[]),'order'=>self::order($saved['order']??null)];
    }

    public static function save(object $user,array $groups,?string $order='name'): array
    {
        $data=['groups'=>self::clean($groups),'order'=>self::order($order)];
        if(!Storage::disk('local')->put(self::path($user),json_encode($data,JSON_THROW_ON_ERROR)))throw new \RuntimeException('Could not save your server groups.');
        return $data;
    }

    private static function order(mixed $order): string
    {
        return in_array($order,self::ORDERS,true)?$order:'name';
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
            $groups[]=['id'=>$group['id'],'name'=>$name,'servers'=>$servers,'sort'=>in_array($group['sort']??null,self::GROUP_ORDERS,true)?$group['sort']:'overview'];
        }
        return $groups;
    }

    private static function path(object $user): string
    {
        return self::DIR.((int)$user->id).'.json';
    }
}
