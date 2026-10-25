<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
/** Refresh rates and automatic backup defaults, tuned per installation in Simplify settings. */
final class PerformanceSettings
{
    public const FILE = 'simplify/performance.json';
    /** key => [label, help, default, min, max] */
    public const FIELDS = [
        'overview_seconds' => ['Overview refresh (seconds)', 'How often open overview pages refresh server status. Hidden tabs and open popups never poll.', 30, 10, 600],
        'server_seconds' => ['Server page refresh (seconds)', 'How often an open server page refreshes players, MOTD and pending changes.', 20, 5, 600],
        'restart_seconds' => ['Restart progress (seconds)', 'Polling while a due restart applies staged changes.', 3, 2, 60],
        'health_seconds' => ['Host health cache (seconds)', 'Node statistics are shared between all viewers for this long.', 30, 10, 600],
        'backup_interval_minutes' => ['Backup interval for new servers (minutes)', 'Starting interval for automatic backups of newly created servers.', 360, 30, 10080],
        'backup_retention' => ['Recovery points for new servers', 'How many successful automatic backups are kept.', 2, 1, 100],
    ];
    private ?array $loaded = null;

    public static function defaults(): array
    {
        return [...array_map(fn (array $field): int => $field[2], self::FIELDS), 'backup_only_when_used' => true];
    }
    public static function all(): array { return app(self::class)->load(); }
    public static function get(string $key): int|bool { return self::all()[$key] ?? self::defaults()[$key]; }

    private function load(): array
    {
        if ($this->loaded !== null) return $this->loaded;
        return $this->loaded = self::normalize(JsonFile::read(self::FILE));
    }

    private static function normalize(array $input): array
    {
        $result = self::defaults();
        foreach (self::FIELDS as $key => [, , , $min, $max]) {
            if (is_numeric($input[$key] ?? null)) $result[$key] = max($min, min($max, (int) $input[$key]));
        }
        if (array_key_exists('backup_only_when_used', $input)) $result['backup_only_when_used'] = (bool) $input['backup_only_when_used'];
        return $result;
    }

    public static function save(array $input): void
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $rules = ['backup_only_when_used' => 'boolean'];
        foreach (self::FIELDS as $key => [, , , $min, $max]) $rules[$key] = 'required|integer|min:'.$min.'|max:'.$max;
        $next = self::normalize(validator($input, $rules)->validate());
        Cache::lock('simplify.performance', 10)->block(3, function () use ($next): void {
            if (!Storage::disk('local')->put(self::FILE, json_encode($next, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) throw new \RuntimeException('Could not save performance settings.');
            app(self::class)->loaded = $next;
        });
    }

    /** The intervals the dashboard needs in the browser. */
    public static function client(): array
    {
        return array_intersect_key(self::all(), array_flip(['overview_seconds', 'server_seconds', 'restart_seconds']));
    }
}
