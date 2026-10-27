<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use App\Models\Allocation;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\ServerCreationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreationGuard
{
    public static function create(ServerCreationService $service, array $payload, array $state): Server
    {
        return self::withResourceLock(function () use ($service, $payload, $state): Server {
            if (DB::transactionLevel() !== 0) {
                throw new \RuntimeException('Server creation must start outside a database transaction.');
            }
            $payload = DB::transaction(function () use ($payload, $state): array {
            User::query()->whereKey(user()->id)->lockForUpdate()->firstOrFail();
            CreatorAccess::assertState($state);
            if (!CreatorAccess::nodes()->whereKey($payload['node_id'])->exists()) {
                throw ValidationException::withMessages(['data.node_id' => __('pelican-simplify::maker.form.you_do_not_have_access_to')]);
            }
            $node = Node::query()->whereKey($payload['node_id'])->lockForUpdate()->firstOrFail();
            if (!$node->isViable((int) $payload['memory'], (int) $payload['disk'], (int) $payload['cpu'])) {
                throw ValidationException::withMessages(['data.ram_gb' => __('pelican-simplify::maker.form.the_hosting_machine_has_no_room')]);
            }
            if (!CreatorAccess::admin() || CreatorAccess::nodes()->count() === 1) {
                $payload['allocation_id'] = Allocation::query()->where('node_id', $node->id)->whereNull('server_id')->orderBy('id')->lockForUpdate()->value('id');
            }
            $allocation = Allocation::query()->whereKey($payload['allocation_id'])->lockForUpdate()->first();
            if (!$allocation || $allocation->server_id !== null || (int) $allocation->node_id !== (int) $payload['node_id']) {
                throw ValidationException::withMessages(['data.allocation_id' => __('pelican-simplify::maker.form.this_port_is_no_longer_available')]);
            }
            if (!CreatorAccess::admin()) {
                $servers = Server::query()->where('owner_id', user()->id)->get(['memory', 'disk']);
                CreatorAccess::checkQuota($payload, ['count' => $servers->count(), 'memory' => $servers->sum('memory'), 'disk' => $servers->sum('disk')], CreatorAccess::settings());
            }
            $payload['owner_id'] = user()->id;
            return $payload;
            });
            // Native Pelican commits its server record before Wings requests it.
            // Never surround this call with our own database transaction.
            return $service->handle($payload);
        });
    }
    public static function withResourceLock(callable $callback): mixed
    {
        // This panel runs on one host. A process-held lock has no expiring lease:
        // keep SSC creation and resource edits serialized through native commit.
        $handle = @fopen(storage_path('app/ssc-resource.lock'), 'c');
        if ($handle === false) {
            throw new \RuntimeException('Could not open the SSC resource lock. Check storage permissions.');
        }
        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                throw ValidationException::withMessages(['data.ram_gb' => __('pelican-simplify::maker.form.another_resource_change_is_in_progress')]);
            }
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
