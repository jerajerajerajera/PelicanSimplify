<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use App\Models\Egg;
use Illuminate\Support\Facades\Http;

final class MinecraftCatalog
{
    /** Server types in the order the creator shows them. */
    public const TYPES = ['vanilla', 'plus', 'loader', 'modded', 'bedrock', 'proxy'];

    /** The software to pick within a type; the first one is the default. */
    public const SOFTWARE = [
        'plus' => ['paper' => 'Paper', 'purpur' => 'Purpur'],
        'loader' => ['fabric' => 'Fabric', 'forge' => 'Forge', 'neoforge' => 'NeoForge', 'quilt' => 'Quilt'],
        'proxy' => ['velocity' => 'Velocity', 'bungeecord' => 'BungeeCord'],
    ];

    /** Egg names to look for on the panel, and the bundled definition imported when none is there. */
    private const EGGS = [
        'vanilla' => [['vanilla minecraft', 'minecraft vanilla', 'vanilla'], 'egg-vanilla-minecraft.json'],
        'paper' => [['paper'], 'egg-paper.json'],
        'purpur' => [['purpur'], 'egg-purpur.json'],
        'fabric' => [['fabric'], 'egg-fabric.json'],
        'forge' => [['forge minecraft', 'forge'], 'egg-forge.json'],
        'neoforge' => [['neoforge'], 'egg-neoforge.json'],
        'quilt' => [['quilt'], 'egg-quilt.json'],
        'bedrock' => [['vanilla bedrock', 'bedrock'], 'egg-vanilla-bedrock.json'],
        'velocity' => [['velocity'], 'egg-velocity.json'],
        'bungeecord' => [['bungeecord'], 'egg-bungeecord.json'],
        'modded' => [['modrinth generic', 'modrinth'], null],
    ];

    /** The chosen software when the type offers one, falling back to the type's default. */
    public static function software(string $type, ?string $software): ?string
    {
        $choices = self::SOFTWARE[$type] ?? null;
        if (!$choices) return null;
        return isset($choices[(string) $software]) ? (string) $software : array_key_first($choices);
    }

    private static function flavour(string $type, ?string $software): ?string
    {
        if (!in_array($type, self::TYPES, true)) return null;
        return self::software($type, $software) ?? $type;
    }

    /** Types that run a chosen Minecraft Java release (the others pick their own version). */
    public static function usesVersion(string $type): bool
    {
        return in_array($type, ['vanilla', 'plus', 'loader'], true);
    }

    /** Java servers with Minecraft's own server.properties and EULA; Bedrock and proxies have neither. */
    public static function javaServer(string $type): bool
    {
        return !in_array($type, ['bedrock', 'proxy'], true);
    }

    /** Eggs the Minecraft part of the creator covers, so the other-games list does not offer them twice. */
    public static function isMinecraftEgg(string $name): bool
    {
        // Whole words, so games such as Arma Reforger or Paperboy stay in the list.
        return preg_match('/\\b(minecraft|vanilla|paper|purpur|spigot|bukkit|folia|sponge\\w*|modrinth|curseforge|ftb|feed the beast|tekkit\\w*|hexxit|blightfall|b-team|forge|neoforge|fabric|quilt|bedrock|pocketmine(-mp)?|nukkit|velocity|bungeecord|waterfall)\\b/i', $name) === 1;
    }

    public static function eggForType(string $type, ?string $software = null): ?Egg
    {
        foreach (self::EGGS[self::flavour($type, $software) ?? ''][0] ?? [] as $needle) {
            $egg = Egg::query()->whereRaw('LOWER(name) = ?', [$needle])->first();
            if ($egg) {
                return $egg;
            }
        }

        return null;
    }

    /** The bundled egg file for a type, or null when it has none (modpacks pick theirs per provider). */
    public static function eggFile(string $type, ?string $software = null): ?string
    {
        return self::EGGS[self::flavour($type, $software) ?? ''][1] ?? null;
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
        } elseif (self::usesVersion($type)) {
            foreach (['MINECRAFT_VERSION', 'VANILLA_VERSION', 'MC_VERSION'] as $key) {
                if (array_key_exists($key, $env)) {
                    $env[$key] = (string) $minecraftVersion;
                }
            }
        }

        return $env;
    }
}
