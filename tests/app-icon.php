<?php
declare(strict_types=1);
// App icon uploads: every generated file has the right size, a transparent logo stays transparent in the manifest
// icons but gets a filled tile for iPhone and Android's maskable shape, and the badge is white on transparent.
require __DIR__.'/../src/Services/AppIcon.php';
use Simplify\Panel\Services\AppIcon;
$check=function(bool $ok,string $what){if(!$ok)throw new \RuntimeException($what);};
$read=fn(string $png)=>imagecreatefromstring($png);
$alpha=fn(\GdImage $image,int $x,int $y)=>(imagecolorat($image,$x,$y)>>24)&0x7f;
$rgb=fn(\GdImage $image,int $x,int $y)=>imagecolorat($image,$x,$y)&0xffffff;

// A dark red circle on a transparent, wide canvas (a logo), and a full-bleed blue square (an app icon design).
$logo=imagecreatetruecolor(800,600);imagealphablending($logo,false);imagesavealpha($logo,true);
imagefill($logo,0,0,imagecolorallocatealpha($logo,0,0,0,127));
imagefilledellipse($logo,400,300,500,500,imagecolorallocate($logo,120,0,0));
$tile=imagecreatetruecolor(600,600);imagefill($tile,0,0,imagecolorallocate($tile,0,80,200));

$check(AppIcon::hasTransparency($logo)&&!AppIcon::hasTransparency($tile),'transparency is detected');

$icons=AppIcon::icons($logo);
$check(array_map('strval',array_keys($icons))===AppIcon::VARIANTS,'all variants are made');
foreach(['180'=>180,'192'=>192,'512'=>512,'maskable'=>512] as $variant=>$size){
    $info=getimagesizefromstring($icons[$variant]);
    $check($info[0]===$size&&$info[1]===$size&&$info['mime']==='image/png',"$variant is a $size px PNG");
}
$check($alpha($read($icons['512']),2,2)===127&&$alpha($read($icons['512']),256,256)===0,'manifest icon keeps the transparent background and the logo');
$mask=$read($icons['maskable']);
$check($alpha($mask,2,2)===0&&$rgb($mask,2,2)===0xffffff,'maskable tile is filled white behind a dark logo');
$check(($rgb($mask,256,256)>>16)>100&&$alpha($mask,256,256)===0,'maskable tile has the logo in the middle');
$check($rgb($mask,256,40)===0xffffff,'maskable logo stays inside the safe zone');
$check($alpha($read($icons['180']),2,2)===0,'the iPhone icon has no transparency (iOS would turn it black)');

// A white logo gets a dark tile, a full-bleed icon is used as it is.
$white=imagecreatetruecolor(512,512);imagealphablending($white,false);imagesavealpha($white,true);
imagefill($white,0,0,imagecolorallocatealpha($white,0,0,0,127));imagefilledrectangle($white,100,100,411,411,imagecolorallocate($white,250,250,250));
$check($rgb($read(AppIcon::icons($white)['maskable']),2,2)===0x18181b,'white logo gets a dark tile');
$check($rgb($read(AppIcon::icons($tile)['maskable']),2,2)===0x0050c8,'full-bleed icon fills the maskable tile itself');

// Badge: white shape, transparent elsewhere, 96 px.
$badge=$read(AppIcon::badge($logo));
$check(imagesx($badge)===AppIcon::BADGE&&imagesy($badge)===AppIcon::BADGE,'badge is 96 px');
$check($alpha($badge,48,48)===0&&$rgb($badge,48,48)===0xffffff,'badge shape is white');
$check($alpha($badge,1,1)===127,'badge background is transparent');
echo "PASS app icon sizes, maskable tile, iPhone tile and white badge\n";
