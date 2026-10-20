<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use App\Models\Server;
use App\Models\User;
use App\Models\Node;
use App\Services\Servers\BuildModificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManagedResources
{
    public static function canEdit(Server $server): bool
    {
        return ManagedSettings::owns($server) && (CreatorAccess::admin() || RoleAccess::can('resources'));
    }
    public static function save(Server $server, array $state): void
    {
        abort_unless(self::canEdit($server), 403);
        ['memory' => $memory, 'cpu' => $cpu, 'disk' => $disk] = ResourceLimits::from($state);
        CreationGuard::withResourceLock(function () use ($server, $memory, $disk, $cpu): void {
        DB::transaction(function () use ($server, $memory, $disk, $cpu): void {
            User::query()->whereKey(user()->id)->lockForUpdate()->firstOrFail();
            $server->refresh();
            abort_unless(self::canEdit($server), 403);
            // Preserve every unrelated native limit: this service resets feature limits
            // if omitted from its input, so pass the actual stored values explicitly.
            $payload = ['memory' => $memory, 'disk' => $disk, 'cpu' => $cpu,
                'backup_limit' => $server->backup_limit, 'database_limit' => $server->database_limit,
                'allocation_limit' => $server->allocation_limit];
            if (!CreatorAccess::admin()) {
                $others = Server::query()->where('owner_id', user()->id)->whereKeyNot($server->id)->get(['memory','disk']);
                // An existing server consumes one slot; changing resources creates no new slot.
                $limits = CreatorAccess::settings(); $limits['max_servers'] = PHP_INT_MAX;
                // Backup slots stay unchanged and aren't part of this resource edit.
                $limits['max_backups'] = max((int) $limits['max_backups'], (int) $server->backup_limit);
                CreatorAccess::checkQuota($payload, ['count' => 0, 'memory' => $others->sum('memory'), 'disk' => $others->sum('disk')], $limits);
            }
            $node = Node::query()->whereKey($server->node_id)->lockForUpdate()->firstOrFail();
            if (!$node->isViable($payload['memory'] - $server->memory, $payload['disk'] - $server->disk, $payload['cpu'] - $server->cpu)) {
                throw ValidationException::withMessages(['data.memory_gb' => 'The hosting machine has no room for these resources.']);
            }
            app(BuildModificationService::class)->handle($server, $payload);
        });
        });
    }
}
