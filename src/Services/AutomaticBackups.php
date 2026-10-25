<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Server;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
/**
 * Automatic backup policy per server, driven by the scheduler (simplify:maintenance).
 * Each minute costs one small file read per server until a backup is actually due; database
 * queries and Wings calls only happen when there is work to do.
 */
final class AutomaticBackups
{
    /** How often a server's running state is sampled for "skip while unused". */
    private const PROBE_SECONDS = 600;

    private static function path(Server $s): string { return 'simplify/backups/'.$s->uuid.'.json'; }

    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'interval_minutes' => (int) PerformanceSettings::get('backup_interval_minutes'),
            'retention' => (int) PerformanceSettings::get('backup_retention'),
            'only_when_used' => (bool) PerformanceSettings::get('backup_only_when_used'),
            'last_attempt' => 0,
            'last_active' => 0,
            'last_probe' => 0,
            'pruned_at' => 0,
        ];
    }

    public static function settings(Server $s): array
    {
        return array_replace(self::defaults(), JsonFile::read(self::path($s)));
    }

    /** New servers start with the configured defaults and a first backup once they have run. */
    public static function initialize(Server $s): void
    {
        if (Storage::disk('local')->get(self::path($s)) !== null) return;
        self::write($s, array_intersect_key(self::defaults(), array_flip(['enabled', 'interval_minutes', 'retention', 'only_when_used', 'last_attempt'])));
    }

    public static function save(Server $s, array $input): void
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $data = validator($input, ['enabled' => 'required|boolean', 'interval_minutes' => 'required|integer|min:30|max:10080', 'retention' => 'required|integer|min:1|max:100', 'only_when_used' => 'sometimes|boolean'])->validate();
        self::write($s, array_replace(self::settings($s), $data));
    }

    private static function write(Server $s, array $options): void
    {
        if (!Storage::disk('local')->put(self::path($s), json_encode($options, JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save the backup policy.');
    }

    public static function tick(Server $s): void
    {
        if (!$s->installed_at || $s->status !== null) return;
        Cache::lock('simplify.backups.'.$s->uuid, 60)->block(1, function () use ($s): void {
            $options = self::settings($s);
            if (!$options['enabled']) return;
            $now = time();
            $changed = false;
            if ($options['only_when_used'] && $now - (int) $options['last_probe'] >= self::PROBE_SECONDS) {
                $options['last_probe'] = $now;
                $changed = true;
                try {
                    $state = $s->retrieveStatus();
                    $state = is_object($state) ? ($state->value ?? '') : (string) $state;
                    if ($state !== 'offline') $options['last_active'] = $now;
                } catch (\Throwable) {
                    $options['last_active'] = $now; // Unknown state never suppresses a backup.
                }
            }
            // After a new backup finished, prune old recovery points once.
            if ((int) $options['pruned_at'] < (int) $options['last_attempt']) {
                $finished = $s->backups()->where('is_successful', true)->whereNotNull('completed_at')->orderByDesc('created_at')->get();
                foreach ($finished->slice((int) $options['retention']) as $old) {
                    if (!$old->is_locked) app(\App\Services\Backups\DeleteBackupService::class)->handle($old);
                }
                if (!$s->backups()->whereNull('completed_at')->exists()) {
                    $options['pruned_at'] = $now;
                    $changed = true;
                }
            }
            $due = $now - (int) $options['last_attempt'] >= (int) $options['interval_minutes'] * 60;
            if (!$due) {
                if ($changed) self::write($s, $options);
                return;
            }
            if ($s->backups()->whereNull('completed_at')->exists()) {
                if ($changed) self::write($s, $options);
                return;
            }
            $latest = $s->backups()->where('is_successful', true)->max('created_at');
            $latestAt = $latest ? strtotime((string) $latest) : 0;
            $unused = $options['only_when_used'] && $latestAt > 0 && (int) $options['last_active'] < $latestAt;
            $noStorage = !$s->node->backupHosts()->exists() && !\App\Models\BackupHost::doesntHave('nodes')->exists();
            $allLocked = $s->backups()->where('is_locked', true)->where('is_successful', true)->count() >= (int) $options['retention'];
            if ($unused || $noStorage || $allLocked || $now - $latestAt < (int) $options['interval_minutes'] * 60) {
                $options['last_attempt'] = max((int) $options['last_attempt'], $now - (int) $options['interval_minutes'] * 60 + 300);
                self::write($s, $options);
                return;
            }
            $options['last_attempt'] = $now;
            $options['pruned_at'] = 0;
            self::write($s, $options);
            // Reserve one temporary slot so a failed backup never destroys either
            // existing recovery point. Prune only completed successful backups.
            $backupServer = clone $s;
            $backupServer->backup_limit = $s->backups()->nonFailed()->count() + 1;
            app(\App\Services\Backups\InitiateBackupService::class)->setIsScheduled(true)->setIsLocked(false)->handle($backupServer, 'Automatic · '.now()->format('Y-m-d H:i'));
        });
    }
}
