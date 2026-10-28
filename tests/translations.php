<?php
declare(strict_types=1);
require __DIR__.'/../src/Services/Translations.php';
use Simplify\Panel\Services\Translations as T;
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo 'PASS '.$message."\n"; }
$en = ['save' => 'Save', 'hello' => 'Hello :name', 'nested' => ['a' => 'A', 'b' => 'B']];
check(T::merge($en, ['save' => 'Opslaan', 'nested' => ['a' => 'Een']]) === ['save' => 'Opslaan', 'hello' => 'Hello :name', 'nested' => ['a' => 'Een', 'b' => 'B']], 'missing texts fall back to English, nested groups too');
check(T::merge($en, ['save' => '  ', 'extra' => 'X'])['save'] === 'Save' && !array_key_exists('extra', T::merge($en, ['extra' => 'X'])), 'empty translations and unknown keys are ignored');
check(T::placeholders(':count servers on :node, :count total') === ['count', 'node'], 'placeholders are found once and sorted');
check(T::audit($en, ['hello' => 'Hallo :naam'])[0] === 'hello: placeholders differ from English', 'a renamed placeholder is reported');
check(T::audit($en, ['gone' => 'Weg']) === ['gone: not in English'], 'a key English does not have is reported');
check(T::audit($en, ['save' => '<b>Opslaan</b>']) === ['save: adds HTML'], 'HTML English does not use is reported');
check(T::audit(['n' => ':count item|:count items'], ['n' => ':count stuk'], '', 'nl') === ['n: plural forms differ from English'], 'a missing plural form is reported');
check(T::audit(['n' => '{0} None|{1} One|[2,*] :count'], ['n' => ':count сервер|:count сервера|:count серверов'], '', 'ru') === [] && T::audit(['n' => 'one|many'], ['n' => '件'], '', 'ja') === [], 'languages use their own number of plural forms');
check(T::audit($en, ['nested' => 'plat']) === ['nested: different shape than English'], 'a flattened group is reported');

// Every shipped language: same files as English, nothing unknown, placeholders intact.
$lang = __DIR__.'/../lang';
check(is_dir($lang.'/en'), 'English texts exist');
$groups = array_map('basename', glob($lang.'/en/*.php'));
foreach (glob($lang.'/*', GLOB_ONLYDIR) as $dir) {
    $locale = basename($dir);
    check((bool) preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $locale), "$locale is a locale name Pelican uses");
    foreach (glob($dir.'/*.php') as $file) {
        check(in_array(basename($file), $groups, true), "$locale/".basename($file).' has an English original');
        $problems = T::audit(require $lang.'/en/'.basename($file), require $file, '', $locale);
        check($problems === [], "$locale/".basename($file).' matches English'.($problems ? ': '.implode('; ', $problems) : ''));
    }
}
// Every key the code asks for exists in English: t('group.key') in the JavaScript bundles (js.php) and
// 'pelican-simplify::file.key' in PHP and Blade.
$get = function (array $texts, string $path) { foreach (explode('.', $path) as $part) { if (!is_array($texts) || !array_key_exists($part, $texts)) return null; $texts = $texts[$part]; } return $texts; };
$english = require $lang.'/en/js.php';
foreach (glob(__DIR__.'/../resources/views/js/*.js') as $file) {
    preg_match_all("/\\b(?:t|tc|sscText|Simplify\\.t|Simplify\\.tc)\\('([a-z0-9_]+(?:\\.[A-Za-z0-9_]+)+)'/", file_get_contents($file), $used);
    $missing = array_values(array_filter(array_unique($used[1]), fn ($key) => !is_string($get($english, $key))));
    check($missing === [], basename($file).' only uses texts that exist in English'.($missing ? ': '.implode(', ', $missing) : ''));
}
$sources = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/..', FilesystemIterator::SKIP_DOTS));
$missing = [];
foreach ($sources as $file) {
    if (!str_ends_with($file->getFilename(), '.php') || preg_match('#/(lang|tests|vendor|dist|node_modules)/#', $file->getPathname())) continue;
    preg_match_all("/pelican-simplify::([a-z0-9_-]+)\\.([A-Za-z0-9_.]+)'/", file_get_contents($file->getPathname()), $used, PREG_SET_ORDER);
    foreach ($used as [, $group, $key]) {
        $texts = is_file($lang."/en/$group.php") ? require $lang."/en/$group.php" : [];
        if (!is_string($get($texts, $key))) $missing[] = "$group.$key in ".basename($file->getPathname());
    }
}
check($missing === [], 'PHP and Blade only use texts that exist in English'.($missing ? ': '.implode(', ', array_unique($missing)) : ''));
