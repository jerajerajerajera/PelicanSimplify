<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use App\Models\Egg;
use Illuminate\Support\Facades\Http;

final class MinecraftCatalog
{
    public static function eggForType(string $type): ?Egg
    {
        $needles = match ($type) {
            'vanilla' => ['vanilla minecraft', 'minecraft vanilla', 'vanilla'],
            'plus' => ['paper'],
            'modded' => ['modrinth generic', 'modrinth'],
            default => [],
        };

        foreach ($needles as $needle) {
            $egg = Egg::query()->whereRaw('LOWER(name) = ?', [$needle])->first();
            if ($egg) {
                return $egg;
            }
        }

        return null;
    }

    /** @return array<string,string> */
    public static function versions(): array
    {
        // Failures are remembered too (10 minutes), so an unreachable Mojang never stalls the creator.
        $releases = Remember::value('ssc.minecraft.releases.v2', 21600, function (): ?array {
            $response = Http::timeout(4)->connectTimeout(2)->get('https://piston-meta.mojang.com/mc/game/version_manifest_v2.json');
            if (!$response->successful()) return null;
            $releases = collect($response->json('versions', []))
                ->filter(fn ($item) => ($item['type'] ?? '') === 'release')
                ->take(70)
                ->pluck('id', 'id')
                ->all();
            return $releases ?: null;
        }, 600);
        return $releases ?: self::fallbackVersions();
    }

    /** @return array<string,string> */
    private static function fallbackVersions(): array
    {
        return array_combine(
            ['1.21.3', '1.21.1', '1.20.6', '1.20.4', '1.20.1', '1.19.4', '1.18.2', '1.16.5'],
            ['1.21.3', '1.21.1', '1.20.6', '1.20.4', '1.20.1', '1.19.4', '1.18.2', '1.16.5']
        );
    }







    /** @return array<string,string> */
    public static function eggVariables(Egg $egg, string $type, ?string $minecraftVersion, ?string $projectId, ?string $versionId): array
    {
        $env = [];
        foreach ($egg->variables as $variable) {
            $env[(string) $variable->env_variable] = (string) ($variable->default_value ?? '');
        }

        if ($type === 'modded') {
            if (array_key_exists('PROJECT_ID', $env)) {
                $env['PROJECT_ID'] = (string) $projectId;
            }
            if (array_key_exists('VERSION_ID', $env)) {
                $env['VERSION_ID'] = (string) $versionId;
            }
        } else {
            foreach (['MINECRAFT_VERSION', 'VANILLA_VERSION', 'MC_VERSION'] as $key) {
                if (array_key_exists($key, $env)) {
                    $env[$key] = (string) $minecraftVersion;
                }
            }
        }

        return $env;
    }
}
