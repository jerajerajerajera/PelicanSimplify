<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Server;
/**
 * "Show on website" for the companion website plugin (server-directory): through its simplify.web adapter when it
 * binds one, otherwise through its DirectoryStore. Unavailable while that plugin is not installed and enabled.
 */
final class WebsiteListing
{
    private const STORE='Deckzwabbers\\ServerDirectory\\Services\\DirectoryStore';

    public static function available(): bool { return Extensions::enabled('server-directory')&&(Extensions::web()||class_exists(self::STORE)); }
    public static function visible(Server $server): bool
    {
        $meta=Extensions::web()?Extensions::webAdapter()->metadata($server):(self::STORE)::metadata($server);
        return ($meta['visible']??false)===true;
    }
    public static function setVisible(Server $server,bool $visible): void
    {
        if(Extensions::web()){$adapter=Extensions::webAdapter();$adapter->save($server,[...$adapter->metadata($server),'visible'=>$visible]);return;}
        (self::STORE)::save($server,[...(self::STORE)::metadata($server),'visible'=>$visible]);
    }
    /** {visible} for the server page, or null when the toggle should not show. */
    public static function forPage(Server $server): ?array
    {
        if(!self::available()||!(user()?->isRootAdmin()||(int)$server->owner_id===(int)user()?->id))return null;
        try{return ['visible'=>self::visible($server)];}catch(\Throwable $e){report($e);return null;}
    }
}
