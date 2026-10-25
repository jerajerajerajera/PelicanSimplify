<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * What ordinary users may do, per Pelican role. "Everyone" applies to every signed-in user; each role adds
 * abilities and sets its own limits (several roles: abilities combine, the highest limit wins). Administrators can
 * always do everything. Server modules that are switched off in Simplify settings stay off for everyone.
 */
final class RoleAccess
{
    public const FILE = 'simplify/role-access.json';

    /** Ability key => [label, description]. Workspace keys match Access::feature(). */
    public const ABILITIES = [
        'settings' => ['Game settings', 'Change gameplay settings, MOTD and server appearance.'],
        'files' => ['Files', 'Browse and edit server files. Pelican file permissions still apply.'],
        'players' => ['Players', 'Whitelist, operators and bans.'],
        'backups' => ['Backups', 'Create, download and restore backups.'],
        'packages' => ['Mods & plugins', 'Search, install and remove mods or plugins.'],
        'activity' => ['Activity log', 'See what happened on the server.'],
        'schedules' => ['Scheduled tasks', 'Plan restarts and console commands.'],
        'resources' => ['Change resources', 'Adjust CPU, RAM and disk of owned servers within the limits below.'],
        'software' => ['Change software', 'Switch Minecraft software or modpack (reinstalls the server).'],
        'advanced' => ['Advanced', 'Container, startup and other advanced settings.'],
        'legacy' => ['Legacy panel', 'Open Pelican\'s own client panel.'],
        'create' => ['Create servers', 'Use the easy creator within the limits below.'],
        'install_eggs' => ['Install missing eggs', 'Let the creator import Simplify\'s bundled egg when it is not installed yet.'],
        'install_games' => ['Install new games', 'Let the creator download a game (egg) from the official Pelican egg index when it is not on the panel yet. Administrators can always do this.'],
    ];
    public const LIMITS = ['max_servers' => [2, 1, 100], 'max_memory_mb' => [8192, 1024, 131072], 'max_disk_mb' => [40960, 1024, 10485760], 'max_cpu' => [200, 1, 1600], 'max_backups' => [3, 0, 100]];
    private const EVERYONE_DEFAULT = ['settings', 'files', 'players', 'backups', 'packages', 'activity', 'resources'];

    private ?array $loaded = null;

    public static function limitDefaults(): array { return array_map(fn (array $l): int => $l[0], self::LIMITS); }

    /** ['everyone' => profile, 'roles' => [['role_id' => int, ...profile], ...]] */
    public static function all(): array { return app(self::class)->load(); }

    private function load(): array
    {
        if ($this->loaded !== null) return $this->loaded;
        return $this->loaded = self::normalize(JsonFile::read(self::FILE, null) ?? self::migrate());
    }

    /** First run: build profiles from the earlier user toggles and creator grants. */
    private static function migrate(): array
    {
        $features = FeatureSettings::all();
        $everyone = [];
        foreach (array_keys(self::ABILITIES) as $key) {
            if (array_key_exists('user_'.$key, $features) && $features['user_'.$key]) $everyone[] = $key;
        }
        if (!array_key_exists('user_settings', $features)) $everyone = self::EVERYONE_DEFAULT;
        $creator = CreatorAccess::configuration();
        $shared = array_intersect_key($creator, self::LIMITS);
        $roles = [];
        foreach (array_map('intval', $creator['role_ids'] ?? []) as $roleId) {
            $roles[$roleId] = ['role_id' => $roleId, 'abilities' => array_values(array_filter(['create', !empty($creator['allow_egg_import']) ? 'install_eggs' : null])), ...$shared];
        }
        foreach ($creator['role_limits'] ?? [] as $profile) {
            $roleId = (int) ($profile['role_id'] ?? 0);
            if ($roleId < 1) continue;
            $roles[$roleId] = [...($roles[$roleId] ?? ['role_id' => $roleId, 'abilities' => []]), ...array_intersect_key($profile, self::LIMITS)];
        }
        return ['everyone' => ['abilities' => $everyone, ...$shared], 'roles' => array_values($roles)];
    }

    private static function profile(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $abilities = array_values(array_intersect(array_keys(self::ABILITIES), is_array($raw['abilities'] ?? null) ? $raw['abilities'] : []));
        $profile = ['abilities' => $abilities];
        foreach (self::LIMITS as $key => [$default, $min, $max]) $profile[$key] = max($min, min($max, (int) ($raw[$key] ?? $default)));
        return $profile;
    }

    public static function normalize(array $raw): array
    {
        $roles = [];
        foreach (is_array($raw['roles'] ?? null) ? $raw['roles'] : [] as $role) {
            $id = (int) ($role['role_id'] ?? 0);
            if ($id > 0) $roles[$id] = ['role_id' => $id, ...self::profile($role)];
        }
        return ['everyone' => self::profile($raw['everyone'] ?? []), 'roles' => array_values($roles)];
    }

    public static function save(array $input): void
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $next = self::normalize($input);
        Cache::lock('simplify.role-access', 10)->block(3, function () use ($next): void {
            if (!Storage::disk('local')->put(self::FILE, json_encode($next, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) throw new \RuntimeException('Could not save role permissions.');
        });
        app(self::class)->loaded = $next;
    }

    /** The combined profile of a user: Everyone plus all of their roles. */
    public static function forUser(?object $user): array
    {
        $all = self::all();
        $profiles = [$all['everyone']];
        $roleIds = [];
        foreach ($user?->roles ?? [] as $role) $roleIds[] = (int) $role->id;
        foreach ($all['roles'] as $role) if (in_array($role['role_id'], $roleIds, true)) $profiles[] = $role;
        $abilities = array_values(array_unique(array_merge(...array_column($profiles, 'abilities'))));
        // Limits: a user's roles replace Everyone's limits (highest per resource wins); no role, Everyone's.
        $limitProfiles = count($profiles) > 1 ? array_slice($profiles, 1) : $profiles;
        $limits = [];
        foreach (array_keys(self::LIMITS) as $key) $limits[$key] = max(array_column($limitProfiles, $key));
        return ['abilities' => $abilities, ...$limits];
    }

    /** Whether the signed-in (non-admin) user has an ability. Administrators always do. */
    public static function can(string $ability): bool
    {
        $user = user();
        if (!$user) return false;
        if ($user->isAdmin()) return true;
        return in_array($ability, self::forUser($user)['abilities'], true);
    }
}
