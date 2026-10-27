<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use App\Enums\EggFormat;
use App\Models\Egg;
use App\Models\Server;
use App\Services\Eggs\Sharing\EggExporterService;
use App\Services\Eggs\Sharing\EggImporterService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;

/**
 * Egg updates. Pelican checks each egg's update URL once a day but only remembers the result for an hour and shows it
 * as a button on its Eggs page. Simplify checks itself, keeps the result, tells root admins in the bell, keeps the
 * previous definition of every updated egg so it can be put back, and can update eggs no server uses on its own.
 */
final class EggUpdates
{
    public const SETTINGS = 'simplify/egg-updates.json';
    public const STATE = 'simplify/egg-updates-state.json';
    private const BACKUPS = 'simplify/egg-backups/';
    /** Backups kept per egg. */
    private const KEEP = 5;
    /** Seconds between two checks of every update URL. */
    private const INTERVAL = 6 * 3600;

    public const MODES = [
        'ask' => 'Ask first',
        'auto' => 'Update eggs without servers automatically',
        'off' => 'Off',
    ];

    public static function defaults(): array
    {
        return ['mode' => 'ask'];
    }

    public static function settings(): array
    {
        $saved = JsonFile::read(self::SETTINGS);
        $settings = array_replace(self::defaults(), array_intersect_key($saved, self::defaults()));
        if (!isset(self::MODES[$settings['mode']])) $settings['mode'] = 'ask';
        return $settings;
    }

    public static function save(array $input): void
    {
        abort_unless(user() === null || user()->isRootAdmin(), 403);
        $next = array_replace(self::settings(), validator(array_intersect_key($input, self::defaults()), [
            'mode' => 'sometimes|in:'.implode(',', array_keys(self::MODES)),
        ])->validate());
        if (!Storage::disk('local')->put(self::SETTINGS, json_encode($next, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) throw new \RuntimeException('Could not save the egg update settings.');
    }

    /** Keyed by egg id (an update can change the egg's uuid). found: id => [hash, at, held]; applied: id => remote hash last installed. */
    public static function state(): array
    {
        return array_replace(['found' => [], 'applied' => [], 'checked_at' => 0], JsonFile::read(self::STATE));
    }

    /** Change the state under a lock, so the scheduler and a click on Update never overwrite each other. */
    public static function change(callable $change): array
    {
        return Cache::lock('simplify.egg-updates', 20)->block(10, function () use ($change): array {
            $state = self::state();
            $state = $change($state) ?? $state;
            if (!Storage::disk('local')->put(self::STATE, json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) throw new \RuntimeException('Could not save the egg update state.');
            return $state;
        });
    }

    /** Scheduled every minute: checks the update URLs every few hours and acts on what it finds as the settings say. */
    public static function run(bool $force = false): array
    {
        $settings = self::settings();
        if ($settings['mode'] === 'off' && !$force) return [];
        if (!$force && time() - (int) self::state()['checked_at'] < self::INTERVAL) return [];
        $new = self::check();
        return $new === [] ? [] : self::handleNew($new, $settings);
    }

    /**
     * Compares every egg with its update URL, the way Pelican does (both definitions without exported_at, as JSON,
     * md5). A remote definition Simplify installed before counts as current, so a file Pelican rewrites on import
     * (an older egg format) is not offered again and again. Returns the eggs that have a new update since last time.
     */
    public static function check(): array
    {
        $results = [];
        foreach (Egg::query()->whereNotNull('update_url')->get() as $egg) {
            try {
                $remote = self::remote($egg);
                $results[$egg->id] = ['egg' => $egg, 'hash' => $remote['hash'], 'differs' => self::localHash($egg, $remote['format']) !== $remote['hash']];
            } catch (\Throwable) {
                // Unreachable or broken update URL: keep what was known.
            }
        }
        $new = [];
        self::change(function (array $state) use ($results, &$new): array {
            foreach ($results as $id => $result) {
                $current = !$result['differs'] || ($state['applied'][$id] ?? null) === $result['hash'];
                if ($current) {
                    unset($state['found'][$id]);
                    continue;
                }
                $known = $state['found'][$id] ?? null;
                if (($known['hash'] ?? null) === $result['hash']) continue;
                $state['found'][$id] = ['hash' => $result['hash'], 'at' => time(), 'held' => false];
                $new[] = $result['egg'];
            }
            // Eggs that were deleted.
            $state['found'] = array_intersect_key($state['found'], array_flip(Egg::query()->pluck('id')->all()));
            $state['checked_at'] = time();
            return $state;
        });
        // Pelican's own Update button on its Eggs page follows the same result.
        $found = self::state()['found'];
        foreach ($results as $id => $result) Cache::put('eggs.'.$result['egg']->uuid.'.update', isset($found[$id]), now()->addHours(7));
        return $new;
    }

    /** New updates: tell root admins, and update the eggs no server uses when that is switched on. */
    private static function handleNew(array $eggs, array $settings): array
    {
        $messages = [];
        $updated = [];
        $waiting = [];
        foreach ($eggs as $egg) {
            $servers = self::serverCount($egg);
            if ($settings['mode'] === 'auto' && $servers === 0) {
                $result = self::update($egg);
                $messages[] = $result['message'];
                if ($result['ok']) $updated[] = $egg->name;
                else $waiting[] = $egg;
                continue;
            }
            $waiting[] = $egg;
        }
        if ($updated !== []) self::notify(count($updated) === 1 ? $updated[0].' egg updated' : count($updated).' eggs updated', self::names($updated).(count($updated) === 1 ? ' has' : ' have').' no servers and '.(count($updated) === 1 ? 'was' : 'were').' updated. The previous version is kept.', 'success');
        if ($waiting !== []) {
            $names = array_map(fn (Egg $egg): string => $egg->name, $waiting);
            self::notify(count($names) === 1 ? 'Egg update for '.$names[0] : count($names).' egg updates available', self::names($names).(count($names) === 1 ? ' has' : ' have').' a new version.', 'info');
        }
        return $messages;
    }

    /**
     * Updates one egg to the definition at its update URL (or $remote, already read). The current definition
     * is kept as a backup first. Running servers are not touched.
     *
     * @return array{ok: bool, message: string}
     */
    public static function update(Egg $egg, ?array $remote = null): array
    {
        $lock = Cache::lock('simplify.egg-update.'.$egg->id, 120);
        if (!$lock->get()) return ['ok' => false, 'message' => $egg->name.' is already being updated.'];
        try {
            $remote ??= self::remote($egg);
            self::backup($egg);
            app(EggImporterService::class)->fromContent($remote['content'], $remote['format'], $egg);
            self::change(function (array $state) use ($egg, $remote): array {
                $state['applied'][$egg->id] = $remote['hash'];
                unset($state['found'][$egg->id]);
                return $state;
            });
            Cache::forget('eggs.'.$egg->uuid.'.update');
            Cache::forget('eggs.'.$egg->fresh()?->uuid.'.update');
            return ['ok' => true, 'message' => $egg->name.' is updated.'];
        } catch (\Throwable $exception) {
            report($exception);
            return ['ok' => false, 'message' => $egg->name.' could not be updated: '.self::reason($exception)];
        } finally {
            $lock->release();
        }
    }

    /** Puts the last kept definition back. The update stays listed, held, so automatic updates leave it alone. */
    public static function restore(Egg $egg): array
    {
        $backups = self::backups($egg);
        if ($backups === []) return ['ok' => false, 'message' => 'There is no earlier version of '.$egg->name.' to put back.'];
        try {
            $content = (string) Storage::disk('local')->get($backups[0]['path']);
            app(EggImporterService::class)->fromContent($content, EggFormat::JSON, $egg);
            Storage::disk('local')->delete($backups[0]['path']);
            $hash = null;
            try { $hash = self::remote($egg)['hash']; } catch (\Throwable) {}
            self::change(function (array $state) use ($egg, $hash): array {
                unset($state['applied'][$egg->id]);
                if ($hash !== null) $state['found'][$egg->id] = ['hash' => $hash, 'at' => time(), 'held' => true];
                return $state;
            });
            return ['ok' => true, 'message' => 'The previous version of '.$egg->name.' is back.'];
        } catch (\Throwable $exception) {
            report($exception);
            return ['ok' => false, 'message' => 'The previous version of '.$egg->name.' could not be put back: '.self::reason($exception)];
        }
    }

    /**
     * Existing servers keep their own startup command and Docker image: Pelican copies both into the server when it is
     * created. This moves servers that still use the egg's previous default (or a command or image the update removed
     * or changed under the same name) to the new one. It applies at their next start.
     */
    public static function moveServers(Egg $egg): array
    {
        $backup = self::backups($egg)[0] ?? null;
        $previous = $backup ? json_decode((string) Storage::disk('local')->get($backup['path']), true) : null;
        if (!is_array($previous)) return ['ok' => false, 'message' => 'Simplify has no earlier version of '.$egg->name.' to compare with.', 'moved' => 0];
        $plan = self::movePlan($egg, $previous);
        foreach ($plan as $id => $change) Server::query()->whereKey($id)->update($change);
        $count = count($plan);
        return ['ok' => true, 'moved' => $count, 'message' => $count === 0 ? 'All servers on '.$egg->name.' already use its current startup command and image.' : $count.' '.($count === 1 ? 'server uses' : 'servers use').' the new startup command or image of '.$egg->name.' from '.($count === 1 ? 'its' : 'their').' next start.'];
    }

    /** server id => [startup?, image?] for servers that still run what the previous definition offered. */
    public static function movePlan(Egg $egg, array $previous): array
    {
        $servers = Server::query()->where('egg_id', $egg->id)->get(['id', 'startup', 'image'])->map(fn (Server $server): array => ['id' => $server->id, 'startup' => $server->startup, 'image' => $server->image])->all();
        return self::moves($servers, $previous, $egg->startup_commands ?? [], $egg->docker_images ?? []);
    }

    /**
     * A server moves only when its command or image came from the previous definition and the new one no longer has
     * it; it then gets the entry with the same name, or the new default. Anything the server set itself stays.
     */
    public static function moves(array $servers, array $previous, array $newStartups, array $newImages): array
    {
        $oldStartups = self::startups($previous);
        $oldImages = is_array($previous['docker_images'] ?? null) ? $previous['docker_images'] : [];
        $plan = [];
        foreach ($servers as $server) {
            $change = [];
            $name = array_search($server['startup'], $oldStartups, true);
            if ($name !== false && !in_array($server['startup'], $newStartups, true)) {
                $next = $newStartups[$name] ?? reset($newStartups);
                if (is_string($next) && $next !== '') $change['startup'] = $next;
            }
            $label = array_search($server['image'], $oldImages, true);
            if ($label !== false && !in_array($server['image'], $newImages, true)) {
                $next = $newImages[$label] ?? reset($newImages);
                if (is_string($next) && $next !== '') $change['image'] = $next;
            }
            if ($change !== []) $plan[$server['id']] = $change;
        }
        return $plan;
    }

    /** Startup commands of an exported definition, name => command (older exports have one "startup"). */
    private static function startups(array $definition): array
    {
        if (is_array($definition['startup_commands'] ?? null)) return array_filter($definition['startup_commands'], 'is_string');
        return is_string($definition['startup'] ?? null) ? ['Default' => $definition['startup']] : [];
    }

    /** The listed updates for the settings page and the notifications. */
    public static function available(): array
    {
        $state = self::state();
        if ($state['found'] === []) return [];
        $eggs = Egg::query()->whereIn('id', array_keys($state['found']))->withCount('servers')->orderBy('name')->get();
        return $eggs->map(fn (Egg $egg): array => [
            'id' => $egg->id,
            'name' => $egg->name,
            'servers' => (int) $egg->servers_count,
            'found' => (int) ($state['found'][$egg->id]['at'] ?? 0),
            'held' => (bool) ($state['found'][$egg->id]['held'] ?? false),
        ])->all();
    }

    /** Eggs with a kept earlier version, for "Put back" and "Move servers". */
    public static function updated(): array
    {
        $ids = [];
        foreach (Storage::disk('local')->directories(rtrim(self::BACKUPS, '/')) as $directory) $ids[] = (int) basename($directory);
        if ($ids === []) return [];
        return Egg::query()->whereIn('id', $ids)->withCount('servers')->orderBy('name')->get()->map(function (Egg $egg): array {
            $backups = self::backups($egg);
            $previous = $backups ? json_decode((string) Storage::disk('local')->get($backups[0]['path']), true) : null;
            return ['id' => $egg->id, 'name' => $egg->name, 'servers' => (int) $egg->servers_count, 'at' => $backups[0]['at'] ?? 0, 'moves' => is_array($previous) ? count(self::movePlan($egg, $previous)) : 0];
        })->filter(fn (array $row): bool => $row['at'] > 0)->values()->all();
    }

    /** Kept definitions of one egg, newest first. */
    public static function backups(Egg $egg): array
    {
        $files = Storage::disk('local')->files(self::BACKUPS.$egg->id);
        $list = [];
        foreach ($files as $path) if (preg_match('/(\d+)\.json$/', $path, $match)) $list[] = ['path' => $path, 'at' => (int) $match[1]];
        usort($list, fn (array $a, array $b): int => $b['at'] <=> $a['at']);
        return $list;
    }

    private static function backup(Egg $egg): void
    {
        $content = app(EggExporterService::class)->handle($egg->id, EggFormat::JSON);
        $path = self::BACKUPS.$egg->id.'/'.time().'.json';
        if (!Storage::disk('local')->put($path, $content)) throw new \RuntimeException('Could not keep the current version.');
        foreach (array_slice(self::backups($egg), self::KEEP) as $old) Storage::disk('local')->delete($old['path']);
    }

    /** @return array{content: string, format: EggFormat, hash: string} */
    public static function remote(Egg $egg): array
    {
        $url = (string) $egg->update_url;
        if (!preg_match('#^https://#i', $url)) throw new \RuntimeException('The update URL must start with https://.');
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $format = in_array($extension, ['yaml', 'yml'], true) ? EggFormat::YAML : EggFormat::JSON;
        $response = Http::timeout(10)->connectTimeout(3)->get($url)->throw();
        $content = $response->body();
        $parsed = $format === EggFormat::YAML ? Yaml::parse($content) : json_decode($content, true);
        if (!is_array($parsed)) throw new \RuntimeException('The update URL does not return an egg.');
        return ['content' => $content, 'format' => $format, 'hash' => self::hash($parsed)];
    }

    private static function localHash(Egg $egg, EggFormat $format): string
    {
        $export = app(EggExporterService::class)->handle($egg->id, $format);
        $parsed = $format === EggFormat::YAML ? Yaml::parse($export) : json_decode($export, true);
        return self::hash(is_array($parsed) ? $parsed : []);
    }

    private static function hash(array $definition): string
    {
        unset($definition['exported_at']);
        return md5(json_encode($definition, JSON_THROW_ON_ERROR));
    }

    public static function serverCount(Egg $egg): int
    {
        return Server::query()->where('egg_id', $egg->id)->count();
    }

    /** A notification for every root admin, with a link to Egg updates. */
    public static function notify(string $title, string $body, string $tone = 'info'): void
    {
        try {
            $notification = \Filament\Notifications\Notification::make()->status($tone)->title($title)->body($body)
                ->actions([\Filament\Actions\Action::make('review')->label('Egg updates')->url(self::url())]);
            foreach (\App\Models\User::query()->get()->filter(fn ($user) => $user->isRootAdmin()) as $user) $user->notifyNow($notification->toDatabase());
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /** The Egg updates tab of Simplify settings, in the dashboard's settings popup when the dashboard is on. */
    public static function url(): string
    {
        if (FeatureSettings::enabled('dashboard')) return \Simplify\Panel\Filament\App\Pages\Dashboard::getUrl(['settings' => 'egg-updates'], panel: 'app');
        return \Simplify\Panel\Filament\Admin\Pages\SimplifySettings::getUrl(['section' => 'egg-updates::data::tab'], panel: 'admin');
    }

    public static function names(array $names): string
    {
        if (count($names) <= 1) return (string) ($names[0] ?? '');
        $last = array_pop($names);
        return implode(', ', $names).' and '.$last;
    }

    private static function reason(\Throwable $exception): string
    {
        $text = trim(strip_tags($exception->getMessage()));
        return $text === '' ? 'unknown error.' : rtrim(mb_strimwidth($text, 0, 200, '…'), '.').'.';
    }
}
