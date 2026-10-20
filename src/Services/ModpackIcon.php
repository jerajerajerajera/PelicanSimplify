<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/** The modpack's own icon as a Minecraft server icon: the centre square scaled to a 64 × 64 PNG. */
final class ModpackIcon
{
    public static function png(string $url): ?string
    {
        if (ModpackCatalog::safeUrl($url) === null) return null;
        try {
            $response = Http::timeout(10)->connectTimeout(3)
                ->withOptions(['allow_redirects' => ['max' => 2, 'protocols' => ['https']]])
                ->withHeaders(['User-Agent' => 'PelicanSimplify (Pelican plugin)'])->get($url);
            $bytes = $response->successful() ? $response->body() : '';
        } catch (Throwable) {
            return null;
        }
        return self::fromBytes($bytes);
    }

    public static function fromBytes(string $bytes): ?string
    {
        if ($bytes === '' || strlen($bytes) > 6 * 1024 * 1024 || !@getimagesizefromstring($bytes)) return null;
        $source = @imagecreatefromstring($bytes);
        if (!$source instanceof \GdImage) return null;
        [$w, $h] = [imagesx($source), imagesy($source)];
        $side = min($w, $h);
        $icon = imagecreatetruecolor(64, 64);
        imagealphablending($icon, false);
        imagesavealpha($icon, true);
        imagefill($icon, 0, 0, imagecolorallocatealpha($icon, 0, 0, 0, 127));
        imagecopyresampled($icon, $source, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), 64, 64, $side, $side);
        ob_start();
        imagepng($icon, null, 9);
        $png = ob_get_clean() ?: null;
        return $png !== null && strlen($png) <= 102400 ? $png : null;
    }
}
