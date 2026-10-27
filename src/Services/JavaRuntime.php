<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use App\Models\Egg;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

final class JavaRuntime
{
    public static function minecraftVersion(array $state): ?string
    {
        $type = (string) ($state['server_type'] ?? '');
        if ($type !== 'modded') {
            return MinecraftCatalog::usesVersion($type) || $type === '' ? ($state['minecraft_version'] ?? null) : null;
        }
        return ModpackCatalog::runtime($state['modpack_project'] ?? null, (string) ($state['modpack_version'] ?? ''))['minecraft'] ?? null;
    }

    public static function fallbackMajor(string $minecraft, string $type): int
    {
        if (version_compare($minecraft, '26.1', '>=')) {
            return 25;
        }
        if ($type === 'plus') {
            return match (true) {
                version_compare($minecraft, '1.20', '>=') => 21,
                version_compare($minecraft, '1.17', '>=') => 17,
                version_compare($minecraft, '1.16.5', '>=') => 16,
                version_compare($minecraft, '1.12', '>=') => 11,
                default => 8,
            };
        }
        return match (true) {
            version_compare($minecraft, '1.20.5', '>=') => 21,
            version_compare($minecraft, '1.18', '>=') => 17,
            version_compare($minecraft, '1.17', '>=') => 16,
            default => 8,
        };
    }

    public static function major(array $state): ?int
    {
        $selected = (string) ($state['java_version'] ?? 'auto');
        if ($selected !== 'auto') {
            return in_array($selected, ['8', '11', '16', '17', '21', '25'], true) ? (int) $selected : null;
        }
        // Proxies run on any current Java; Bedrock is no Java server at all.
        if (($state['server_type'] ?? '') === 'proxy') {
            return 21;
        }
        if (($state['server_type'] ?? '') === 'modded') {
            $packJava = ModpackCatalog::runtime($state['modpack_project'] ?? null, (string) ($state['modpack_version'] ?? ''))['java'] ?? null;
            if (in_array($packJava, [8, 11, 16, 17, 21, 25], true)) {
                return $packJava;
            }
        }
        $minecraft = self::minecraftVersion($state);
        if (!$minecraft) {
            return null;
        }
        $type = (string) ($state['server_type'] ?? 'vanilla');
        // Paper publishes its own recommendations, which can exceed Vanilla's minimum.
        if ($type === 'plus') {
            return self::fallbackMajor($minecraft, $type);
        }
        try {
            return Cache::remember('ssc.java.mojang.' . $minecraft, 21600, function () use ($minecraft, $type): int {
                try {
                    $manifest = Http::timeout(6)->get('https://piston-meta.mojang.com/mc/game/version_manifest_v2.json');
                    if ($manifest->successful()) {
                        foreach ($manifest->json('versions', []) as $release) {
                            if (($release['id'] ?? '') !== $minecraft) {
                                continue;
                            }
                            $url = (string) ($release['url'] ?? '');
                            if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'piston-meta.mojang.com') {
                                break;
                            }
                            $metadata = Http::timeout(6)->get($url);
                            $value = $metadata->successful() ? $metadata->json('javaVersion.majorVersion') : null;
                            if (is_numeric($value) && (int) $value >= 8 && (int) $value <= 99) {
                                return (int) $value;
                            }
                            break;
                        }
                    }
                } catch (Throwable) {
                    // Cache the known-release fallback too, avoiding repeated timeout delays.
                }
                return self::fallbackMajor($minecraft, $type);
            });
        } catch (Throwable) {
            // Known releases still work if the cache is temporarily unavailable.
        }
        return self::fallbackMajor($minecraft, $type);
    }

    public static function imageForMajor(array $images, int $major): string
    {
        foreach ($images as $label => $image) {
            if (!is_string($image)) {
                continue;
            }
            // Prefer the image tag over a possibly stale friendly name.
            $pattern = '/(?:java|jdk|jre)[-_ ]?(\d+)(?=$|[^0-9])/i';
            if (preg_match($pattern, $image, $match)) {
                if ((int) $match[1] === $major) {
                    return $image;
                }
                continue;
            }
            if (preg_match($pattern, (string) $label, $match) && (int) $match[1] === $major) {
                return $image;
            }
        }
        return 'ghcr.io/pterodactyl/yolks:java_' . $major;
    }

    public static function image(array $state, ?Egg $egg): ?string
    {
        $override = trim((string) ($state['image'] ?? ''));
        if ($override !== '') {
            return $override;
        }
        if (($state['server_type'] ?? '') === 'bedrock') {
            $images = array_values(array_filter((array) ($egg?->docker_images ?? []), 'is_string'));
            return $images[0] ?? null;
        }
        $major = self::major($state);
        return $major === null ? null : self::imageForMajor((array) ($egg?->docker_images ?? []), $major);
    }

    public static function requireImage(array $state, Egg $egg): string
    {
        return self::image($state, $egg) ?? throw ValidationException::withMessages([
            'data.modpack_version' => 'Could not determine the modpack Java version. Retry or choose Java manually in Advanced > Edit settings.',
        ]);
    }
}
