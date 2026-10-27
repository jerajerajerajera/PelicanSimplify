<?php
require_once __DIR__.'/fixtures/lang.php';
require __DIR__.'/../src/Services/UploadLimits.php';
require __DIR__.'/../src/Services/DependencyCheck.php';
use Simplify\Panel\Services\UploadLimits as U;
use Simplify\Panel\Services\DependencyCheck as D;
function check($ok){if(!$ok)throw new RuntimeException('Upload limit regression');}
check(U::bytes('2M')===2097152&&U::bytes('1.5G')===1610612736&&U::bytes('512k')===524288);
check(U::calculate('2M','8M','file')['banner_bytes']===2097152);
check(U::calculate('10M','12M','file|max:1024')['banner_bytes']===1048576);
check(U::calculate('10M','1M',['file','max:10240'])['banner_bytes']===1048576-65536);
check(U::calculate('20M','0','file')['banner_bytes']===5242880);
check(U::calculate('10M','12M','file',false)['banner_bytes']===0);
check(D::ready([['ok'=>true],['ok'=>false,'blocking'=>false]]));
check(!D::ready([['ok'=>false],['ok'=>false,'blocking'=>false]]));
D::assertReady([['ok'=>true],['ok'=>false,'blocking'=>false]]);
check(count(U::checks())===2&&U::checks()[1]['blocking']===false);
echo "PASS PHP and Livewire limits, multipart overhead, disabled uploads, banner cap and nonblocking proxy warnings\n";
