<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Storage;
/**
 * Announcements: Info, Warning and Success messages root admins write under Admin tools → Announcements, shown as
 * bars above the server overview and server pages and in the bell. Also shows the announcements of the Hub plugin "Announcements"
 * (read only) and, for root admins, the Panel's own update announcements (PanelUpdates). Live ones are pinned there;
 * each account can unpin one and pin it again from the bell.
 */
final class Announcements
{
    public const FILE='simplify/announcements.json';
    public const DISMISSED_DIR='simplify/announcements-dismissed/';
    public const CATEGORIES=['info'=>'Info','warning'=>'Warning','success'=>'Success'];
    public const AUDIENCES=['everyone'=>'Everyone','admins'=>'Admins only'];
    /** Category => tone of the dashboard's alerts and bell items. */
    public const TONES=['info'=>'info','warning'=>'warn','success'=>'ok','danger'=>'danger'];
    public const LIMIT=50;
    public const TITLE_LENGTH=120;
    public const TEXT_LENGTH=1000;
    public const LABEL_LENGTH=40;
    private const DISMISSED_LIMIT=300;

    /** Saved announcements, newest first. */
    public static function all(): array
    {
        $rows=[];
        foreach(JsonFile::read(self::FILE) as $row){
            $row=is_array($row)?self::clean($row):null;
            if($row)$rows[$row['id']]=$row;
        }
        uasort($rows,fn(array $a,array $b):int=>$b['created_at']<=>$a['created_at']);
        return array_values($rows);
    }

    public static function find(string $id): ?array
    {
        foreach(self::all() as $row)if($row['id']===$id)return $row;
        return null;
    }

    /** Adds ($id null) or replaces an announcement; returns the saved row. Throws \InvalidArgumentException with a message for the form. */
    public static function save(array $input,?string $id=null,string $author='',?int $now=null): array
    {
        $now??=time();
        $rows=self::all();
        $old=null;
        if($id!==null){foreach($rows as $row)if($row['id']===$id)$old=$row;if(!$old)throw new \InvalidArgumentException('This announcement no longer exists.');}
        elseif(count($rows)>=self::LIMIT)throw new \InvalidArgumentException('There are already '.self::LIMIT.' announcements. Delete an old one first.');
        $title=self::line($input['title']??'',self::TITLE_LENGTH);
        if($title==='')throw new \InvalidArgumentException('Give the announcement a title.');
        $starts=self::time($input['starts_at']??null);$ends=self::time($input['ends_at']??null);
        if($starts!==null&&$ends!==null&&$ends<=$starts)throw new \InvalidArgumentException('"Show until" must be after "Show from".');
        $link=self::buttonTarget($input);
        if(($input['link_type']??'none')!=='none'&&$link===null)throw new \InvalidArgumentException(($input['link_type']??'')==='url'?'Use a link that starts with https:// or with / for a page of this panel.':'Pick a server for the button.');
        $row=self::clean([
            'id'=>$old['id']??bin2hex(random_bytes(8)),
            'category'=>$input['category']??'info','title'=>$title,'text'=>$input['text']??'',
            'starts_at'=>$starts,'ends_at'=>$ends,'audience'=>$input['audience']??'everyone',
            'button'=>$link===null?null:['label'=>self::line($input['button_label']??'',self::LABEL_LENGTH)?:'Open',...$link],
            'created_by'=>$old['created_by']??$author,'created_at'=>$old['created_at']??$now,'updated_at'=>$now,
            // Sent as an app notification once, when it starts; editing does not send it again.
            'push'=>!empty($input['push']),'pushed_at'=>$old['pushed_at']??null,
        ]);
        $rows=array_values(array_filter($rows,fn(array $r):bool=>$r['id']!==$row['id']));
        array_unshift($rows,$row);
        self::write($rows);
        return $row;
    }

    /** Live announcements marked "also send as app notification" that were not sent yet. */
    public static function duePush(?int $now=null): array
    {
        return array_values(array_filter(self::all(),fn(array $r):bool=>$r['push']&&$r['pushed_at']===null&&self::state($r,$now)==='live'));
    }

    public static function markPushed(string $id,?int $now=null): void
    {
        self::write(array_map(fn(array $r):array=>$r['id']===$id?[...$r,'pushed_at'=>$now??time()]:$r,self::all()));
    }

    public static function delete(string $id): void
    {
        self::write(array_values(array_filter(self::all(),fn(array $r):bool=>$r['id']!==$id)));
    }

    /** "live", "scheduled" or "ended". */
    public static function state(array $row,?int $now=null): string
    {
        $now??=time();
        if($row['starts_at']!==null&&$row['starts_at']>$now)return 'scheduled';
        if($row['ends_at']!==null&&$row['ends_at']<=$now)return 'ended';
        return 'live';
    }

    /** Live announcements this account may see, newest first, each marked when this account hid it. */
    public static function forUser(object $user,?int $now=null): array
    {
        $now??=time();
        $admin=method_exists($user,'isAdmin')&&$user->isAdmin();
        $items=[];
        foreach(self::all() as $row){
            if(self::state($row,$now)!=='live'||($row['audience']==='admins'&&!$admin))continue;
            $items[]=self::present($row,$now);
        }
        array_push($items,...self::external($now));
        if(method_exists($user,'isRootAdmin')&&$user->isRootAdmin())array_push($items,...PanelUpdates::announcements($now));
        $hidden=array_flip(self::dismissedKeys($user));
        foreach($items as &$item)$item['dismissed']=isset($hidden[$item['key']]);
        unset($item);
        usort($items,fn(array $a,array $b):int=>$b['at']<=>$a['at']);
        return $items;
    }

    /** Unpins an announcement for this account: no bar any more, it stays under the bell until it ends. */
    public static function dismiss(object $user,string $key): void
    {
        if(!self::validKey($key))return;
        $keys=array_values(array_diff(self::dismissedKeys($user),[$key]));
        $keys[]=$key;
        self::saveDismissed($user,array_slice($keys,-self::DISMISSED_LIMIT));
    }

    /** Pins an unpinned announcement again for this account. */
    public static function restore(object $user,string $key): void
    {
        if(!self::validKey($key))return;
        $keys=self::dismissedKeys($user);
        if(in_array($key,$keys,true))self::saveDismissed($user,array_values(array_diff($keys,[$key])));
    }

    public static function dismissedKeys(object $user): array
    {
        return array_values(array_filter(JsonFile::read(self::DISMISSED_DIR.(int)$user->id.'.json'),'is_string'));
    }

    /** Dashboard shape of a saved announcement. */
    public static function present(array $row,?int $now=null): array
    {
        $now??=time();
        $action=null;
        if($row['button']){
            $url=$row['button']['url']??null;
            if(isset($row['button']['server']))$url=self::serverUrl($row['button']['server']);
            if($url)$action=['label'=>$row['button']['label'],'url'=>$url];
        }
        $at=max($row['starts_at']??0,$row['created_at']);
        return ['key'=>'own-'.$row['id'],'source'=>'own','category'=>$row['category'],'tone'=>self::TONES[$row['category']],'title'=>$row['title'],'text'=>$row['text'],
            'from'=>$row['created_by']!==''?$row['created_by']:'Admin','audience'=>$row['audience'],'at'=>$at,'time'=>self::ago($at,$now),
            'until'=>$row['ends_at']!==null?self::until($row['ends_at']):null,'action'=>$action];
    }

    /** Announcements of the Hub plugin "Announcements" (Boy132) when it is installed: read only, for the app panel. */
    public static function external(?int $now=null): array
    {
        $now??=time();
        $model='Boy132\\Announcements\\Models\\Announcement';
        if(!class_exists($model))return [];
        try{$records=$model::query()->get();}catch(\Throwable){return [];}
        $items=[];
        foreach($records as $record){
            $panels=is_array($record->panels??null)?$record->panels:[];
            if($panels!==[]&&!in_array('app',$panels,true))continue;
            $from=self::stamp($record->valid_from??null);$to=self::stamp($record->valid_to??null);
            if(($from!==null&&$from>$now)||($to!==null&&$to<$now))continue;
            $title=self::line(strip_tags((string)($record->title??'')),self::TITLE_LENGTH);
            if($title==='')continue;
            $type=(string)($record->type??'info');
            $label=self::line((string)($record->url_label??''),self::LABEL_LENGTH);$url=(string)($record->url_link??'');
            $at=$from??self::stamp($record->created_at??null)??$now;
            $items[]=['key'=>'plugin-'.(int)$record->id,'source'=>'plugin','category'=>isset(self::TONES[$type])&&$type!=='danger'?$type:($type==='danger'?'warning':'info'),'tone'=>self::TONES[$type]??'info',
                'title'=>$title,'text'=>self::text(strip_tags((string)($record->body??''))),'from'=>'Announcements','audience'=>'everyone','at'=>$at,'time'=>self::ago($at,$now),
                'until'=>$to!==null?self::until($to):null,'action'=>$label!==''&&self::safeUrl($url)?['label'=>$label,'url'=>$url]:null];
        }
        return $items;
    }

    /** One stored row with known fields only, or null when it is unusable. */
    public static function clean(array $row): ?array
    {
        $id=$row['id']??null;$title=self::line($row['title']??'',self::TITLE_LENGTH);
        if(!is_string($id)||!preg_match('/^[a-f0-9]{16}$/',$id)||$title==='')return null;
        $button=null;
        if(is_array($row['button']??null)){
            $label=self::line($row['button']['label']??'',self::LABEL_LENGTH)?:'Open';
            if(is_string($row['button']['server']??null)&&preg_match('/^[A-Za-z0-9-]{1,64}$/',$row['button']['server']))$button=['label'=>$label,'server'=>$row['button']['server']];
            elseif(is_string($row['button']['url']??null)&&self::safeUrl($row['button']['url']))$button=['label'=>$label,'url'=>$row['button']['url']];
        }
        $starts=is_int($row['starts_at']??null)?$row['starts_at']:null;$ends=is_int($row['ends_at']??null)?$row['ends_at']:null;
        return ['id'=>$id,'category'=>isset(self::CATEGORIES[$row['category']??''])?$row['category']:'info','title'=>$title,'text'=>self::text($row['text']??''),
            'starts_at'=>$starts,'ends_at'=>$ends,'audience'=>isset(self::AUDIENCES[$row['audience']??''])?$row['audience']:'everyone','button'=>$button,
            'created_by'=>self::line($row['created_by']??'',64),'created_at'=>is_int($row['created_at']??null)?$row['created_at']:0,'updated_at'=>is_int($row['updated_at']??null)?$row['updated_at']:0,
            'push'=>($row['push']??false)===true,'pushed_at'=>is_int($row['pushed_at']??null)?$row['pushed_at']:null];
    }

    /** "5 minutes ago", "yesterday", … */
    public static function ago(int $at,int $now): string
    {
        $seconds=max(0,$now-$at);
        if($seconds<60)return 'just now';
        foreach([[86400,'day'],[3600,'hour'],[60,'minute']] as [$size,$unit]){
            if($seconds<$size)continue;
            $count=intdiv($seconds,$size);
            if($unit==='day'&&$count===1)return 'yesterday';
            return $count.' '.$unit.($count===1?'':'s').' ago';
        }
        return 'just now';
    }

    private static function until(int $at): string { return 'until '.date('D j M H:i',$at); }

    private static function validKey(string $key): bool { return (bool)preg_match('/^[a-z]+-[A-Za-z0-9._-]{1,120}$/',$key); }

    private static function saveDismissed(object $user,array $keys): void
    {
        if(!Storage::disk('local')->put(self::DISMISSED_DIR.(int)$user->id.'.json',json_encode($keys,JSON_THROW_ON_ERROR)))throw new \RuntimeException('Could not save the announcement.');
    }

    private static function write(array $rows): void
    {
        if(!Storage::disk('local')->put(self::FILE,json_encode(array_values($rows),JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)))throw new \RuntimeException('Could not save the announcements.');
    }

    /** The button target from the form: a server (uuid) or a link, null without a button. */
    private static function buttonTarget(array $input): ?array
    {
        return match($input['link_type']??'none'){
            'server'=>is_string($input['server']??null)&&preg_match('/^[A-Za-z0-9-]{1,64}$/',$input['server'])?['server'=>$input['server']]:null,
            'url'=>is_string($input['url']??null)&&self::safeUrl(trim($input['url']))?['url'=>trim($input['url'])]:null,
            default=>null,
        };
    }

    /** https links and paths of this panel only (no javascript:, no other schemes, no protocol-relative links). */
    public static function safeUrl(string $url): bool
    {
        if(strlen($url)>2048||preg_match('/[\x00-\x20\x7F]/',$url))return false;
        if(str_starts_with($url,'/'))return !str_starts_with($url,'//')&&!str_starts_with($url,'/\\');
        return str_starts_with(strtolower($url),'https://')&&filter_var($url,FILTER_VALIDATE_URL)!==false;
    }

    private static function serverUrl(string $uuid): ?string
    {
        try{return \Simplify\Panel\Filament\App\Pages\Dashboard::getUrl(['server'=>$uuid],panel:'app');}catch(\Throwable){return null;}
    }

    /** Unix time from a form value (Y-m-d H:i[:s] in the panel's time zone), a number or a date object. */
    private static function time(mixed $value): ?int
    {
        if($value===null||$value==='')return null;
        if(is_int($value))return $value;
        if($value instanceof \DateTimeInterface)return $value->getTimestamp();
        $time=is_string($value)?strtotime($value):false;
        return $time===false?null:$time;
    }

    private static function stamp(mixed $value): ?int
    {
        if($value instanceof \DateTimeInterface)return $value->getTimestamp();
        return is_string($value)&&$value!==''&&($time=strtotime($value))!==false?$time:null;
    }

    private static function line(mixed $value,int $length): string
    {
        return is_scalar($value)?mb_substr(trim((string)preg_replace('/\s+/u',' ',(string)$value)),0,$length):'';
    }

    /** Plain text with its line breaks (at most two in a row). */
    private static function text(mixed $value): string
    {
        if(!is_scalar($value))return '';
        $text=str_replace(["\r\n","\r"],"\n",(string)$value);
        $text=(string)preg_replace(['/[^\S\n]+/u','/ *\n */','/\n{3,}/'],[' ',"\n","\n\n"],$text);
        return mb_substr(trim($text),0,self::TEXT_LENGTH);
    }
}
