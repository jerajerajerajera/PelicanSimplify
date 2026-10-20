<?php
namespace Illuminate\Support\Facades { class Http { public static bool $called=false; public static function __callStatic($m,$a){ self::$called=true; throw new \RuntimeException('no network in tests'); } } }
namespace {
require __DIR__.'/../src/Services/ModpackCatalog.php';
require __DIR__.'/../src/Services/ModpackIcon.php';
use Simplify\Panel\Services\ModpackIcon;
$image=imagecreatetruecolor(300,200);imagefill($image,0,0,imagecolorallocate($image,200,40,40));ob_start();imagejpeg($image);$jpeg=ob_get_clean();
$png=ModpackIcon::fromBytes($jpeg);
assert_ok($png!==null,'A wide JPEG becomes an icon');
$size=getimagesizefromstring($png);
assert_ok($size[0]===64&&$size[1]===64&&$size[2]===IMAGETYPE_PNG,'The icon is a 64 x 64 PNG');
assert_ok(strlen($png)<=102400,'The icon stays within 100 KB');
assert_ok(ModpackIcon::fromBytes('not an image')===null,'Non-images are ignored');
assert_ok(ModpackIcon::fromBytes('')===null,'Empty downloads are ignored');
assert_ok(ModpackIcon::png('http://example.com/icon.png')===null&&!\Illuminate\Support\Facades\Http::$called,'Only https URLs are fetched');
assert_ok(ModpackIcon::png('https://example.com/icon.png')===null,'Download failures return no icon');
$job=file_get_contents(__DIR__.'/../src/Jobs/ApplyServerProperties.php');
assert_ok(str_contains($job,"(\$this->settings['icon_if_missing'] ?? false) && \$this->hasFile(\$files, 'server-icon.png')"),'A server-icon.png shipped by the pack is kept');
$creator=file_get_contents(__DIR__.'/../src/Filament/Admin/Pages/CreateMinecraftServer.php');
assert_ok(str_contains($creator,"(\$state['icon_source'] ?? 'modpack') === 'modpack'"),'The modpack icon is preselected');
assert_ok(str_contains($creator,"if (\$modpackIcon !== null) \$state['server_icon'] = null;"),'Choosing the modpack icon ignores an earlier upload');
assert_ok(str_contains($creator,"if (!\$offered && \$get('icon_source') === 'upload') return false;"),'Choosing to upload shows the upload field');
$card=file_get_contents(__DIR__.'/../resources/views/filament/admin/card-radio.blade.php');
assert_ok(str_contains($card,'data:image/png;base64,{{ $modpackIcon }}'),'The wizard previews the downloaded modpack icon');
echo "PASS modpack icon becomes a 64 x 64 PNG, https only, failures ignored, preselected in the wizard with upload as alternative, shipped icons kept\n";
function assert_ok(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FAIL $message\n");exit(1);}}
}
