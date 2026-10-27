<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use App\Models\Server;

final class ManagedSettings
{
    public static function owns(Server $server): bool
    {
        return (bool) user() && ((int) $server->owner_id === (int) user()->id || user()->isRootAdmin());
    }
    public static function minecraft(Server $server): bool
    {
        // Bedrock has no Java server.properties, status ping or EULA file, so it is handled like any other game.
        $egg = (string) $server->egg?->name;
        return preg_match('/minecraft|vanilla|paper|purpur|spigot|modrinth|curseforge|ftb|tekkit|hexxit|blightfall|b-team|forge|fabric|quilt|pack/i', $egg) === 1
            && preg_match('/bedrock|pocketmine|nukkit/i', $egg) !== 1;
    }
    public static function content(Server $server): array
    {
        $data=ServerMetadata::read($server);
        return is_array($data['modpack']??null)?$data['modpack']:[];
    }
}
