<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use App\Enums\ServerState;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The installation view of the server page: when the installation started, how long this egg usually
 * takes, the log so far, Kill and Try again.
 *
 * Wings has no call to stop a running install script and refuses power actions while installing. Kill
 * therefore removes the server from Wings (which cancels the installer and removes its files) and marks
 * it failed; Try again creates it on Wings again, which installs it from scratch. That is only safe for
 * the first installation of a new server: a reinstall would lose the server's files.
 */
final class InstallProgress
{
    public const FAILED = ['install_failed', 'reinstall_failed'];
    /** Seconds Wings gets to remove the killed installer's files before the server is created again. */
    public const RETRY_AFTER = 10;
    private const LOG_LINES = 400;

    private static function path(Server $s): string { return 'simplify/install/'.$s->uuid.'.json'; }

    /** Page data while the server is installing or its installation failed; empty otherwise. */
    public static function info(Server $s, bool $withLog = true): array
    {
        $state = $s->status?->value;
        if ($state !== 'installing' && !in_array($state, self::FAILED, true)) return [];
        return ['install_since' => $s->updated_at?->getTimestamp(), 'install_typical' => self::typical($s), 'install_fresh' => self::fresh($s),
            'install_killed' => self::killed($s), ...($withLog ? ['install_log' => self::log($s)] : [])];
    }

    /** True for the first installation of a new server (or one that was killed): Kill can stop it without losing files. */
    public static function fresh(Server $s): bool
    {
        return $s->installed_at === null || self::killed($s);
    }

    public static function killed(Server $s): bool
    {
        return isset(JsonFile::read(self::path($s))['killed_at']);
    }

    /** The middle duration in seconds of recent successful first installations of this egg, or null with too few. */
    public static function typical(Server $s): ?int
    {
        if (!$s->egg_id) return null;
        return Cache::remember('simplify.install-typical.'.$s->egg_id, 600, function () use ($s): ?int {
            $times = Server::query()->where('egg_id', $s->egg_id)->whereNull('status')->whereNotNull('installed_at')
                ->latest('installed_at')->limit(15)->get(['created_at', 'installed_at'])
                ->map(fn (Server $other) => $other->installed_at->getTimestamp() - $other->created_at->getTimestamp())
                // Longer gaps are reinstalls months later, not an installation time.
                ->filter(fn (int $seconds) => $seconds >= 5 && $seconds <= 7200)->sort()->values();
            return $times->count() >= 2 ? (int) $times[intdiv($times->count(), 2)] : null;
        });
    }

    /** The installation log so far, so a page opened halfway shows what already happened. */
    public static function log(Server $s): array
    {
        try {
            $text = (string) app(DaemonServerRepository::class)->setServer($s)->getInstallLogs();
        } catch (\Throwable) {
            return [];
        }
        $lines = preg_split('/\r?\n/', rtrim(preg_replace('/\x1b\[[0-9;?]*[ -\/]*[@-~]/', '', $text) ?? '')) ?: [];
        return array_slice($lines === [''] ? [] : $lines, -self::LOG_LINES);
    }

    /** Stops the first installation of a new server. */
    public static function kill(Server $s): void
    {
        abort_unless($s->status?->value === 'installing', 409, 'The installation has already finished.');
        abort_unless(self::fresh($s), 409, 'Only the first installation of a new server can be stopped. Stopping a reinstall would delete the server\'s files.');
        self::mark($s, ['killed_at' => time(), 'by' => user()?->id]);
        app(DaemonServerRepository::class)->setServer($s)->delete();
        $s->forceFill(['status' => ServerState::InstallFailed])->save();
    }

    /** Runs the installation again: a killed server is created on Wings again, any other through Pelican's reinstall. */
    public static function retry(Server $s): void
    {
        abort_unless(in_array($s->status?->value, self::FAILED, true), 409, 'The server is not waiting for a new installation.');
        $killed = JsonFile::read(self::path($s))['killed_at'] ?? null;
        if ($killed === null) {
            app(\App\Services\Servers\ReinstallServerService::class)->handle($s);
            return;
        }
        abort_if(time() - (int) $killed < self::RETRY_AFTER, 429, 'Wings is still cleaning up. Try again in a few seconds.');
        $s->forceFill(['status' => ServerState::Installing])->save();
        try {
            app(DaemonServerRepository::class)->setServer($s)->create(false);
        } catch (\Throwable $e) {
            $s->forceFill(['status' => ServerState::InstallFailed])->save();
            throw $e;
        }
        Storage::disk('local')->delete(self::path($s));
    }

    private static function mark(Server $s, array $data): void
    {
        if (!Storage::disk('local')->put(self::path($s), json_encode($data, JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save the installation state.');
    }
}
