<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** How the panel name in the top-left corner looks: styled text, or an uploaded logo. Stored with the appearance settings. */
final class BrandSettings
{
    public const LOGO_DIR = 'simplify/branding/';
    public const LOGO_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'];
    public const LOGO_MAX_KB = 1024;

    /** Font choices; only these stacks ever reach a style attribute. */
    public const FONTS = [
        'inter' => ['Inter (default)', 'Inter, ui-sans-serif, system-ui, sans-serif'],
        'system' => ['System', 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif'],
        'rounded' => ['Rounded', 'ui-rounded, "SF Pro Rounded", "Nunito", "Varela Round", system-ui, sans-serif'],
        'serif' => ['Serif', 'ui-serif, Georgia, Cambria, "Times New Roman", serif'],
        'mono' => ['Monospace', 'ui-monospace, "JetBrains Mono", "Cascadia Code", Menlo, Consolas, monospace'],
        'monocraft' => ['Monocraft (pixel)', 'Monocraft, ui-monospace, monospace'],
        'condensed' => ['Condensed', '"Roboto Condensed", "Arial Narrow", "Helvetica Neue", sans-serif'],
        'display' => ['Impact', 'Impact, Haettenschweiler, "Arial Black", sans-serif'],
    ];

    public static function defaults(): array
    {
        return ['mode' => 'text', 'text' => '', 'font' => 'inter', 'weight' => 700, 'color_style' => 'solid', 'color1' => '', 'color2' => '',
            'spacing' => 0, 'uppercase' => false, 'suffix' => true, 'logo' => null, 'logo_height' => 32, 'logo_text' => false];
    }

    public static function rules(): array
    {
        return [
            'mode' => 'required|in:text,logo', 'text' => 'nullable|string|max:40', 'font' => 'required|in:'.implode(',', array_keys(self::FONTS)),
            'weight' => 'required|integer|in:400,500,600,700,800,900', 'color_style' => 'required|in:gradient,solid',
            'color1' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], 'color2' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'spacing' => 'required|integer|min:-5|max:30', 'uppercase' => 'boolean', 'suffix' => 'boolean',
            'logo_height' => 'required|integer|min:16|max:64', 'logo_text' => 'boolean',
        ];
    }

    /** Cleans saved or submitted values; unknown or broken values fall back to the defaults. */
    public static function normalize(mixed $saved, ?string $currentLogo = null): array
    {
        $saved = is_array($saved) ? $saved : [];
        $out = self::defaults();
        foreach (self::rules() as $key => $rule) {
            if (!array_key_exists($key, $saved)) continue;
            $value = $saved[$key];
            if (in_array($key, ['uppercase', 'suffix', 'logo_text'], true)) { $out[$key] = (bool) $value; continue; }
            if (in_array($key, ['weight', 'spacing', 'logo_height'], true)) $value = is_numeric($value) ? (int) $value : null;
            if ($key === 'text') $value = is_string($value) ? trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '') : '';
            if ($value === null) continue;
            if (validator([$key => $value], [$key => $rule])->passes()) $out[$key] = is_string($value) && str_starts_with($value, '#') ? strtolower($value) : $value;
        }
        $logo = $currentLogo ?? ($saved['logo'] ?? null);
        $out['logo'] = is_string($logo) && preg_match('/^logo-[a-f0-9]{16}\.(png|jpg|webp)$/', $logo) && Storage::disk('local')->exists(self::LOGO_DIR.$logo) ? $logo : null;
        if ($out['mode'] === 'logo' && $out['logo'] === null) $out['mode'] = 'text';
        return $out;
    }

    /** What the dashboard needs to draw the brand. */
    public static function forDisplay(array $brand): array
    {
        return [...$brand, 'font_stack' => self::FONTS[$brand['font']][1] ?? self::FONTS['inter'][1],
            'logo_url' => $brand['logo'] ? route('simplify.logo', ['v' => substr($brand['logo'], 5, 8)]) : null];
    }

    /** Stores an uploaded logo after checking it really is a PNG, JPEG or WebP image; returns the new file name. */
    public static function storeLogo(UploadedFile $file): string
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $info = strlen($bytes) <= self::LOGO_MAX_KB * 1024 ? @getimagesizefromstring($bytes) : false;
        $ext = $info ? array_search($info['mime'] ?? '', self::LOGO_TYPES, true) : false;
        if (!$ext || $info[0] < 1 || $info[1] < 1 || $info[0] > 4000 || $info[1] > 4000) {
            throw ValidationException::withMessages(['logoUpload' => 'Use a PNG, JPEG or WebP image up to '.self::LOGO_MAX_KB.' KB.']);
        }
        $name = 'logo-'.bin2hex(random_bytes(8)).'.'.$ext;
        if (!Storage::disk('local')->put(self::LOGO_DIR.$name, $bytes)) throw new \RuntimeException('Could not save the logo.');
        return $name;
    }

    public static function deleteLogoFile(?string $name): void
    {
        if (is_string($name) && preg_match('/^logo-[a-f0-9]{16}\.(png|jpg|webp)$/', $name)) Storage::disk('local')->delete(self::LOGO_DIR.$name);
    }
}
