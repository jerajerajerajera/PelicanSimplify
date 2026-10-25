<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Enums\ContainerStatus;
use App\Models\Node;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
/**
 * One request per node instead of one or two per server. Wings lists all of its servers, with state and
 * utilization, at /api/servers; the overview, host health and Pelican's own retrieveStatus() and
 * retrieveResources() caches are fed from that one list. An unreachable node costs one timeout, not one per server.
 */
final class WingsServers
{
    /** Pelican keeps a server's status and resources for 15 seconds; the node list lives as long. */
    public const TTL = 15;

    /** ['at' => fetch time, 'servers' => [uuid => ['state' => ..., 'utilization' => [...]]]], or null when the node did not answer. */
    public static function forNode(Node $node): ?array
    {
        if ($node->isUnderMaintenance()) return null;
        return Remember::value('simplify.wings-servers.'.$node->id, self::TTL, function () use ($node): ?array {
            $response = Http::daemon($node)->connectTimeout(1)->timeout(2)->get('/api/servers');
            if (!$response->successful()) return null;
            $list = $response->json();
            if (!is_array($list) || !array_is_list($list)) return null;
            $servers = [];
            foreach ($list as $index => $entry) {
                $uuid = $entry['configuration']['uuid'] ?? $entry['uuid'] ?? null;
                $servers[is_string($uuid) ? $uuid : '#'.$index] = ['state' => $entry['state'] ?? null, 'utilization' => is_array($entry['utilization'] ?? null) ? $entry['utilization'] : []];
            }
            return ['at' => microtime(true), 'servers' => $servers];
        }, self::TTL);
    }

    /** Fills Pelican's per-server status and resource caches for these servers from their nodes' lists. */
    public static function warm(iterable $servers): void
    {
        $byNode = [];
        foreach ($servers as $server) if ($server->node) $byNode[(int) $server->node_id][] = $server;
        foreach ($byNode as $group) {
            $snapshot = self::forNode($group[0]->node);
            if ($snapshot === null) continue;
            $ttl = max(1, self::TTL - (int) (microtime(true) - (float) $snapshot['at']));
            foreach ($group as $server) {
                $entry = $snapshot['servers'][$server->uuid] ?? null;
                if ($entry === null) continue; // Unknown to Wings: Pelican asks about it itself.
                Cache::put("servers.$server->uuid.status", ContainerStatus::tryFrom((string) $entry['state']) ?? ContainerStatus::Missing, $ttl);
                Cache::put("servers.$server->uuid.resources", $entry['utilization'], $ttl);
            }
        }
    }
}
