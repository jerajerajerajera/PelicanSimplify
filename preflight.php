<?php
declare(strict_types=1);
$panel = rtrim($argv[1] ?? '/var/www/pelican', '/');
try {
    require $panel.'/vendor/autoload.php';
    require_once __DIR__.'/src/Services/UploadLimits.php';
    require_once __DIR__.'/src/Services/DependencyCheck.php';
    $checks = \Simplify\Panel\Services\DependencyCheck::inspect($panel, __DIR__, false, true);
    foreach ($checks as $check) echo ($check['ok'] ? 'PASS ' : (($check['blocking'] ?? true) ? 'FAIL ' : 'WARN ')).$check['name'].($check['ok'] ? '' : ': '.$check['fix'])."\n";
    \Simplify\Panel\Services\DependencyCheck::assertReady($checks);
} catch (\Throwable $error) { fprintf(STDERR, "%s\n", $error->getMessage()); exit(1); }
