<?php
declare(strict_types=1);
// Plugin search: results of Modrinth, Hangar and SpigotMC become one list with one card per plugin; "Install" picks the
// newest release made for the server's version, or the newest at all marked as not made for it; GitHub release files
// and download hosts are checked.
require __DIR__.'/../src/Services/PluginSources.php';
use Simplify\Panel\Services\PluginSources as P;
$check=function(bool $ok,string $what){if(!$ok)throw new \RuntimeException($what);};

$check(P::fits(['1.21.1'],'1.21.4',false)&&!P::fits(['1.21.1'],'1.21.4',true)&&P::fits(['26.1'],'26.1.2',false)&&!P::fits(['1.20.6'],'1.21.4',false)&&!P::fits(['1.21'],'',false),'version lines');
$urls=P::searchUrls('world edit','paper','plugins');
$check(array_keys($urls)===['modrinth','hangar','spigot']&&str_contains($urls['spigot'],'/search/resources/world%20edit'),'paper searches three sites');
$check(array_keys(P::searchUrls('','spigot','plugins'))===['modrinth','spigot'],'spigot servers skip Hangar (Paper only)');
$check(array_keys(P::searchUrls('sodium','fabric','mods'))===['modrinth'],'mods search Modrinth');

// Merge: same plugin on several sites is one card from the preferred site with a release for this version.
$results=[
 'modrinth'=>['hits'=>[['project_id'=>'AAA','title'=>'WorldEdit','description'=>'Edit','icon_url'=>'https://cdn.modrinth.com/i.png','author'=>'EngineHub','downloads'=>10,'slug'=>'worldedit','project_type'=>'plugin','versions'=>['1.21.4']],
   ['project_id'=>'BBB','title'=>'OldThing','versions'=>['1.16.5'],'downloads'=>999999]]],
 'hangar'=>['result'=>[['name'=>'WorldEdit','namespace'=>['owner'=>'EngineHub','slug'=>'WorldEdit'],'stats'=>['downloads'=>500],'supportedPlatforms'=>['PAPER'=>['1.21.4']],'avatarUrl'=>'javascript:x'],
   ['name'=>'Bad','namespace'=>['owner'=>'a/b','slug'=>'x']]]],
 'spigot'=>[['id'=>13932,'name'=>'World Edit','tag'=>'t','downloads'=>9000,'testedVersions'=>['1.21']],['id'=>1,'name'=>'Paid','premium'=>true]],
];
$cards=P::merge($results,'worldedit','1.21.4','plugins');
$check(count($cards)===2,'one card per plugin, premium and invalid skipped: '.json_encode(array_column($cards,'key')));
$check($cards[0]['key']==='modrinth:AAA'&&array_column($cards[0]['also'],'key')===['hangar:WorldEdit','spigot:13932']&&$cards[0]['downloads']===9000,'modrinth card with hangar and spigot under also');
$check($cards[1]['key']==='modrinth:BBB'&&$cards[1]['fits']===false,'not made for this version comes after');
$check(P::merge(['hangar'=>$results['hangar']],'','1.21.4','plugins')[0]['icon']==='','non-https icons dropped');

$check(P::parseKey('spigot:123')===['spigot','123']&&P::parseKey('spigot:abc')===null&&P::parseKey('hangar:../x')===null&&P::parseKey('evil:x')===null,'keys');

// Modrinth: newest release for this version, else newest release with fits=false.
$mr=[['id'=>'v3','version_number'=>'3.0','version_type'=>'release','game_versions'=>['1.21.5'],'files'=>[['filename'=>'x-3.0.jar','url'=>'https://cdn.modrinth.com/x3.jar','primary'=>true]]],
     ['id'=>'v2','version_number'=>'2.0','version_type'=>'release','game_versions'=>['1.21.4'],'files'=>[['filename'=>'x-2.0-sources.jar','url'=>'https://cdn.modrinth.com/s.jar'],['filename'=>'x-2.0.jar','url'=>'https://cdn.modrinth.com/x2.jar','primary'=>true]],'dependencies'=>[['dependency_type'=>'required','project_id'=>'DEP']]]];
$r=P::choose('modrinth','AAA',$mr,null,'1.21.4','mods');
$check($r['installable']&&$r['version']==='2.0'&&$r['filename']==='x-2.0.jar'&&$r['fits']&&$r['needs']===['DEP'],'modrinth exact for mods: '.json_encode($r));
$r=P::choose('modrinth','AAA',$mr,null,'1.21.4','plugins');
$check($r['version']==='3.0'&&$r['fits'],'plugins accept the same version line');
$r=P::choose('modrinth','AAA',$mr,null,'1.20.1','plugins');
$check($r['version']==='3.0'&&!$r['fits']&&$r['installable']&&$r['for']==='1.21.5','no release for 1.20.1: newest, not fitting');
$check(!P::choose('modrinth','AAA',null,null,'1.21.4','plugins')['installable'],'site down');

// Hangar: Release channel first; external GitHub link handed to the release lookup.
$hangar=['result'=>[['name'=>'2.1-beta','channel'=>['name'=>'Beta'],'platformDependencies'=>['PAPER'=>['1.21.4']],'downloads'=>['PAPER'=>['downloadUrl'=>'https://hangarcdn.papermc.io/b.jar','fileInfo'=>['name'=>'B.jar']]]],
  ['name'=>'2.0','channel'=>['name'=>'Release'],'platformDependencies'=>['PAPER'=>['1.21.1']],'downloads'=>['PAPER'=>['externalUrl'=>'https://github.com/Owner/Repo/releases/tag/2.0']],'pluginDependencies'=>['PAPER'=>[['name'=>'Vault','required'=>true],['name'=>'Opt','required'=>false]]]]]];
$r=P::choose('hangar','X',$hangar,null,'1.21.4','plugins');
$check($r['version']==='2.0'&&!$r['installable']&&$r['github']==='https://api.github.com/repos/Owner/Repo/releases/tags/2.0'&&$r['need_names']===['Vault'],'hangar release channel, github lookup: '.json_encode($r));
$r=P::fromRelease($r,['tag_name'=>'2.0','assets'=>[['name'=>'EssentialsXChat-2.0.jar','browser_download_url'=>'https://github.com/Owner/Repo/releases/download/2.0/EssentialsXChat-2.0.jar'],['name'=>'EssentialsX-2.0.jar','browser_download_url'=>'https://github.com/Owner/Repo/releases/download/2.0/EssentialsX-2.0.jar'],['name'=>'E-2.0-sources.jar','browser_download_url'=>'https://github.com/Owner/Repo/releases/download/2.0/E-2.0-sources.jar']]]);
$check($r['installable']&&$r['filename']==='EssentialsX-2.0.jar'&&$r['via']==='GitHub'&&!isset($r['github']),'main jar of the release: '.json_encode($r));
$check(!P::fromRelease(['installable'=>false,'reason'=>''],['assets'=>[['name'=>'x.zip','browser_download_url'=>'https://github.com/a/b/releases/download/1/x.zip']]])['installable'],'no jar in release');
$check(P::releaseApi('https://github.com/a/b/releases')==='https://api.github.com/repos/a/b/releases/latest'&&P::releaseApi('https://example.com/a/b/releases')===null,'release links');

// SpigotMC: newest version only, file name built from name and version, external non-GitHub stays on the website.
$r=P::choose('spigot','9089',['name'=>'Essentials X!','file'=>['type'=>'.jar'],'version'=>['id'=>5],'testedVersions'=>['1.20','1.21']],['name'=>'2.21.0'],'1.21.4','plugins');
$check($r['installable']&&$r['filename']==='Essentials-X-2.21.0.jar'&&$r['fits']&&$r['for']==='1.20 – 1.21','spigot: '.json_encode($r));
$r=P::choose('spigot','1',['name'=>'Ext','file'=>['type'=>'external','externalUrl'=>'https://example.com/dl'],'testedVersions'=>['1.8']],null,'1.21.4','plugins');
$check(!$r['installable']&&!isset($r['github'])&&str_contains($r['reason'],'own website')&&!$r['fits'],'external download stays a website link');

$check(P::downloadable('https://cdn.modrinth.com/a.jar','a.jar')&&!P::downloadable('http://cdn.modrinth.com/a.jar','a.jar')&&!P::downloadable('https://evil.test/a.jar','a.jar')
  &&!P::downloadable('https://github.com/a/b/archive/x.jar','x.jar')&&!P::downloadable('https://cdn.modrinth.com/a.jar','../a.jar')&&!P::downloadable('https://cdn.modrinth.com/a.zip','a.zip'),'download hosts and names');
$check(P::fromFilename('LuckPerms-Bukkit-5.4.141.jar')===['LuckPerms Bukkit','5.4.141']&&P::fromFilename('Chunky.jar.disabled')===['Chunky','']&&P::fromFilename('worldedit-bukkit-7.3.10.jar')===['worldedit bukkit','7.3.10'],'names from files');
echo "PASS plugin search merges Modrinth, Hangar and SpigotMC into one card per plugin, picks the release for the server's version or marks it not made for it, GitHub release jars, download hosts and file names\n";
