<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Http;
/**
 * "What's new" for the Panel's update announcements: a few plain lines from a GitHub release (Pelican, Wings) or from a
 * CHANGELOG.md section (Simplify and plugins that publish on GitHub). Fetched by the scheduled update check only and
 * kept a day per version (a failed fetch is tried again after an hour), so a page load never waits for GitHub.
 */
final class ReleaseNotes
{
    /** Lines kept per update; the bell shows the first three. */
    public const LINES=8;
    private const LINE_LENGTH=160;

    /** Release notes of a GitHub release (tag v<version>), or null. @return array{lines: list<string>, url: ?string}|null */
    public static function release(string $repository,string $version): ?array
    {
        if(!preg_match('#^\w[\w.-]*/\w[\w.-]*$#',$repository)||!preg_match('/^[0-9A-Za-z.+-]{1,40}$/',$version))return null;
        return Remember::value('simplify.release-notes.'.$repository.'.'.$version,86400,function()use($repository,$version):?array{
            $data=Http::timeout(4)->connectTimeout(2)->acceptJson()->get('https://api.github.com/repos/'.$repository.'/releases/tags/v'.$version)->throw()->json();
            if(!is_array($data)||!is_string($data['body']??null))return null;
            $lines=self::summarize($data['body']);
            $url=is_string($data['html_url']??null)&&str_starts_with($data['html_url'],'https://github.com/')?$data['html_url']:null;
            return $lines?['lines'=>$lines,'url'=>$url]:null;
        },3600);
    }

    /** The CHANGELOG.md section of a version next to a raw.githubusercontent.com update file, or null. */
    public static function changelog(string $updateUrl,string $version,?string $pageUrl=null): ?array
    {
        if(!preg_match('#^https://raw\.githubusercontent\.com/(\w[\w.-]*)/(\w[\w.-]*)/(\w[\w./-]*?)/[^/]+$#',$updateUrl,$m)||!preg_match('/^[0-9A-Za-z.+-]{1,40}$/',$version))return null;
        $file='https://raw.githubusercontent.com/'.$m[1].'/'.$m[2].'/'.$m[3].'/CHANGELOG.md';
        return Remember::value('simplify.release-notes.'.hash('sha256',$file).'.'.$version,86400,function()use($file,$version,$pageUrl):?array{
            $section=self::section(Http::timeout(4)->connectTimeout(2)->get($file)->throw()->body(),$version);
            $lines=$section===null?[]:self::summarize($section);
            return $lines?['lines'=>$lines,'url'=>$pageUrl]:null;
        },3600);
    }

    /** The text under "## <version>" up to the next heading of that level, or null. */
    public static function section(string $markdown,string $version): ?string
    {
        $found=null;$lines=[];
        foreach(preg_split('/\R/',$markdown)?:[] as $line){
            if(preg_match('/^##\s+v?(\S+)/',$line,$m)){
                if($found)break;
                $found=$m[1]===$version;
                continue;
            }
            if($found)$lines[]=$line;
        }
        return $found?implode("\n",$lines):null;
    }

    /** Plain lines from Markdown: list items first (else paragraphs), without links, images, markup or "by @someone in …". */
    public static function summarize(string $markdown): array
    {
        $markdown=(string)preg_replace('/<!--.*?-->/s','',$markdown);
        $items=[];$text=[];$skip=false;
        foreach(preg_split('/\R/',$markdown)?:[] as $line){
            $line=trim($line);
            // GitHub's generated "New Contributors" list is about people, not the release.
            if(str_starts_with($line,'#'))$skip=(bool)preg_match('/new contributors/i',$line);
            if($skip||$line===''||str_starts_with($line,'#')||str_starts_with($line,'|')||str_starts_with($line,'```')||preg_match('/^(\*\*)?(full changelog|what\'s changed|new contributors)/i',$line))continue;
            if(preg_match('/^(?:[-*+]|\d+[.)])\s+(.*)$/',$line,$m))$items[]=self::plain($m[1]);
            else $text[]=self::plain($line);
        }
        // An intro sentence goes first when there are list items too (Simplify's changelog starts with one).
        $lines=array_values(array_filter($items?[...array_slice($text,0,1),...$items]:$text,fn(string $l):bool=>$l!==''));
        return array_slice($lines,0,self::LINES);
    }

    private static function plain(string $line): string
    {
        $line=(string)preg_replace(['/!\[[^\]]*\]\([^)]*\)/','/\[([^\]]+)\]\([^)]*\)/','/\s+by @[\w-]+ in https?:\/\/\S+/i','/https?:\/\/\S+/','/<[^>]+>/','/(\*\*|__|`|~~)/','/(?<!\w)[*_](\S[^*_]*)[*_](?!\w)/'],['','$1','','','','','$1'],$line);
        $line=trim((string)preg_replace('/\s+/',' ',html_entity_decode($line,ENT_QUOTES|ENT_HTML5)));
        return mb_strlen($line)>self::LINE_LENGTH?rtrim(mb_substr($line,0,self::LINE_LENGTH-1)).'…':$line;
    }
}
