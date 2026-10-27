<?php
declare(strict_types=1);
// Browse Hub: the Hub's list becomes cards with the maker up front, a short plain-text summary, credit for original
// authors and only https screenshots; makers are ranked by how much they made.
require __DIR__.'/../src/Services/HubCatalog.php';
use Simplify\Panel\Services\HubCatalog as H;
$check=function(bool $ok,string $what){if(!$ok)throw new \RuntimeException($what);};

$nord=['slug'=>'nord-theme','title'=>'Nord Theme','author_name'=>'boy132','type'=>'theme','pricing_type'=>'free','downloads_count'=>89,'latest_version'=>'1.0.0','last_version_at'=>'2026-05-29T08:32:43+00:00',
 'description'=>"A light and dark arctic Nord theme.\n\n_Originally created by [Andréia Bohner](https://github.com/andreia) as [Filament Theme](https://github.com/andreia/filament-nord-theme), under MIT License._"];
$detail=['screenshots'=>['https://peli.nyc3.digitaloceanspaces.com/plugin-screenshots/a.png','http://insecure/b.png','javascript:alert(1)'],'license'=>null];
$card=H::normalize($nord,$detail);
$check($card['url']==='https://hub.pelican.dev/plugins/nord-theme'&&$card['author']==='boy132'&&$card['downloads']===89,'basic fields: '.json_encode($card));
$check($card['summary']==='A light and dark arctic Nord theme.','summary skips the credit line: '.$card['summary']);
$check($card['based']===['name'=>'Andréia Bohner','url'=>'https://github.com/andreia'],'original author credited');
$check($card['screenshots']===['https://peli.nyc3.digitaloceanspaces.com/plugin-screenshots/a.png'],'only https screenshots');
$check($card['updated']==='2026-05-29'&&$card['license']===null,'date and empty license');

$readme="# Catppuccin Mocha Theme for Pelican\n\n[![Release](https://img.shields.io/x)](https://github.com/x)\n\nA dark-only theme using the **Catppuccin** [Mocha](https://catppuccin.com) palette.\n\n## Features\n- Foo";
$check(H::summary($readme)==='A dark-only theme using the Catppuccin Mocha palette. Foo','markdown to text: '.H::summary($readme));
$long=str_repeat('Word ',60);
$check(mb_strlen(H::summary($long))<=161&&str_ends_with(H::summary($long),'…'),'long text cut at a word');

$check(H::normalize(['slug'=>'../evil','type'=>'theme'])===null,'bad slug skipped');
$check(H::normalize(['slug'=>'paid','type'=>'plugin','pricing_type'=>'premium'])===null,'premium skipped (nothing to install for free)');
$check(H::normalize(['slug'=>'odd','type'=>'egg'])===null,'unknown type skipped');
$check(H::normalize(['slug'=>'x','type'=>'plugin','title'=>'<b>X</b>','author_name'=>''])['title']==='X'&&H::normalize(['slug'=>'x','type'=>'plugin'])['author']==='Unknown maker','text cleaned');

$items=H::build(['list'=>[$nord,['slug'=>'fluffy-theme','type'=>'theme','author_name'=>'boy132','downloads_count'=>40],['slug'=>'deepfield','type'=>'theme','author_name'=>'grvtoofly','downloads_count'=>79],
  ['slug'=>'player-counter','type'=>'plugin','author_name'=>'boy132','downloads_count'=>990],$nord],'details'=>['nord-theme'=>$detail]]);
$check(count($items)===4&&$items[0]['screenshots']!==[],'duplicates merged, details joined');
$makers=H::makers($items,'theme');
$check(array_column($makers,'name')===['boy132','grvtoofly']&&$makers[0]['count']===2&&$makers[0]['downloads']===129,'makers ranked: '.json_encode($makers));
$check(array_column(H::makers($items,'plugin'),'count')===[1],'makers per type');
echo "hub-catalog ok\n";
