<?php
namespace Illuminate\Support\Facades {
class Cache {public static array $store=[];public static function get($k){return self::$store[$k]??null;}public static function put($k,$v,$t=null){self::$store[$k]=$v;return true;}}
class Log {public static array $warnings=[];public static function warning($m){self::$warnings[]=$m;}}
class Http {public static array $responses=[];public static array $asked=[];
    public static function timeout($s){return new self;}public function connectTimeout($s){return $this;}public function acceptJson(){return $this;}
    public function get($url){self::$asked[]=$url;$body=self::$responses[$url]??null;return new class($body){public function __construct(private $body){}public function status(){return $this->body===null?404:($this->body==='500'?500:200);}public function throw(){if($this->status()!==200)throw new \RuntimeException('HTTP '.$this->status());return $this;}public function json(){return json_decode($this->body,true);}public function body(){return $this->body;}};}}
}
namespace {
require __DIR__.'/../src/Services/Remember.php';
require __DIR__.'/../src/Services/ReleaseNotes.php';
use Simplify\Panel\Services\ReleaseNotes as R;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
function check($condition,$message){if(!$condition)throw new \Exception($message);}

// A GitHub release body as Pelican and Wings write them: generated "What's Changed" lists with authors and PR links.
$body="## What's Changed\r\n* Fix **backups** on S3 by @lancepioch in https://github.com/pelican-dev/panel/pull/1800\r\n* Add [server transfers](https://pelican.dev/docs/transfers) to the admin area by @rmartinoscar in https://github.com/pelican-dev/panel/pull/1801\r\n* `php artisan p:upgrade` keeps your .env\r\n<!-- hidden -->\r\n![screenshot](https://x/y.png)\r\n\r\n## New Contributors\r\n* @someone made their first contribution\r\n\r\n**Full Changelog**: https://github.com/pelican-dev/panel/compare/v1.0.0...v1.0.1";
$lines=R::summarize($body);
check($lines[0]==='Fix backups on S3'&&$lines[1]==='Add server transfers to the admin area'&&$lines[2]==='php artisan p:upgrade keeps your .env','list items without markup, links, authors or PR links');
check(!array_filter($lines,fn($l)=>str_contains($l,'http')||str_contains($l,'Full Changelog')||str_contains($l,'@')),'no URLs, no Full Changelog line, no mentions');

// Simplify's CHANGELOG: the section of exactly that version, intro sentence first.
$changelog="# Changes\n\n## 0.5.3-snapshot.6 (snapshot channel)\nNotifications show everything now.\n- The bell lists all notifications.\n- Mark all read marks every notification read.\n\n## 0.5.3-snapshot.5 (snapshot channel)\nLess scrolling.\n- Older.\n";
check(R::section($changelog,'0.5.3-snapshot.5')==="Less scrolling.\n- Older.\n",'the section of that version only');
check(R::section($changelog,'0.5.3-snapshot')===null,'no partial version matches');
check(R::summarize((string)R::section($changelog,'0.5.3-snapshot.6'))===['Notifications show everything now.','The bell lists all notifications.','Mark all read marks every notification read.'],'intro sentence, then the list');
check(count(R::summarize(str_repeat("- line\n",20)))===R::LINES,'at most '.R::LINES.' lines');
check(mb_strlen(R::summarize('- '.str_repeat('a',400))[0])===160,'long lines are cut with an ellipsis');

// Fetching: GitHub release by tag, CHANGELOG next to a raw update file; cached, misses too.
Http::$responses['https://api.github.com/repos/pelican-dev/panel/releases/tags/v1.0.1']=json_encode(['body'=>$body,'html_url'=>'https://github.com/pelican-dev/panel/releases/tag/v1.0.1']);
$notes=R::release('pelican-dev/panel','1.0.1');
check($notes['url']==='https://github.com/pelican-dev/panel/releases/tag/v1.0.1'&&$notes['lines'][0]==='Fix backups on S3','release notes with a link to the release');
R::release('pelican-dev/panel','1.0.1');check(count(Http::$asked)===1,'read once per version');
check(R::release('pelican-dev/wings','9.9.9')===null&&R::release('pelican-dev/wings','9.9.9')===null&&count(Http::$asked)===2,'a missing release is not asked again right away');
check(R::release('../evil','1.0.0')===null&&count(Http::$asked)===2,'only owner/repository names');
Http::$responses['https://raw.githubusercontent.com/jerajerajerajera/PelicanSimplify/snapshot/CHANGELOG.md']=$changelog;
$own=R::changelog('https://raw.githubusercontent.com/jerajerajerajera/PelicanSimplify/snapshot/update.json','0.5.3-snapshot.6','https://github.com/x');
check($own['lines'][0]==='Notifications show everything now.'&&$own['url']==='https://github.com/x','Simplify reads the CHANGELOG of its channel branch');
check(R::changelog('https://hub.pelican.dev/plugins/x/update.json','1.0.0')===null,'other update hosts have no CHANGELOG to read');
check(Log::$warnings===[],'a missing release is not an error');
Http::$responses['https://api.github.com/repos/pelican-dev/wings/releases/tags/v1.0.2']='500';
check(R::release('pelican-dev/wings','1.0.2')===null&&count(Log::$warnings)===1&&str_contains(Log::$warnings[0],'HTTP 500'),'a failed fetch is logged');

// Checksums of release files are left out, also under their own heading.
$wings="## What's Changed\n* 19dda386e02041da1f1fbcc73f34e4bb01c1fbc2d4aa  wings_linux_amd64\n* Servers are no longer marked as stopped\n\n### SHA256 Checksums\n* wings_linux_arm64\n";
check(R::summarize($wings)===['Servers are no longer marked as stopped'],'no checksum lines');

// A beta's section points to the snapshot sections below it; those are its notes, up to the previous beta.
$beta="## 0.5.3-beta.8 (beta channel)\nEverything from the snapshots (0.5.3-snapshot.11 below).\n\n## 0.5.3-snapshot.11 (snapshot channel)\nInstall view.\n- Kill stops the install.\n\n## 0.5.3-snapshot.10 (snapshot channel)\nMore AIs.\n- Claude, GPT or Gemini.\n\n## 0.5.3-beta.7 (beta channel)\nOlder.\n- Not this one.\n";
check(R::summarize((string)R::section($beta,'0.5.3-beta.8'))===['Everything from the snapshots.','Kill stops the install.','Claude, GPT or Gemini.'],'beta notes include its snapshots');

// Plugins: CHANGELOG next to a GitHub update file, else the release of their GitHub page.
Http::$responses['https://api.github.com/repos/someone/cool-plugin/releases/tags/v2.0.0']=json_encode(['body'=>"* Cooler",'html_url'=>'https://github.com/someone/cool-plugin/releases/tag/v2.0.0']);
check(R::forPlugin('https://hub.pelican.dev/plugins/cool/update.json','https://github.com/someone/cool-plugin','2.0.0')['lines']===['Cooler'],'a Hub plugin with a GitHub page');
check(R::forPlugin(null,'https://example.com/plugin','2.0.0')===null,'no GitHub, no notes');
echo "PASS release notes: checksums skipped, beta sections with their snapshots, plugins via their GitHub page, failures logged, GitHub release bodies and CHANGELOG sections as a few plain lines, links and authors removed, cached per version, misses too\n";
}
