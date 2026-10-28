<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use App\Enums\EggFormat;
use App\Models\Egg;
use App\Services\Eggs\Sharing\EggImporterService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Games for the "other game" creator: eggs already on the panel plus the official Pelican egg index
 * (resources/eggs/game-index.json, built by scripts/build-egg-index.py and shipped with each release, so
 * searching never needs the network). Picking an index game downloads its pinned egg, checks the
 * checksum and imports it with Pelican's own importer.
 */
final class GameCatalog
{
    /** Shown first while the panel has few servers of its own. */
    private const POPULAR = ['valheim', 'rust', 'terraria', 'palworld', 'ark: survival ascended', '7 days to die', 'satisfactory', 'counter-strike 2', 'project zomboid', 'factorio', 'enshrouded', 'v rising'];

    private static ?array $index = null;

    public static function index(): array
    {
        if (self::$index !== null) return self::$index;
        try {
            $data = json_decode((string) file_get_contents(plugin_path('pelican-simplify', 'resources/eggs/game-index.json')), true, 512, JSON_THROW_ON_ERROR);
            return self::$index = array_values(array_filter($data['games'] ?? [], 'is_array'));
        } catch (Throwable) {
            return self::$index = [];
        }
    }

    public static function canInstall(): bool
    {
        return CreatorAccess::admin() || (CreatorAccess::canCreate() && RoleAccess::can('install_games'));
    }

    /**
     * Cards for the game picker: key, name, description, installed, popularity (higher first). With Minecraft
     * shown above the list, its eggs (Paper, Forge, Bedrock and so on) are left out here.
     */
    public static function cards(bool $withoutMinecraft = false): array
    {
        $counts = \App\Models\Server::query()->selectRaw('egg_id, count(*) as total')->groupBy('egg_id')->pluck('total', 'egg_id');
        $rank = static fn (string $name): int => ($at = array_search(mb_strtolower($name), self::POPULAR, true)) === false ? 0 : count(self::POPULAR) - $at;
        $cards = CreatorAccess::eggs()->get(['id', 'name', 'description', 'uuid'])->map(fn (Egg $egg): array => [
            'key' => 'egg:'.$egg->id,
            'name' => $egg->name,
            'description' => mb_strimwidth(trim(preg_replace('/\s+/', ' ', (string) $egg->description)), 0, 160, '…'),
            'installed' => true,
            'popular' => (int) ($counts[$egg->id] ?? 0) * 100 + $rank($egg->name),
        ])->reject(fn (array $card): bool => $withoutMinecraft && MinecraftCatalog::isMinecraftEgg($card['name']))->values()->all();
        if (!self::canInstall()) return $cards;
        // Anything already on the panel (approved or not) is never offered for a second import.
        $present = Egg::query()->get(['uuid', 'name']);
        $uuids = $present->pluck('uuid')->filter()->flip();
        $names = $present->pluck('name')->map(fn ($name) => mb_strtolower((string) $name))->flip();
        foreach (self::index() as $game) {
            if (isset($uuids[$game['uuid'] ?? '']) || isset($names[mb_strtolower($game['name'])])) continue;
            $cards[] = ['key' => 'index:'.$game['id'], 'name' => $game['name'], 'description' => $game['description'] ?: $game['category'].' game server', 'installed' => false, 'popular' => $rank($game['name'])];
        }
        return $cards;
    }

    /** The egg for a picker key; index games are installed first (permission, checksum, lock). */
    public static function resolve(?string $key): ?Egg
    {
        if (!is_string($key)) return null;
        if (str_starts_with($key, 'egg:')) return OtherGameCatalog::selected((int) substr($key, 4));
        if (!str_starts_with($key, 'index:')) return null;
        $game = collect(self::index())->firstWhere('id', substr($key, 6));
        if (!$game) return null;
        if (!self::canInstall()) {
            throw ValidationException::withMessages(['data.game_key' => __('pelican-simplify::maker.game.this_game_is_not_on_the')]);
        }
        try {
            return Cache::lock('simplify.game-install.'.$game['id'], 120)->block(15, fn (): Egg => self::install($game));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['data.game_key' => __('pelican-simplify::maker.game.this_game_is_being_installed_by')]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['data.game_key' => __('pelican-simplify::maker.game.install_failed', ['error' => $exception->getMessage()])]);
        }
    }

    private static function install(array $game): Egg
    {
        // UUID only: an unrelated egg that happens to share the name must never be approved for self-service.
        $egg = filled($game['uuid'] ?? null) ? Egg::query()->where('uuid', $game['uuid'])->first() : null;
        if (!$egg) {
            if (!str_starts_with((string) $game['url'], 'https://raw.githubusercontent.com/pelican-eggs/')) throw new \RuntimeException('Unknown egg source.');
            $content = Http::timeout(20)->connectTimeout(5)->get($game['url'])->throw()->body();
            if (!hash_equals((string) $game['sha256'], hash('sha256', $content))) throw new \RuntimeException('The download does not match the egg index checksum.');
            $egg = app(EggImporterService::class)->fromContent($content, EggFormat::JSON, new Egg());
            self::notifyAdmins($egg);
        }
        self::approve($egg);
        return $egg;
    }

    /** Installed games become available for self-service, so the person who installed it can use it. */
    private static function approve(Egg $egg): void
    {
        $data = CreatorAccess::configuration();
        $ids = array_map('intval', $data['other_egg_ids'] ?? []);
        if (in_array((int) $egg->id, $ids, true)) return;
        $data['other_egg_ids'] = [...$ids, (int) $egg->id];
        Storage::disk('local')->put(CreatorAccess::FILE, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        app(CreatorAccess::class)->reset();
    }

    private static function notifyAdmins(Egg $egg): void
    {
        try {
            $by = user();
            foreach (\App\Models\User::query()->get()->filter(fn ($user) => $user->isRootAdmin() && $user->id !== $by?->id) as $user) Translations::as($user, fn () => $user->notifyNow(\Filament\Notifications\Notification::make()->info()
                ->title(__('pelican-simplify::maker.game.installed_title', ['name' => $egg->name]))
                ->body(__('pelican-simplify::maker.game.installed_body', ['by' => $by?->username ?? '?']))->toDatabase()));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
