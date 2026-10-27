<?php
// English lookups for tests that load plugin code without Laravel: __('pelican-simplify::file.key', [...]) and trans_choice().
namespace {
    require_once __DIR__.'/../../src/Services/Translations.php';
    if (!function_exists('__')) {
        function __($key = null, $replace = [], $locale = null)
        {
            if (!is_string($key) || !str_starts_with($key, 'pelican-simplify::')) return $key;
            [$file, $path] = array_pad(explode('.', substr($key, 18), 2), 2, null);
            static $files = [];
            $files[$file] ??= is_file($f = __DIR__.'/../../lang/en/'.$file.'.php') ? require $f : [];
            $value = $files[$file];
            foreach ($path === null ? [] : explode('.', $path) as $part) {
                if (!is_array($value) || !array_key_exists($part, $value)) return $key;
                $value = $value[$part];
            }
            if (!is_string($value)) return $value;
            foreach ($replace as $name => $text) $value = str_replace(':'.$name, (string) $text, $value);
            return $value;
        }
    }
    if (!function_exists('trans_choice')) {
        function trans_choice($key, $number, array $replace = [], $locale = null)
        {
            $forms = explode('|', (string) __($key));
            $count = is_countable($number) ? count($number) : (int) $number;
            $text = $forms[0];
            foreach ($forms as $form) {
                if (preg_match('/^\{(\d+)\}\s*(.*)$/s', $form, $m) && (int) $m[1] === $count) { $text = $m[2]; break; }
                if (preg_match('/^\[(\d+),(\*|\d+)\]\s*(.*)$/s', $form, $m) && $count >= (int) $m[1] && ($m[2] === '*' || $count <= (int) $m[2])) { $text = $m[3]; break; }
            }
            if (count($forms) === 2 && !preg_match('/^[\{\[]/', $forms[0])) $text = $count === 1 ? $forms[0] : $forms[1];
            foreach (['count' => $count] + $replace as $name => $value) $text = str_replace(':'.$name, (string) $value, $text);
            return $text;
        }
    }
}
