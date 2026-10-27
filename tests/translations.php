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
        $problems = T::audit(require $lang.'/en/'.basename($file), require $file);
        check($problems === [], "$locale/".basename($file).' matches English'.($problems ? ': '.implode('; ', $problems) : ''));
    }
}
// English is the source: JS reads each key it uses from there.
$js = file_get_contents(__DIR__.'/../resources/views/js/core.js');
preg_match_all("/\\bt\\('([a-z0-9_]+)'/", $js, $used);
$english = require $lang.'/en/js.php';
foreach (array_unique($used[1]) as $key) check(isset($english[$key]), "core.js text $key exists in English");
