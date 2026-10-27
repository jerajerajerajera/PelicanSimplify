<?php
namespace Simplify\Panel\Services;

/**
 * Simplify's texts live in lang/<locale>/<group>.php and follow the language Pelican sets for the user.
 * A missing text falls back to English, so a half-finished language still works.
 */
final class Translations
{
    public const NAMESPACE = 'pelican-simplify';
    public const FALLBACK = 'en';

    /** One group for the current locale, with English filling every missing key. */
    public static function group(string $group, ?string $locale = null): array
    {
        $key = self::NAMESPACE.'::'.$group;
        $english = trans($key, [], self::FALLBACK);
        $local = trans($key, [], $locale ?? app()->getLocale());
        return self::merge(is_array($english) ? $english : [], is_array($local) ? $local : []);
    }

    /** Texts for the JavaScript bundles (window.simplifyLang). */
    public static function js(): array
    {
        return self::group('js');
    }

    /** English with every translated, non-empty text laid over it; keys English does not have are ignored. */
    public static function merge(array $english, array $local): array
    {
        foreach ($english as $key => $text) {
            if (!array_key_exists($key, $local)) continue;
            if (is_array($text) && is_array($local[$key])) $english[$key] = self::merge($text, $local[$key]);
            elseif (is_string($text) && is_string($local[$key]) && trim($local[$key]) !== '') $english[$key] = $local[$key];
        }
        return $english;
    }

    /** Placeholder names (:name) in a text, sorted, for comparing a translation with English. */
    public static function placeholders(string $text): array
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $text, $found);
        $names = array_values(array_unique($found[1]));
        sort($names);
        return $names;
    }

    /** Problems in one translated group compared with English: unknown keys and placeholders that differ. */
    public static function audit(array $english, array $local, string $prefix = ''): array
    {
        $problems = [];
        foreach ($local as $key => $text) {
            $path = $prefix.$key;
            if (!array_key_exists($key, $english)) { $problems[] = $path.': not in English'; continue; }
            if (is_array($english[$key]) !== is_array($text)) { $problems[] = $path.': different shape than English'; continue; }
            if (is_array($text)) { array_push($problems, ...self::audit($english[$key], $text, $path.'.')); continue; }
            if (!is_string($text)) { $problems[] = $path.': not text'; continue; }
            if (self::placeholders($text) !== self::placeholders((string) $english[$key])) $problems[] = $path.': placeholders differ from English';
            if (strip_tags($text) !== $text && strip_tags((string) $english[$key]) === (string) $english[$key]) $problems[] = $path.': adds HTML';
        }
        return $problems;
    }
}
