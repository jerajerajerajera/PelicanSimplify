<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The panel's own icon for the installed web app and its app notifications. An uploaded image is turned into every
 * size once, at upload: transparent icons for the manifest, a filled tile for iPhone and Android's maskable shape,
 * and a white-on-transparent badge for Android's status bar. Without uploads Simplify's own icons are used.
 */
final class AppIcon
{
    public const FILE = 'simplify/app-icon.json';
    public const DIR = 'simplify/branding/';
    public const TYPES = ['image/png', 'image/jpeg', 'image/webp'];
    public const MAX_KB = 4096;
    public const MIN_ICON = 512;
    public const MIN_BADGE = 48;
    public const BADGE = 96;
    /** Generated files per icon: manifest sizes, the maskable tile and the filled 180 px tile for iPhone. */
    public const VARIANTS = ['180', '192', '512', 'maskable'];

    /** ['icon' => id|null, 'badge' => id|null, 'badge_from_icon' => bool]; ids name the generated files. */
    public static function current(): array
    {
        $saved = JsonFile::read(self::FILE);
        $id = fn (mixed $value) => is_string($value) && preg_match('/^[a-f0-9]{16}$/', $value) ? $value : null;
        return ['icon' => $id($saved['icon'] ?? null), 'badge' => $id($saved['badge'] ?? null), 'badge_from_icon' => ($saved['badge_from_icon'] ?? false) === true];
    }

    /** Storage path of a generated file, or null when Simplify's own icon is used. */
    public static function path(string $variant): ?string
    {
        $current = self::current();
        if ($variant === 'badge') return $current['badge'] ? self::DIR.'app-badge-'.$current['badge'].'.png' : null;
        return $current['icon'] && in_array($variant, self::VARIANTS, true) ? self::DIR.'app-icon-'.$current['icon'].'-'.$variant.'.png' : null;
    }

    /** Changes whenever an icon is uploaded or removed, so installed apps and the service worker fetch it again. */
    public static function version(): string
    {
        $current = self::current();
        return ($current['icon'] ?? '-').($current['badge'] ?? '-');
    }

    public static function storeIcon(UploadedFile $file): void
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $image = self::open($file, 'appIconUpload', self::MIN_ICON);
        $id = bin2hex(random_bytes(8));
        foreach (self::icons($image) as $variant => $png) self::put(self::DIR.'app-icon-'.$id.'-'.$variant.'.png', $png);
        $old = self::current();
        $next = ['icon' => $id, 'badge' => $old['badge'], 'badge_from_icon' => $old['badge_from_icon']];
        // A transparent logo also makes a good badge, unless one was uploaded on purpose.
        if ($old['badge'] === null || $old['badge_from_icon']) {
            $badge = self::hasTransparency($image) ? self::badge($image) : null;
            $next['badge'] = $badge !== null ? bin2hex(random_bytes(8)) : null;
            $next['badge_from_icon'] = $badge !== null;
            if ($badge !== null) self::put(self::DIR.'app-badge-'.$next['badge'].'.png', $badge);
        }
        imagedestroy($image);
        self::save($next, $old);
    }

    public static function storeBadge(UploadedFile $file): void
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $image = self::open($file, 'appBadgeUpload', self::MIN_BADGE);
        if (!self::hasTransparency($image)) {
            imagedestroy($image);
            throw ValidationException::withMessages(['appBadgeUpload' => __('pelican-simplify::settings.app_icon.badge_needs_transparency')]);
        }
        $id = bin2hex(random_bytes(8));
        self::put(self::DIR.'app-badge-'.$id.'.png', self::badge($image));
        imagedestroy($image);
        $old = self::current();
        self::save(['icon' => $old['icon'], 'badge' => $id, 'badge_from_icon' => false], $old);
    }

    /** Removes the icon ("icon") or the badge ("badge"); removing the icon also removes a badge made from it. */
    public static function remove(string $kind): void
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $old = self::current();
        $next = $old;
        if ($kind === 'icon') {
            $next['icon'] = null;
            if ($old['badge_from_icon']) $next = [...$next, 'badge' => null, 'badge_from_icon' => false];
        } else {
            $next = [...$next, 'badge' => null, 'badge_from_icon' => false];
        }
        self::save($next, $old);
    }

    /** The generated PNGs (as bytes) for one source image, by variant. */
    public static function icons(\GdImage $image): array
    {
        $out = [];
        foreach ([180, 192, 512] as $size) $out[(string) $size] = $size === 180 ? self::tile($image, 180, 0.8) : self::png(self::fit($image, $size, 1.0, null));
        // Android cuts maskable icons to a circle or squircle; the safe zone is the middle 80 % circle.
        $out['maskable'] = self::tile($image, 512, 0.66);
        return $out;
    }

    /** White shape on transparent, from the image's own transparency (Android only uses the alpha channel). */
    public static function badge(\GdImage $image): string
    {
        $fit = self::fit($image, self::BADGE, 0.92, null);
        $out = self::canvas(self::BADGE, null);
        for ($y = 0; $y < self::BADGE; $y++) {
            for ($x = 0; $x < self::BADGE; $x++) {
                $alpha = (imagecolorat($fit, $x, $y) >> 24) & 0x7f;
                if ($alpha < 127) imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, 255, 255, 255, $alpha));
            }
        }
        imagedestroy($fit);
        return self::png($out);
    }

    public static function hasTransparency(\GdImage $image): bool
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $step = max(1, (int) floor(min($w, $h) / 64));
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) if (((imagecolorat($image, $x, $y) >> 24) & 0x7f) > 64) return true;
        }
        return false;
    }

    /** A filled square: a full-bleed image as it is, a transparent logo centred on a light or dark background. */
    private static function tile(\GdImage $image, int $size, float $scale): string
    {
        if (!self::hasTransparency($image)) return self::png(self::fit($image, $size, 1.0, null));
        return self::png(self::fit($image, $size, $scale, self::isLight($image) ? [24, 24, 27] : [255, 255, 255]));
    }

    /** The image scaled to fit a square of $size × $scale, centred on a square canvas. */
    private static function fit(\GdImage $image, int $size, float $scale, ?array $background): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $box = (int) round($size * $scale);
        $ratio = min($box / $w, $box / $h);
        $tw = max(1, (int) round($w * $ratio));
        $th = max(1, (int) round($h * $ratio));
        $canvas = self::canvas($size, $background);
        imagecopyresampled($canvas, $image, intdiv($size - $tw, 2), intdiv($size - $th, 2), 0, 0, $tw, $th, $w, $h);
        return $canvas;
    }

    private static function canvas(int $size, ?array $background): \GdImage
    {
        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, $background ? imagecolorallocate($canvas, ...$background) : imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, $background !== null);
        return $canvas;
    }

    /** True when the visible pixels are mostly light (a white logo then gets a dark background). */
    private static function isLight(\GdImage $image): bool
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $step = max(1, (int) floor(min($w, $h) / 64));
        $sum = 0.0;
        $count = 0;
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $c = imagecolorat($image, $x, $y);
                if ((($c >> 24) & 0x7f) > 64) continue;
                $sum += (0.2126 * (($c >> 16) & 0xff) + 0.7152 * (($c >> 8) & 0xff) + 0.0722 * ($c & 0xff)) / 255;
                $count++;
            }
        }
        return $count > 0 && $sum / $count > 0.7;
    }

    private static function png(\GdImage $image): string
    {
        imagesavealpha($image, true);
        ob_start();
        imagepng($image, null, 9);
        imagedestroy($image);
        return (string) ob_get_clean();
    }

    /** Checks the upload really is an image of at least $min px on both sides; returns it as a true-colour image. */
    private static function open(UploadedFile $file, string $field, int $min): \GdImage
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $info = strlen($bytes) <= self::MAX_KB * 1024 ? @getimagesizefromstring($bytes) : false;
        $image = $info && in_array($info['mime'] ?? '', self::TYPES, true) && $info[0] >= $min && $info[1] >= $min && $info[0] <= 4096 && $info[1] <= 4096
            ? @imagecreatefromstring($bytes) : false;
        if (!$image) throw ValidationException::withMessages([$field => __('pelican-simplify::settings.app_icon.invalid', ['min' => $min, 'size' => self::MAX_KB / 1024])]);
        if (!imageistruecolor($image)) imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        return $image;
    }

    private static function put(string $path, string $bytes): void
    {
        if (!Storage::disk('local')->put($path, $bytes)) throw new \RuntimeException('Could not save the app icon.');
    }

    /** Saves the new state and deletes files that are no longer used. */
    private static function save(array $next, array $old): void
    {
        Cache::lock('simplify.app-icon', 10)->block(3, function () use ($next): void {
            if (!Storage::disk('local')->put(self::FILE, json_encode($next, JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save the app icon.');
        });
        $disk = Storage::disk('local');
        if ($old['icon'] && $old['icon'] !== $next['icon']) foreach (self::VARIANTS as $variant) $disk->delete(self::DIR.'app-icon-'.$old['icon'].'-'.$variant.'.png');
        if ($old['badge'] && $old['badge'] !== $next['badge']) $disk->delete(self::DIR.'app-badge-'.$old['badge'].'.png');
    }
}
