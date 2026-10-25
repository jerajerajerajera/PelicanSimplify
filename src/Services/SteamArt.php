<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use App\Models\Server;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Steam artwork for game servers. An egg only knows its dedicated-server app, which has no store art, so the
 * game is looked up by name in the public Steam store search (no API key). The banner and icon are copied
 * once into Simplify's own storage, so nothing is fetched from Steam afterwards.
 */
final class SteamArt
{
    private const CDN = 'https://cdn.cloudflare.steamstatic.com/steam/apps/';
    private const ICONS = 'simplify/game-icons/';

    public static function enabled(): bool
    {
        return FeatureSettings::enabled('steam_art');
    }

    /** "Valheim (Plus) Dedicated Server" → "Valheim". */
    public static function searchName(string $eggName): string
    {
        $name = preg_replace('/\s*[\(\[][^\)\]]*[\)\]]\s*/', ' ', $eggName) ?? $eggName;
        $name = preg_replace('/\b(dedicated\s+server|dedicated|server|servers|egg|ds)\b/i', ' ', $name) ?? $name;
        return trim(preg_replace('/\s+/', ' ', $name) ?? $name, " -–:|");
    }

    /** The matching Steam game with its art URLs, or null. Cached for a week (misses for a day). */
    public static function find(string $eggName): ?array
    {
        $term = self::searchName($eggName);
        if (mb_strlen($term) < 2) return null;
        return Remember::value('simplify.steam-art.'.md5(mb_strtolower($term)), 604800, function () use ($term): ?array {
            $items = Http::timeout(4)->connectTimeout(2)->acceptJson()
                ->get('https://store.steampowered.com/api/storesearch/', ['term' => $term, 'cc' => 'us', 'l' => 'en'])
                ->throw()->json('items');
            return self::pick(is_array($items) ? $items : [], $term);
        }, 86400);
    }

    /** Exact name match first, then a result that contains the whole search name. */
    public static function pick(array $items, string $term): ?array
    {
        $norm = static fn ($text): string => preg_replace('/[^a-z0-9]+/', '', mb_strtolower((string) $text)) ?? '';
        $wanted = $norm($term);
        if ($wanted === '') return null;
        $best = null;
        foreach ($items as $item) {
            if (!is_array($item) || !is_numeric($item['id'] ?? null)) continue;
            $name = $norm($item['name'] ?? '');
            if ($name === $wanted) { $best = $item; break; }
            if ($best === null && str_contains($name, $wanted)) $best = $item;
        }
        if ($best === null) return null;
        $id = (int) $best['id'];
        return ['appid' => $id, 'name' => (string) ($best['name'] ?? ''), 'banner' => self::CDN.$id.'/library_hero.jpg',
            'header' => self::CDN.$id.'/header.jpg', 'capsule' => self::CDN.$id.'/library_600x900.jpg'];
    }

    /** Copies the banner (selected for the server) and a square icon. Runs after the creation response. */
    public static function apply(Server $server, array $art): void
    {
        try {
            $banner = self::image([$art['banner'] ?? null, $art['header'] ?? null], 1920);
            if ($banner !== null) {
                $entry = ServerBanners::store($server, $banner, ($art['name'] ?? 'Steam').' (Steam)');
                ServerArtwork::select($server, $entry['id']);
            }
            $icon = self::icon([$art['capsule'] ?? null, $art['header'] ?? null]);
            if ($icon !== null) Storage::disk('local')->put(self::ICONS.$server->uuid.'.png', $icon);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public static function iconBytes(Server $server): ?string
    {
        $disk = Storage::disk('local');
        $path = self::ICONS.$server->uuid.'.png';
        return $disk->exists($path) ? $disk->get($path) : null;
    }

    /** The first URL that downloads as an image, rendered to bytes by $render(image, width, height). */
    private static function firstImage(array $urls, \Closure $render): ?string
    {
        foreach (array_filter($urls) as $url) {
            $source = self::download((string) $url);
            if ($source === null) continue;
            ob_start();
            $render($source, imagesx($source), imagesy($source));
            return ob_get_clean() ?: null;
        }
        return null;
    }

    /** Scaled down to $width as JPEG. */
    private static function image(array $urls, int $width): ?string
    {
        return self::firstImage($urls, function (\GdImage $source, int $w, int $h) use ($width): void {
            $scale = min(1, $width / max(1, $w));
            $target = imagescale($source, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
            imagejpeg($target ?: $source, null, 84);
        });
    }

    /** A 128×128 PNG: the top square of the portrait capsule, else the middle of the header image. */
    private static function icon(array $urls): ?string
    {
        return self::firstImage($urls, function (\GdImage $source, int $w, int $h): void {
            $side = min($w, $h);
            $x = (int) (($w - $side) / 2);
            $y = $h > $w ? 0 : (int) (($h - $side) / 2);
            $icon = imagecreatetruecolor(128, 128);
            imagecopyresampled($icon, $source, 0, 0, $x, $y, 128, 128, $side, $side);
            imagepng($icon, null, 7);
        });
    }

    /** Only Steam's CDN, at most 6 MB, and only real images. */
    private static function download(string $url): ?\GdImage
    {
        if (!str_starts_with($url, self::CDN)) return null;
        try {
            $response = Http::timeout(10)->connectTimeout(3)->withOptions(['allow_redirects' => false])->get($url);
            $bytes = $response->successful() ? $response->body() : '';
            if ($bytes === '' || strlen($bytes) > 6 * 1024 * 1024 || !@getimagesizefromstring($bytes)) return null;
            $image = @imagecreatefromstring($bytes);
            return $image instanceof \GdImage ? $image : null;
        } catch (Throwable) {
            return null;
        }
    }
}
