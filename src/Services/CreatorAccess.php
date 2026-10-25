<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use App\Filament\Admin\Resources\Servers\ServerResource;
use App\Models\Egg;
use App\Models\Node;
use Illuminate\Validation\ValidationException;

/** Explicit self-service grants, separate from Pelican administration permissions. */
final class CreatorAccess
{
    private ?array $cached=null;
    public function reset(): void { $this->cached=null; }
    public const FILE = 'ssc/creator-access.json';

    public static function defaults(): array
    {
        return ['enabled' => false, 'user_ids' => [], 'role_ids' => [], 'role_limits' => [], 'user_limits' => [], 'node_ids' => [], 'other_egg_ids' => [],
            'minecraft_enabled' => true, 'allow_egg_import' => false, 'max_servers' => 2, 'max_memory_mb' => 8192,
            'max_disk_mb' => 40960, 'max_cpu' => 200, 'max_backups' => 3];
    }

    public static function configuration(): array
    {
        $instance=app(self::class);if($instance->cached!==null)return $instance->cached;
        return $instance->cached=array_replace(self::defaults(), JsonFile::read(self::FILE));
    }

    /** Limits come from the user's roles (RoleAccess); individual grants from earlier versions still win. */
    public static function effective(?object $user, array $settings): array
    {
        if ($user === null) return $settings;
        $keys = ['max_servers','max_memory_mb','max_disk_mb','max_cpu','max_backups'];
        $settings = [...$settings, ...array_intersect_key(RoleAccess::forUser($user), array_flip($keys))];
        foreach ($settings['user_limits'] ?? [] as $profile) {
            if ((int) ($profile['user_id'] ?? 0) !== (int) $user->id) continue;
            foreach ($keys as $key) if (isset($profile[$key])) $settings[$key] = (int) $profile[$key];
        }
        return $settings;
    }

    /** Individual user grants saved by earlier versions; honoured, no longer editable. */
    public static function legacyUserGrants(): int
    {
        $settings = self::configuration();
        return count(array_unique([...array_map('intval', $settings['user_ids'] ?? []), ...array_map(fn ($p) => (int) ($p['user_id'] ?? 0), $settings['user_limits'] ?? [])]));
    }

    public static function settings(): array
    {
        $settings = self::configuration();
        return self::admin() ? $settings : self::effective(user(), $settings);
    }

    /** Whether the signed-in user may let the creator import a missing (bundled, integrity-checked) egg. */
    public static function canImportEggs(): bool
    {
        if (user()?->can('import egg')) return true;
        return self::canCreate() && RoleAccess::can('install_eggs');
    }

    public static function admin(): bool
    {
        return (bool) (user()?->isAdmin() && ServerResource::canCreate());
    }

    public static function allowed(?object $user, array $settings): bool
    {
        $legacyGrant = !empty($settings['enabled']) && in_array((int) $user?->id, array_map('intval', $settings['user_ids'] ?? []), true);
        return $user !== null
            && (in_array('create', RoleAccess::forUser($user)['abilities'], true) || $legacyGrant)
            && !empty($settings['node_ids'])
            && (!empty($settings['minecraft_enabled']) || !empty($settings['other_egg_ids']));
    }

    public static function canCreate(): bool
    {
        return self::admin() || self::allowed(user(), self::settings());
    }

    public static function nodes(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Node::query()->whereIn('id', user()?->accessibleNodes()->pluck('id') ?? []);
        return self::admin() ? $query : $query->whereIn('id', self::settings()['node_ids']);
    }

    public static function eggs(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Egg::query()->orderBy('name');
        return self::admin() ? $query : $query->whereIn('id', self::settings()['other_egg_ids']);
    }

    /** Called again inside the creation transaction; all existing owned servers count. */
    public static function checkQuota(array $payload, array $usage, array $limits): void
    {
        $fail = static fn (string $field, string $message) => throw ValidationException::withMessages(['data.' . $field => $message]);
        if ($usage['count'] >= (int) $limits['max_servers']) {
            $fail('name', 'Your server limit has been reached.');
        }
        foreach (['memory' => ['max_memory_mb', 'ram_gb'], 'disk' => ['max_disk_mb', 'disk_mb']] as $key => [$limit, $field]) {
            if ((int) $payload[$key] < 1 || (int) $payload[$key] + (int) $usage[$key] > (int) $limits[$limit]) {
                $fail($field, 'This exceeds your total ' . ($key === 'memory' ? 'RAM' : 'disk') . ' allowance.');
            }
        }
        if ((int) $payload['cpu'] < 1 || (int) $payload['cpu'] > (int) $limits['max_cpu']) {
            $fail('cpu', 'CPU must be within your per-server allowance; unlimited CPU is unavailable.');
        }
        if ((int) $payload['backup_limit'] < 0 || (int) $payload['backup_limit'] > (int) $limits['max_backups']) {
            $fail('backup_limit', 'Too many backup slots for your allowance.');
        }
    }

    public static function assertState(array $state): void
    {
        abort_unless(self::canCreate(), 403);
        if (self::admin()) {
            return;
        }
        if (($state['java_version'] ?? 'auto') !== 'auto') {
            throw ValidationException::withMessages(['data.java_version' => 'Java is selected automatically for your server.']);
        }
        $settings = self::settings();
        $game = $state['game'] ?? 'minecraft';
        if (($game === 'minecraft' && !$settings['minecraft_enabled']) || !in_array($game, ['minecraft', 'other'], true)) {
            throw ValidationException::withMessages(['data.game' => 'This game is not available for your account.']);
        }
        if ($game === 'other' && !in_array((int) ($state['game_egg_id'] ?? 0), array_map('intval', $settings['other_egg_ids']), true)) {
            throw ValidationException::withMessages(['data.game_egg_id' => 'Choose an approved game.']);
        }
        foreach (['image', 'startup'] as $field) {
            if (trim((string) ($state[$field] ?? '')) !== '') {
                throw ValidationException::withMessages(['data.' . $field => 'This override is available to administrators only.']);
            }
        }
        if (!empty($state['skip_scripts']) || (int) ($state['swap'] ?? 0) !== 0
            || (int) ($state['database_limit'] ?? 0) !== 0 || (int) ($state['allocation_limit'] ?? 0) !== 0) {
            throw ValidationException::withMessages(['data.name' => 'These advanced limits are managed by an administrator.']);
        }
        if (!empty($state['environment_overrides'])) {
            throw ValidationException::withMessages(['data.environment_overrides' => 'Use the game settings provided by the wizard.']);
        }
    }
}
