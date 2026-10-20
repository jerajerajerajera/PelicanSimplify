<?php
declare(strict_types=1);
require __DIR__.'/../src/Services/DependencyCheck.php';
use Simplify\Panel\Services\DependencyCheck as D;
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo 'PASS '.$message."\n"; }
check(D::nodeSupported('v22.12.0') && D::nodeSupported('v24.0.0') && !D::nodeSupported('v22.11.0') && !D::nodeSupported('missing'), 'Node boundary and missing executable are enforced');
// Committed build fixture: panel.css and panel.js have content, empty.css is empty.
$root = __DIR__.'/fixtures/build';
$manifest = ['resources/css/panel.css'=>['file'=>'assets/panel.css'], 'resources/js/panel.js'=>['file'=>'assets/panel.js']];
$withCss = fn (string $file) => ['resources/css/panel.css'=>['file'=>$file], 'resources/js/panel.js'=>$manifest['resources/js/panel.js']];
check(D::assetErrors($manifest, $root, array_keys($manifest)) === [], 'complete compiled CSS and JS pass');
check(D::assetErrors(null, $root) !== [], 'missing manifest blocks setup');
check(D::assetErrors($manifest, $root, ['resources/css/missing.css']) !== [], 'stale manifest missing a source entry blocks setup');
check(D::assetErrors($withCss('assets/missing.css'), $root) !== [], 'manifest pointing at missing CSS blocks setup');
check(D::assetErrors($withCss('assets/empty.css'), $root) !== [], 'empty compiled CSS blocks setup');
check(D::assetErrors(['css'=>['file'=>'../outside.css'],'js'=>$manifest['resources/js/panel.js']], $root) !== [], 'traversal paths are rejected');
check(D::assetErrors(['js'=>['file'=>'assets/panel.js','imports'=>'bad']], $root) !== [], 'malformed imports and missing CSS are rejected');
check(D::assetErrors(['js'=>['file'=>'assets/panel.js','imports'=>['missing']],'css'=>$manifest['resources/css/panel.css']], $root) !== [], 'missing imported chunks are rejected');
$ready = [['name'=>'CSS', 'ok'=>true, 'fix'=>'']];
D::assertReady($ready); check(D::ready($ready), 'successful checks allow progress');
$failed = [['name'=>'CSS', 'ok'=>false, 'fix'=>'Rebuild assets.']];
try { D::assertReady($failed); throw new RuntimeException('accepted failed CSS'); }
catch (RuntimeException $error) { check(str_contains($error->getMessage(), 'installation blocked') && str_contains($error->getMessage(), 'Rebuild assets.'), 'failed dependencies stop installation with repair instructions'); }
check(!D::ready([]), 'an unperformed check never passes');
