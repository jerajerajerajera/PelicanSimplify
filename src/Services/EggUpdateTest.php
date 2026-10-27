<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use App\Enums\EggFormat;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Eggs\Sharing\EggImporterService;
use App\Services\Servers\ServerCreationService;
use App\Services\Servers\ServerDeletionService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;

/**
 * "Test first, then update": before an egg that servers use is updated, the new definition is imported as a
 * separate copy and a test server is made from it, owned by a root admin, on a temporary port above the range hosts
 * forward to players. Only when that server installs and starts (Wings reports it running once the egg's "done"
 * line shows) is the real egg updated, with exactly the tested definition. Test server, port and copy are removed
 * either way. One test runs at a time; the scheduler moves it on every minute.
 */
final class EggUpdateTest
{
    /** external_id of test servers; lists in Simplify leave them out. */
    public const MARK = 'simplify-egg-check-';
    private const CONTENT = 'simplify/egg-tests/';
    private const INSTALL_SECONDS = 15 * 60;
    private const START_SECONDS = 3 * 60;
    /** Temporary test ports: above the usual game ranges, below the ephemeral range. */
    private const PORTS = [45000, 45999];

    public static function isTest(?Server $server): bool
    {
        return $server !== null && str_starts_with((string) $server->external_id, self::MARK);
    }

    /** Leaves test servers out of a server query. */
    public static function hide($query)
    {
        return $query->where(fn ($q) => $q->whereNull('external_id')->orWhere('external_id', 'not like', self::MARK.'%'));
    }

    /** Queue a test of $egg's published update; it starts right away when no other test runs. */
    public static function start(Egg $egg): array
    {
        $state = EggUpdates::change(function (array $state) use ($egg): array {
            $state['queue'] = array_values(array_unique([...($state['queue'] ?? []), $egg->id]));
            unset($state['results'][$egg->id]);
            return $state;
        });
        if (($state['test'] ?? null) === null) self::tick();
        return ['ok' => true, 'message' => __('pelican-simplify::eggs.test.started', ['name' => $egg->name])];
    }

    /** Called every minute: moves the running test on, or starts the next one. */
    public static function tick(): array
    {
        $test = EggUpdates::state()['test'] ?? null;
        if ($test === null) return self::next();
        try {
            return self::advance($test);
        } catch (\Throwable $exception) {
            report($exception);
            return self::finish($test, false, 'followed', self::reason($exception));
        }
    }

    private static function next(): array
    {
        $id = null;
        EggUpdates::change(function (array $state) use (&$id): array {
            $id = array_shift($state['queue']);
            $state['queue'] = $state['queue'] ?? [];
            return $state;
        });
        $egg = $id ? Egg::query()->find($id) : null;
        if (!$egg) return [];
        try {
            $test = self::begin($egg);
        } catch (\Throwable $exception) {
            report($exception);
            $reason = self::reason($exception);
            EggUpdates::change(function (array $state) use ($egg, $reason): array {
                $state['results'][$egg->id] = ['ok' => false, 'code' => 'made', 'detail' => $reason, 'at' => time()];
                return $state;
            });
            EggUpdates::notify(fn () => [__('pelican-simplify::eggs.notify.not_tested_title', ['name' => $egg->name]), __('pelican-simplify::eggs.notify.not_tested_body', ['reason' => $reason])], 'warning');
            // @english: scheduler output
            return [$egg->name.': no test server: '.$reason];
        }
        EggUpdates::change(function (array $state) use ($test): array {
            $state['test'] = $test;
            return $state;
        });
        // @english
        return [$egg->name.': test server '.$test['server'].' is installing.'];
    }

    /** Copy of the new definition, a temporary port and the test server. */
    private static function begin(Egg $egg): array
    {
        $remote = EggUpdates::remote($egg);
        $definition = $remote['format'] === EggFormat::YAML ? Yaml::parse($remote['content']) : json_decode($remote['content'], true);
        if (!is_array($definition)) throw new \RuntimeException('the update address does not return an egg.');
        $path = self::CONTENT.$egg->id.'.json';
        if (!Storage::disk('local')->put($path, json_encode(['content' => $remote['content'], 'format' => $remote['format']->name, 'hash' => $remote['hash']], JSON_THROW_ON_ERROR))) throw new \RuntimeException('could not keep the tested definition.');
        // A fresh uuid and an explicit new model, so the import never touches the real egg.
        unset($definition['uuid']);
        $definition['name'] = $egg->name.' (update check)';
        $copy = app(EggImporterService::class)->fromContent(json_encode($definition, JSON_THROW_ON_ERROR), EggFormat::JSON, new Egg());
        $allocation = null;
        try {
            $owner = User::query()->get()->first(fn (User $user) => $user->isRootAdmin());
            if (!$owner) throw new \RuntimeException('there is no root admin to own the test server.');
            $node = Node::query()->get()->first(fn (Node $node) => !$node->isUnderMaintenance() && $node->isViable(2048, 8192, 200));
            if (!$node) throw new \RuntimeException('no node has room for a test server (2 GB RAM, 8 GB disk).');
            $allocation = self::allocation($node);
            $server = app(ServerCreationService::class)->handle([
                'external_id' => self::MARK.$egg->id.'-'.time(),
                'name' => __('pelican-simplify::eggs.test.server_name', ['name' => $egg->name]),
                'description' => __('pelican-simplify::eggs.test.server_description'),
                'owner_id' => $owner->id, 'node_id' => $node->id, 'allocation_id' => $allocation->id, 'egg_id' => $copy->id,
                'environment' => OtherGameCatalog::environment($copy, []),
                'memory' => 2048, 'swap' => 0, 'disk' => 8192, 'io' => 500, 'cpu' => 200,
                'database_limit' => 0, 'allocation_limit' => 0, 'backup_limit' => 0,
                'oom_killer' => false, 'skip_scripts' => false, 'start_on_completion' => false,
                'image' => collect($copy->docker_images)->first(), 'startup' => collect($copy->startup_commands)->first(),
            ]);
        } catch (\Throwable $exception) {
            $allocation?->delete();
            $copy->delete();
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        return ['egg' => $egg->id, 'copy' => $copy->id, 'server' => $server->id, 'allocation' => $allocation->id, 'stage' => 'installing', 'since' => time(), 'started' => time()];
    }

    /** A free port in the test range on the node's first address, made for this test only. */
    private static function allocation(Node $node): Allocation
    {
        $ip = Allocation::query()->where('node_id', $node->id)->orderBy('id')->value('ip') ?? '0.0.0.0';
        $taken = Allocation::query()->where('node_id', $node->id)->where('ip', $ip)->whereBetween('port', self::PORTS)->pluck('port')->all();
        foreach (range(self::PORTS[1], self::PORTS[0]) as $port) {
            if (!in_array($port, $taken, true)) return Allocation::query()->create(['node_id' => $node->id, 'ip' => $ip, 'port' => $port]);
        }
        throw new \RuntimeException('no free test port between '.self::PORTS[0].' and '.self::PORTS[1].'.');
    }

    private static function advance(array $test): array
    {
        $server = Server::query()->find($test['server']);
        if (!$server) return self::finish($test, false, 'deleted');
        $status = $server->status?->value ?? $server->status;
        $age = time() - (int) $test['since'];
        if ($test['stage'] === 'installing') {
            if (in_array($status, ['install_failed', 'reinstall_failed'], true)) return self::finish($test, false, 'not_installed', null, $server);
            if ($status === null || $status === '') {
                app(DaemonServerRepository::class)->setServer($server)->power('start')->throw();
                EggUpdates::change(function (array $state): array {
                    $state['test']['stage'] = 'starting';
                    $state['test']['since'] = time();
                    return $state;
                });
                // @english
                return ['Test server '.$server->id.' installed, starting.'];
            }
            return $age > self::INSTALL_SECONDS ? self::finish($test, false, 'install_slow', null, $server) : [];
        }
        $running = app(DaemonServerRepository::class)->setServer($server)->getDetails()['state'] ?? 'unknown';
        if ($running === 'running') return self::finish($test, true);
        if ($running === 'offline' && $age > 30) return self::finish($test, false, 'stopped', null, $server);
        return $age > self::START_SECONDS ? self::finish($test, false, 'start_slow', null, $server) : [];
    }

    /** Update on success, report on failure; clean up both ways. $code names the failure (eggs.test.reasons), $detail the error behind it. */
    private static function finish(array $test, bool $ok, string $code = '', ?string $detail = null, ?Server $server = null): array
    {
        $egg = Egg::query()->find($test['egg']);
        $log = $ok || !$server ? '' : self::logTail($server);
        self::cleanUp($test);
        $saved = json_decode((string) Storage::disk('local')->get(self::CONTENT.$test['egg'].'.json'), true);
        Storage::disk('local')->delete(self::CONTENT.$test['egg'].'.json');
        $message = '';
        if ($ok && $egg && is_array($saved)) {
            $result = EggUpdates::update($egg, ['content' => (string) $saved['content'], 'format' => $saved['format'] === 'YAML' ? EggFormat::YAML : EggFormat::JSON, 'hash' => (string) $saved['hash']]);
            $ok = $result['ok'];
            if (!$ok) [$code, $detail] = ['update', $result['error'] ?? $result['message']];
        }
        EggUpdates::change(function (array $state) use ($test, $ok, $code, $detail): array {
            $state['test'] = null;
            $state['results'][$test['egg']] = ['ok' => $ok, 'code' => $code, 'detail' => $detail, 'at' => time()];
            return $state;
        });
        $name = $egg?->name ?? '?';
        if ($ok) {
            EggUpdates::notify(fn () => [__('pelican-simplify::eggs.notify.tested_title', ['name' => $name]), __('pelican-simplify::eggs.notify.tested_body')], 'success');
            $message = $name.' tested and updated.';
        } else {
            $result = ['code' => $code, 'detail' => $detail];
            EggUpdates::notify(fn () => [__('pelican-simplify::eggs.notify.not_installed_title', ['name' => $name]), $log !== ''
                ? __('pelican-simplify::eggs.notify.not_installed_log', ['reason' => self::reasonText($result), 'log' => rtrim($log, '.')])
                : __('pelican-simplify::eggs.notify.not_installed_body', ['reason' => self::reasonText($result)])], 'danger');
            // @english: scheduler output
            $message = $name.': the test server '.($code === 'update' ? 'worked, but the update failed: ' : $code.': ').$detail;
        }
        return [$message];
    }

    /** Why the last test failed, in the reader's language ("did not install."); results saved before codes keep their English text. */
    public static function reasonText(array $result): string
    {
        if (!isset($result['code'])) return (string) ($result['reason'] ?? '');
        return __('pelican-simplify::eggs.test.reasons.'.$result['code'], ['detail' => rtrim((string) ($result['detail'] ?? ''), '.')]);
    }

    private static function cleanUp(array $test): void
    {
        $server = Server::query()->find($test['server']);
        if ($server) {
            try { app(DaemonServerRepository::class)->setServer($server)->power('kill'); } catch (\Throwable) {}
            try { app(ServerDeletionService::class)->withForce()->handle($server); } catch (\Throwable $exception) { report($exception); }
        }
        Allocation::query()->whereKey($test['allocation'])->whereNull('server_id')->delete();
        // The copy goes once no server uses it any more (the forced delete above).
        $copy = Egg::query()->find($test['copy']);
        if ($copy && !Server::query()->where('egg_id', $copy->id)->exists()) $copy->delete();
    }

    private static function logTail(Server $server): string
    {
        try {
            $lines = preg_split('/\R/', trim((string) app(DaemonServerRepository::class)->setServer($server)->getInstallLogs())) ?: [];
            return mb_strimwidth(implode(' | ', array_slice(array_filter($lines), -3)), 0, 300, '…');
        } catch (\Throwable) {
            return '';
        }
    }

    private static function reason(\Throwable $exception): string
    {
        $text = trim(strip_tags($exception->getMessage()));
        if ($exception instanceof \Illuminate\Validation\ValidationException) $text = __('pelican-simplify::eggs.test.variable_needed', ['errors' => implode(' ', array_map(fn ($m) => is_array($m) ? implode(' ', $m) : $m, $exception->errors()))]);
        return $text === '' ? __('pelican-simplify::eggs.results.unknown_error') : rtrim(mb_strimwidth($text, 0, 200, '…'), '.').'.';
    }
}
