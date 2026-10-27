<?php
declare(strict_types=1);
// Every Minecraft server type and software in the Server Maker maps to a bundled, checksummed egg it can find again by name.
namespace App\Models { class Egg {} }
namespace Simplify\Panel\Services { class Remember {} }
namespace {
require __DIR__.'/../src/Services/MinecraftCatalog.php';
use Simplify\Panel\Services\MinecraftCatalog as C;
function check($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
$manifest=json_decode((string)file_get_contents(__DIR__.'/../resources/eggs/manifest.json'),true,512,JSON_THROW_ON_ERROR);
$eggs=(new ReflectionClassConstant(C::class,'EGGS'))->getValue();
$pairs=[];foreach(C::TYPES as $type){if($type==='modded')continue;foreach(array_keys(C::SOFTWARE[$type]??[null=>null]) as $software)$pairs[]=[$type,$software===''?null:$software];}
foreach($pairs as [$type,$software]){
  $file=C::eggFile($type,$software);$entry=$manifest[$file]??null;
  check($entry!==null&&hash_equals($entry['sha256'],hash('sha256',(string)file_get_contents(__DIR__.'/../resources/eggs/'.$file))),"bundled egg for $type".($software?" / $software":'').' is in the manifest with a matching checksum');
  $definition=json_decode((string)file_get_contents(__DIR__.'/../resources/eggs/'.$file),true,512,JSON_THROW_ON_ERROR);
  check($definition['name']===$entry['name']&&in_array(mb_strtolower($entry['name']),$eggs[C::software($type,$software)??$type][0],true),"imported $entry[name] egg is found again by name");
}
check(C::software('plus',null)==='paper'&&C::software('plus','purpur')==='purpur'&&C::software('plus','spigot')==='paper'&&C::software('vanilla','paper')===null,'software falls back to the type default and only exists where offered');
check(C::eggFile('modded')===null&&C::eggFile('unknown')===null,'modpacks pick their egg per provider; unknown types have none');
check(C::usesVersion('loader')&&!C::usesVersion('bedrock')&&!C::usesVersion('proxy')&&!C::usesVersion('modded'),'only vanilla, plugins and mods take a chosen Minecraft version');
check(C::javaServer('modded')&&!C::javaServer('bedrock')&&!C::javaServer('proxy'),'Bedrock and proxies have no Java server properties or EULA');
check(C::isMinecraftEgg('Forge Minecraft')&&C::isMinecraftEgg('Vanilla Bedrock')&&C::isMinecraftEgg('Velocity')&&!C::isMinecraftEgg('Valheim')&&!C::isMinecraftEgg('Rust'),'Minecraft eggs stay out of the other-games list');
}
