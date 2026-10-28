<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
/**
 * The Pelican Hub's public list of themes and plugins, shown in Simplify's plugin manager (Browse Hub) to point people
 * to the makers' work. Nothing is downloaded or installed from here: "Install on Hub" opens the item's Hub page, so
 * every install goes through the Hub and counts for its maker. The list is read from the Hub's JSON API at most once an
 * hour (a failed read is retried after five minutes), and only themes get one detail request each, for screenshots.
 */
final class HubCatalog
{
    public const HUB='https://hub.pelican.dev';
    private const CACHE='simplify.hub-catalog';
    private const MAX_PAGES=10;
    private const TYPES=['theme','plugin','language'];

    /** @return array{items: list<array>, at: ?int, error: ?string} */
    public static function catalog(): array
    {
        $cached=Cache::get(self::CACHE);
        if(is_array($cached))return $cached;
        return self::refresh();
    }

    public static function forget(): void { Cache::forget(self::CACHE); }

    public static function refresh(): array
    {
        try{
            $catalog=['items'=>self::build(self::read()),'at'=>time(),'error'=>null];
            Cache::put(self::CACHE,$catalog,now()->addHour());
        }catch(\Throwable $e){
            report($e);
            $catalog=['items'=>[],'at'=>null,'error'=>'The Pelican Hub could not be reached. Try again in a few minutes.'];
            Cache::put(self::CACHE,$catalog,now()->addMinutes(5));
        }
        return $catalog;
    }

    /**
     * List pages plus a detail record per theme, keyed by slug. The first page says how many pages there are; the
     * other pages are fetched together, and so are the theme details, so a cold catalogue takes two round trips
     * instead of one per page and per theme.
     */
    private static function read(): array
    {
        $agent='PelicanSimplify/'.BuildInfo::version().' (+https://github.com/jerajerajerajera/PelicanSimplify)';
        $get=fn(string $path)=>Http::timeout(10)->connectTimeout(4)->acceptJson()->withHeaders(['User-Agent'=>$agent])->get(self::HUB.$path)->throw()->json();
        $pool=function(array $paths)use($agent):array{
            if(!$paths)return [];
            $responses=Http::pool(fn(\Illuminate\Http\Client\Pool $pool)=>array_map(fn(string $path)=>$pool->as($path)->timeout(10)->connectTimeout(4)->acceptJson()->withHeaders(['User-Agent'=>$agent])->get(self::HUB.$path),$paths));
            $bodies=[];
            foreach($paths as $path){
                $response=$responses[$path]??null;
                if($response instanceof \Illuminate\Http\Client\Response&&$response->successful()&&is_array($response->json()))$bodies[$path]=$response->json();
            }
            return $bodies;
        };
        $first=$get('/api/plugins?page=1');
        $pages=[1=>$first];
        $last=min(self::MAX_PAGES,(int)($first['last_page']??1));
        $paths=[];for($page=2;$page<=$last;$page++)$paths[]='/api/plugins?page='.$page;
        foreach($pool($paths) as $path=>$body)$pages[(int)substr($path,strlen('/api/plugins?page='))]=$body;
        $list=[];
        for($page=1;$page<=$last;$page++){
            if(!isset($pages[$page]))throw new \RuntimeException('Hub page '.$page.' could not be read.');
            foreach((array)($pages[$page]['data']??[]) as $row)if(is_array($row))$list[]=$row;
        }
        $slugs=[];
        foreach($list as $row){
            $slug=(string)($row['slug']??'');
            if(($row['type']??'')==='theme'&&self::validSlug($slug))$slugs[]=$slug;
        }
        $details=[];
        foreach($pool(array_map(fn(string $slug)=>'/api/plugins/'.$slug,array_unique($slugs))) as $path=>$body)$details[substr($path,strlen('/api/plugins/'))]=(array)$body;
        return ['list'=>$list,'details'=>$details];
    }

    /** Hub rows to view rows: free items with a valid slug and Hub page; installed ones are marked by the caller. */
    public static function build(array $read): array
    {
        $items=[];
        foreach((array)($read['list']??[]) as $row){
            $item=self::normalize((array)$row,(array)($read['details'][$row['slug']??'']??[]));
            if($item)$items[$item['slug']]=$item;
        }
        return array_values($items);
    }

    public static function normalize(array $row,array $detail=[]): ?array
    {
        $row=[...$row,...array_filter($detail,fn($v)=>$v!==null)];
        $slug=(string)($row['slug']??'');
        $type=(string)($row['type']??'');
        if(!self::validSlug($slug)||!in_array($type,self::TYPES,true)||($row['pricing_type']??'free')!=='free')return null;
        $url=self::HUB.'/plugins/'.$slug;
        $description=(string)($row['description']??'');
        $shots=array_values(array_filter((array)($row['screenshots']??[]),fn($s)=>is_string($s)&&self::https($s)));
        return [
            'slug'=>$slug,'type'=>$type,
            'title'=>self::text((string)($row['title']??$slug),80),
            'author'=>self::text((string)($row['author_name']??''),60)?:'Unknown maker',
            'summary'=>self::summary($description),
            'based'=>self::basedOn($description),
            'downloads'=>max(0,(int)($row['downloads_count']??0)),
            'version'=>self::text((string)($row['latest_version']??''),30),
            'updated'=>self::date($row['last_version_at']??$row['approved_at']??null),
            'license'=>is_string($row['license']??null)?self::text($row['license'],30):null,
            'screenshots'=>array_slice($shots,0,4),
            'url'=>$url,
            'featured'=>(bool)($row['is_featured']??false),
        ];
    }

    /** Makers of one type, most items first, then most downloads. */
    public static function makers(array $items,string $type): array
    {
        $makers=[];
        foreach($items as $item){
            if($item['type']!==$type)continue;
            $m=&$makers[$item['author']];
            $m??=['name'=>$item['author'],'count'=>0,'downloads'=>0];
            $m['count']++;$m['downloads']+=$item['downloads'];
            unset($m);
        }
        $makers=array_values($makers);
        usort($makers,fn($a,$b)=>[$b['count'],$b['downloads'],$a['name']]<=>[$a['count'],$a['downloads'],$b['name']]);
        return $makers;
    }

    /** First sentences of a Markdown description as plain text, without headings, images, badges or code. */
    public static function summary(string $markdown,int $max=160): string
    {
        $text=preg_replace('/```.*?```/s',' ',$markdown)??'';
        $lines=[];
        foreach(preg_split('/\R/',$text)?:[] as $line){
            $line=trim($line);
            if($line===''||preg_match('/^(#|!\[|\[!\[|<|>|\||-{3,}|_?originally created by)/i',$line))continue;
            $lines[]=preg_replace('/^(?:[-*+]|\d+\.)\s+/','',$line)??$line;
        }
        $text=implode(' ',$lines);
        $text=preg_replace('/!\[[^\]]*\]\([^)]*\)/','',$text)??'';
        $text=preg_replace('/\[([^\]]*)\]\([^)]*\)/','$1',$text)??'';
        $text=str_replace(['**','__','`'],'',$text);
        $text=trim(preg_replace('/\s+/',' ',$text)??'');
        if(mb_strlen($text)<=$max)return $text;
        $cut=mb_substr($text,0,$max);
        $end=max((int)mb_strrpos($cut,'. '),0);
        return $end>60?mb_substr($cut,0,$end+1):rtrim(mb_substr($cut,0,(int)(mb_strrpos($cut,' ')?:$max)),' ,;:').'…';
    }

    /** "Originally created by [Name](https://...)" in a description: the original author is credited too. */
    public static function basedOn(string $markdown): ?array
    {
        if(!preg_match('/originally (?:created|made) by \[([^\]]{1,60})\]\((https:\/\/[^)\s]+)\)/i',$markdown,$m))return null;
        return ['name'=>trim($m[1]),'url'=>$m[2]];
    }

    private static function validSlug(string $slug): bool { return preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/',$slug)===1; }

    private static function https(string $url): bool { return str_starts_with($url,'https://')&&filter_var($url,FILTER_VALIDATE_URL)!==false; }

    private static function text(string $value,int $max): string { return mb_substr(trim(strip_tags($value)),0,$max); }

    private static function date(mixed $value): ?string
    {
        if(!is_string($value)||($at=strtotime($value))===false)return null;
        return gmdate('Y-m-d',$at);
    }
}
