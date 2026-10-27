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
    /** Plural forms per language as Laravel picks them (trans_choice); every other language has two (one|many). */
    public const PLURAL_FORMS = ['ar' => 6, 'be' => 3, 'bs' => 3, 'cs' => 3, 'hr' => 3, 'lt' => 3, 'pl' => 3, 'ro' => 3, 'ru' => 3, 'sk' => 3, 'sl' => 4, 'sr' => 3, 'uk' => 3,
        'id' => 1, 'ja' => 1, 'ko' => 1, 'tr' => 1, 'vi' => 1, 'zh' => 1];

    /** One group for the current locale, with English filling every missing key. */
    public static function group(string $group, ?string $locale = null): array
    {
        $key = self::NAMESPACE.'::'.$group;
        $english = trans($key, [], self::FALLBACK);
        $local = trans($key, [], $locale ?? app()->getLocale());
        return self::merge(is_array($english) ? $english : [], is_array($local) ? $local : []);
    }

    /**
     * Option labels kept as English constants in code (for validation), translated by key: $path.'.'.$key in
     * lang/<locale>/<file>.php, with the constant's own English when a text is missing.
     */
    public static function labels(string $path, array $english): array
    {
        $out = [];
        foreach ($english as $key => $text) $out[$key] = self::text($path.'.'.$key, $text);
        return $out;
    }

    /** Rows such as [label, help, default] of an English constant; $fields names the translated positions ($path.'.'.$key.'.'.$field). */
    public static function rows(string $path, array $english, array $fields = ['label', 'help']): array
    {
        foreach ($english as $key => $row) {
            foreach ($fields as $index => $field) {
                if (isset($row[$index]) && is_string($row[$index])) $english[$key][$index] = self::text($path.'.'.$key.'.'.$field, $row[$index]);
            }
        }
        return $english;
    }

    /** One text by key, or the English given when there is none (for labels that also serve as ids, such as tab names). */
    public static function text(string $path, string $english, array $replace = []): string
    {
        $name = self::NAMESPACE.'::'.$path;
        $translated = function_exists('__') ? __($name, $replace) : $name;
        if (is_string($translated) && $translated !== $name) return $translated;
        foreach ($replace as $placeholder => $value) $english = str_replace(':'.$placeholder, (string) $value, $english);
        return $english;
    }

    /** Key for an English label: lower-case words joined by underscores ("Users & access" becomes users_access). */
    public static function key(string $english): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($english)), '_');
    }

    /** The language Pelican set for this request; English outside a full Laravel app. */
    public static function locale(): string
    {
        try { return (string) app()->getLocale() ?: self::FALLBACK; } catch (\Throwable) { return self::FALLBACK; }
    }

    /** Runs $callback in $user's language (Pelican's language setting), for texts sent to someone else such as notifications. */
    public static function as(?object $user, callable $callback): mixed
    {
        $previous = self::locale();
        try {
            $locale = (string) ($user->language ?? '') ?: (string) config('app.locale', self::FALLBACK);
            app()->setLocale($locale);
        } catch (\Throwable) {
            return $callback();
        }
        try { return $callback(); } finally { app()->setLocale($previous); }
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

    /** How many plural forms a 'one|many' text needs in $locale (pt_BR follows pt, zh_TW follows zh). */
    public static function pluralForms(string $locale): int
    {
        return self::PLURAL_FORMS[$locale] ?? self::PLURAL_FORMS[explode('_', $locale)[0]] ?? 2;
    }

    /**
     * Whether a translated plural text fits: the same {0}/[2,*] conditions as English, or plain forms in the
     * number $locale needs (a language with three forms may drop English's {0} and [2,*]).
     */
    public static function pluralFits(string $english, string $text, ?string $locale): bool
    {
        if (!str_contains($english, '|')) return true;
        $conditions = fn (string $s) => preg_match_all('/(?:^|\|)\s*(\{\d+\}|\[[^\]]*\])/', $s, $found) ? $found[1] : [];
        $parts = substr_count($text, '|') + 1;
        if ($parts === substr_count($english, '|') + 1 && $conditions($text) === $conditions($english)) return true;
        return $locale !== null && $conditions($text) === [] && $parts === self::pluralForms($locale);
    }

    /** Problems in one translated group compared with English: unknown keys, placeholders and plural forms that differ. */
    public static function audit(array $english, array $local, string $prefix = '', ?string $locale = null): array
    {
        $problems = [];
        foreach ($local as $key => $text) {
            $path = $prefix.$key;
            if (!array_key_exists($key, $english)) { $problems[] = $path.': not in English'; continue; }
            if (is_array($english[$key]) !== is_array($text)) { $problems[] = $path.': different shape than English'; continue; }
            if (is_array($text)) { array_push($problems, ...self::audit($english[$key], $text, $path.'.', $locale)); continue; }
            if (!is_string($text)) { $problems[] = $path.': not text'; continue; }
            if (self::placeholders($text) !== self::placeholders((string) $english[$key])) $problems[] = $path.': placeholders differ from English';
            if (!self::pluralFits((string) $english[$key], $text, $locale)) $problems[] = $path.': plural forms differ from English';
            if (strip_tags($text) !== $text && strip_tags((string) $english[$key]) === (string) $english[$key]) $problems[] = $path.': adds HTML';
        }
        return $problems;
    }
}
