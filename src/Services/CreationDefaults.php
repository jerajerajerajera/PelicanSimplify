<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
final class CreationDefaults
{
    public const FILE = 'simplify/creation-defaults.json';
    public static function defaults(): array
    {
        return ['vanilla'=>['cpu'=>100,'ram_gb'=>4,'disk_mb'=>10240],
            'plus'=>['cpu'=>150,'ram_gb'=>4,'disk_mb'=>10240],
            'modded'=>['cpu'=>200,'ram_gb'=>4,'disk_mb'=>20480]];
    }
    public static function all(): array
    {
        $defaults = self::defaults();
        $saved = JsonFile::read(self::FILE);
        foreach ($defaults as $type => &$profile) foreach ($profile as $key => &$value) {
                $candidate = $saved[$type][$key] ?? null;
                $max = ['cpu'=>1600,'ram_gb'=>128,'disk_mb'=>10485760][$key];
                $min = $key === 'disk_mb' ? 1024 : 1;
                if (is_int($candidate) && $candidate >= $min && $candidate <= $max) $value = $candidate;
        }
        unset($profile, $value);
        return $defaults;
    }
    public static function forType(string $type): array
    {
        $profiles = self::all();
        return $profiles[$type] ?? $profiles['vanilla'];
    }
    public static function save(array $input): void
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $rules = [];
        foreach (array_keys(self::defaults()) as $type) {
            $rules[$type.'.cpu'] = 'required|integer|min:1|max:1600';
            $rules[$type.'.ram_gb'] = 'required|integer|min:1|max:128';
            $rules[$type.'.disk_mb'] = 'required|integer|min:1024|max:10485760';
        }
        validator($input, $rules)->validate();
        $next = [];
        foreach (self::defaults() as $type => $profile) foreach ($profile as $key => $_) $next[$type][$key] = (int) $input[$type][$key];
        Cache::lock('simplify.creation-defaults', 10)->block(3, function()use($next):void {
            if (!Storage::disk('local')->put(self::FILE, json_encode($next, JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save server creation defaults.');
        });
    }
}
