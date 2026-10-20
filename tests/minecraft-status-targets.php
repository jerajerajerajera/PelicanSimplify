<?php
namespace App\Models { if (!class_exists(Server::class)) { class Server {} } }
namespace {
require __DIR__.'/../src/Services/MinecraftStatus.php';
use Simplify\Panel\Services\MinecraftStatus as S;
$check=function($ok,$what){if(!$ok)throw new RuntimeException('Minecraft status target regression: '.$what);};
$check(S::targets('100.72.58.65','play.example.com','node.example.com')===['100.72.58.65'],'specific IP is used as-is');
$check(S::targets('0.0.0.0','play.example.com','node.example.com')===['127.0.0.1','play.example.com','node.example.com'],'wildcard tries loopback, alias, node');
$check(S::targets('0.0.0.0','Minecraft servers','node.example.com')===['127.0.0.1','node.example.com'],'label alias is skipped');
$check(S::targets('::','','node.example.com')===['::1','node.example.com'],'IPv6 wildcard uses ::1');
$check(S::targets('0.0.0.0',null,'100.72.58.65')===['127.0.0.1','100.72.58.65'],'IP node host still tried');
$check(S::targets('0.0.0.0','203.0.113.5','')===['127.0.0.1','203.0.113.5'],'IP alias accepted');
echo "PASS Minecraft status pings loopback, alias and node for wildcard allocations\n";
}
