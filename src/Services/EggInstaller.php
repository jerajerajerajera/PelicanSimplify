<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use App\Enums\EggFormat;
use App\Models\Egg;
use App\Services\Eggs\Sharing\EggImporterService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Import only the bundled definition for a missing expected egg. */
final class EggInstaller
{
    public static function existing(array $state): ?Egg
    {
        return ($state['server_type'] ?? '') === 'modded'
            ? ModpackCatalog::egg($state['modpack_project'] ?? null)
            : MinecraftCatalog::eggForType((string) ($state['server_type'] ?? ''), $state['server_software'] ?? null);
    }

    public static function file(array $state): ?string
    {
        $type = (string) ($state['server_type'] ?? '');
        if ($type !== 'modded') {
            return MinecraftCatalog::eggFile($type, $state['server_software'] ?? null);
        }
        if (!($parts = ModpackCatalog::split($state['modpack_project'] ?? null))) {
            return null;
        }
        return match ($parts[0]) {
            'modrinth' => 'egg-modrinth-generic.json',
            'ftb' => 'egg-ftb-server.json',
            'curseforge' => 'egg-curseforge-generic.json',
            'technic' => match ($parts[1]) {
                'tekkit-2' => 'egg-tekkit2.json',
                'tekkitmain' => 'egg-tekkit.json',
                'tekkit' => 'egg-tekkit-classic.json',
                'tekkit-legends' => 'egg-tekkit-legends.json',
                'tekkit-smp' => 'egg-tekkit-smp.json',
                'hexxit' => 'egg-hexxit.json',
                'blightfall' => 'egg-blightfall.json',
                'attack-of-the-bteam' => 'egg-attack-of-the-b--team.json',
                'the-1122-pack' => 'egg-the1-12-2-pack.json',
                'the-1710-pack' => 'egg-the1-7-10-pack.json',
                default => null,
            },
        };
    }

    public static function ensure(array $state): Egg
    {
        if ($egg = self::existing($state)) {
            return $egg;
        }
        $file = self::file($state);
        if (!$file) {
            throw ValidationException::withMessages(['data.egg_id' => __('pelican-simplify::maker.form.choose_a_supported_server_type_and')]);
        }
        if (!CreatorAccess::canImportEggs()) {
            throw ValidationException::withMessages(['data.egg_id' => __('pelican-simplify::maker.form.the_matching_egg_is_not_installed')]);
        }
        try {
            // Recheck under a shared cache lock so simultaneous wizards reuse one import.
            return Cache::lock('ssc.egg-import.' . hash('sha256', $file), 60)->block(10, function () use ($state, $file): Egg {
                if ($egg = self::existing($state)) {
                    return $egg;
                }
                $root = plugin_path('pelican-simplify', 'resources/eggs');
                $manifest = json_decode((string) file_get_contents($root . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
                $entry = $manifest[$file] ?? null;
                $content = file_get_contents($root . '/' . $file);
                if (!$entry || $content === false || !hash_equals($entry['sha256'], hash('sha256', $content))) {
                    throw new \RuntimeException('The bundled egg definition failed its integrity check.');
                }
                $definition = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                if (($definition['name'] ?? '') !== $entry['name']) {
                    throw new \RuntimeException('The bundled egg name does not match the manifest.');
                }
                // Native import can update a same-UUID egg even if its name was changed.
                // A fresh UUID and explicit new model ensure existing definitions are never updated.
                unset($definition['uuid']);
                $egg = app(EggImporterService::class)->fromContent(
                    json_encode($definition, JSON_THROW_ON_ERROR), EggFormat::JSON, new Egg()
                );
                if ($egg->name !== $entry['name']) {
                    throw new \RuntimeException('The imported egg did not match the expected definition.');
                }
                return $egg;
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['data.egg_id' => __('pelican-simplify::maker.form.this_egg_is_being_imported_by')]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['data.egg_id' => __('pelican-simplify::maker.form.could_not_import_the_required_egg')]);
        }
    }
}
