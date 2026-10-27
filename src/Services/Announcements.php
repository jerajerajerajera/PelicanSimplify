<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Storage;
/**
 * Announcements: Info, Warning and Success messages root admins write under Admin tools → Announcements, shown as
 * bars above the server overview and server pages and in the bell. Also shows the announcements of the Hub plugin "Announcements"
 * (read only) and, for root admins, the Panel's own update announcements (PanelUpdates). Live ones are pinned there;
 * each account can unpin one and pin it again from the bell. An admin can also pin one to everyone's notifications:
 * it then stays at the top of the bell until it ends, whatever the account does with its bar. "Also send in game" posts it
 * in the chat of game servers as well (InGameAnnouncements).
 */
final class Announcements
{
    public const FILE='simplify/announcements.json';
    public const DISMISSED_DIR='simplify/announcements-dismissed/';
    /** Per account: {"read_at": unix time, "archived_at": unix time, "archived": [keys]}: "Mark all read" and "Clear read". */
    public const READ_DIR='simplify/announcements-read/';
    public const CATEGORIES=['info'=>'Info','warning'=>'Warning','success'=>'Success'];
    public const AUDIENCES=['everyone'=>'Everyone','admins'=>'Admins only'];
    /** Category => tone of the dashboard's alerts and bell items. */
    public const TONES=['info'=>'info','warning'=>'warn','success'=>'ok','danger'=>'danger'];
    public const LIMIT=50;
    public const TITLE_LENGTH=120;
    public const TEXT_LENGTH=1000;
    public const LABEL_LENGTH=40;
    private const DISMISSED_LIMIT=300;
    private const PELICAN_ID='/^[0-9a-f-]{36}$/i';

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
    public static function save(array $input,?string $id=null,string $author='',?int $now=null,?int $editor=null): array
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
        $ingame=InGameAnnouncements::fromInput($input,$old,$editor);
        $row=self::clean([
            'id'=>$old['id']??bin2hex(random_bytes(8)),
            'category'=>$input['category']??'info','title'=>$title,'text'=>$input['text']??'',
            'starts_at'=>$starts,'ends_at'=>$ends,'audience'=>$input['audience']??'everyone',
            'button'=>$link===null?null:['label'=>self::line($input['button_label']??'',self::LABEL_LENGTH)?:'Open',...$link],
            'created_by'=>$old['created_by']??$author,'created_at'=>$old['created_at']??$now,'updated_at'=>$now,
            // Sent as an app notification once per device, from when it starts; editing does not send it again.
            'push'=>!empty($input['push']),'pushed_at'=>$old['pushed_at']??null,'pushed_to'=>$old['pushed_to']??null,
            'pinned'=>!empty($input['pinned']),
            'ingame'=>$ingame,
        ]);
        $rows=array_values(array_filter($rows,fn(array $r):bool=>$r['id']!==$row['id']));
        array_unshift($rows,$row);
        self::write($rows);
        return $row;
    }

    /** How long after it starts an announcement still reaches devices that missed it (turned on later, or a failed send). */
    public const PUSH_CATCH_UP=86400;

    /** Live announcements marked "also send as app notification" that devices may still be missing (each device gets one once). */
    public static function duePush(?int $now=null): array
    {
        $now??=time();
        return array_values(array_filter(self::all(),fn(array $r):bool=>$r['push']&&self::state($r,$now)==='live'&&($r['starts_at']??$r['created_at'])>=$now-self::PUSH_CATCH_UP));
    }

    /** Records a sending round: first sent at, and how many devices it reached so far. */
    public static function recordPush(string $id,int $devices,?int $now=null): void
    {
        self::write(array_map(fn(array $r):array=>$r['id']===$id?[...$r,'pushed_at'=>$r['pushed_at']??$now??time(),'pushed_to'=>($r['pushed_to']??0)+$devices]:$r,self::all()));
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
        $state=self::readState($user);
        $archived=array_flip($state['archived']);
        $markedUnread=array_flip($state['unread_keys']);$markedRead=array_flip($state['read_keys']);
        foreach($items as &$item){
            $item['dismissed']=isset($hidden[$item['key']]);
            $item['pinned']=($item['pinned']??false)===true;
            // Unread until "Mark all read" or until this account unpins its bar.
            // "Clear read" (or ✕ on one) moves it to Archived, read; an admin's pinned ones always stay on top.
            $item['archived']=!$item['pinned']&&isset($archived[$item['key']]);
            // Mark read / Mark as unread on this one wins over the rest.
            $item['unread']=!$item['archived']&&!isset($markedRead[$item['key']])&&(isset($markedUnread[$item['key']])||(!$item['dismissed']&&$item['at']>$state['read_at']));
        }
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
        // Unpinning its bar reads it, also after Mark as unread.
        $state=self::readState($user);
        if(in_array($key,$state['unread_keys'],true))self::saveReadState($user,['unread_keys'=>array_values(array_diff($state['unread_keys'],[$key]))]);
    }

    /** Pins an unpinned announcement again for this account. */
    public static function restore(object $user,string $key): void
    {
        if(!self::validKey($key))return;
        $keys=self::dismissedKeys($user);
        if(in_array($key,$keys,true))self::saveDismissed($user,array_values(array_diff($keys,[$key])));
    }

    /** "Mark all read": every announcement up to now counts as read for this account. */
    public static function markAllRead(object $user,?int $now=null): void
    {
        self::saveReadState($user,['read_at'=>$now??time(),'unread_keys'=>[],'read_keys'=>[]]);
    }

    /**
     * Mark read / Mark as unread on one announcement for this account. Mark as unread also takes it out of Archived, back
     * into the list, like an unread Pelican notification.
     */
    public static function markOne(object $user,string $key,bool $unread): void
    {
        if(!self::validKey($key))return;
        $state=self::readState($user);
        $without=fn(array $keys):array=>array_values(array_diff($keys,[$key]));
        $with=fn(array $keys):array=>array_slice([...$without($keys),$key],-self::DISMISSED_LIMIT);
        self::saveReadState($user,$unread
            ?['unread_keys'=>$with($state['unread_keys']),'read_keys'=>$without($state['read_keys']),'archived'=>$without($state['archived'])]
            :['read_keys'=>$with($state['read_keys']),'unread_keys'=>$without($state['unread_keys'])]);
    }

    /** Mark as unread on one Pelican notification: unread again, and out of Archived when ✕ put it there. */
    public static function markPelicanUnread(object $user,string $id): void
    {
        if(!preg_match(self::PELICAN_ID,$id))return;
        PanelNotifications::markUnread([$id]);
        $state=self::readState($user);
        if(in_array($id,$state['archived_ids'],true))self::saveReadState($user,['archived_ids'=>array_values(array_diff($state['archived_ids'],[$id]))]);
    }

    /** "Clear read": read announcements (not the pinned ones) move to Archived for this account; read Pelican notifications follow archived_at. */
    public static function archiveRead(object $user,?int $now=null): void
    {
        $keys=array_column(array_filter(self::forUser($user,$now),fn(array $item):bool=>!$item['unread']&&!$item['pinned']),'key');
        $keys=array_values(array_unique([...self::readState($user)['archived'],...$keys]));
        self::saveReadState($user,['archived_at'=>$now??time(),'archived'=>array_slice($keys,-self::DISMISSED_LIMIT)]);
    }

    /**
     * ✕ on one notification: an announcement (by key) or a Pelican notification (by id) moves to Archived, read.
     * Pinned announcements stay; their bar has its own pin.
     */
    public static function archiveOne(object $user,string $key,bool $pelican=false): void
    {
        $state=self::readState($user);
        if($pelican){
            if(!preg_match(self::PELICAN_ID,$key))return;
            PanelNotifications::markRead([$key]);
            self::saveReadState($user,['archived_ids'=>array_slice(array_values(array_unique([...$state['archived_ids'],$key])),-self::DISMISSED_LIMIT)]);
            return;
        }
        if(!self::validKey($key))return;
        self::saveReadState($user,['archived'=>array_slice(array_values(array_unique([...$state['archived'],$key])),-self::DISMISSED_LIMIT)]);
    }

    /** Whether this account has anything under Archived. */
    public static function hasArchive(object $user): bool
    {
        $state=self::readState($user);
        return $state['archived_at']>0||$state['archived_ids']!==[]||$state['archived']!==[];
    }

    public static function readAt(object $user): int { return self::readState($user)['read_at']; }

    /** Until when read Pelican notifications are archived (0: never cleared). */
    public static function archivedAt(object $user): int { return self::readState($user)['archived_at']; }

    /** @return array{read_at: int, archived_at: int, archived: list<string>, archived_ids: list<string>, unread_keys: list<string>, read_keys: list<string>} */
    public static function readState(object $user): array
    {
        $data=JsonFile::read(self::READ_DIR.(int)$user->id.'.json');
        return ['read_at'=>is_int($data['read_at']??null)?$data['read_at']:0,'archived_at'=>is_int($data['archived_at']??null)?$data['archived_at']:0,
            'archived'=>array_values(array_filter(is_array($data['archived']??null)?$data['archived']:[],fn($key):bool=>is_string($key)&&self::validKey($key))),
            'archived_ids'=>array_values(array_filter(is_array($data['archived_ids']??null)?$data['archived_ids']:[],fn($id):bool=>is_string($id)&&preg_match(self::PELICAN_ID,$id)===1)),
            'unread_keys'=>self::keyList($data['unread_keys']??null),'read_keys'=>self::keyList($data['read_keys']??null)];
    }

    /** @return list<string> */
    private static function keyList(mixed $keys): array
    {
        return array_values(array_filter(is_array($keys)?$keys:[],fn($key):bool=>is_string($key)&&self::validKey($key)));
    }

    private static function saveReadState(object $user,array $changes): void
    {
        if(!Storage::disk('local')->put(self::READ_DIR.(int)$user->id.'.json',json_encode([...self::readState($user),...$changes],JSON_THROW_ON_ERROR)))throw new \RuntimeException('Could not save the announcements.');
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
            'until'=>$row['ends_at']!==null?self::until($row['ends_at']):null,'ends_at'=>$row['ends_at'],'action'=>$action,'pinned'=>$row['pinned']];
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
                'until'=>$to!==null?self::until($to):null,'ends_at'=>$to,'action'=>$label!==''&&self::safeUrl($url)?['label'=>$label,'url'=>$url]:null];
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
            'push'=>($row['push']??false)===true,'pushed_at'=>is_int($row['pushed_at']??null)?$row['pushed_at']:null,'pushed_to'=>is_int($row['pushed_to']??null)?$row['pushed_to']:null,'pinned'=>($row['pinned']??false)===true,
            'ingame'=>InGameAnnouncements::clean($row['ingame']??null)];
    }

    /** "5 minutes ago", "yesterday", … */
    public static function ago(int $at,int $now): string
    {
        $seconds=max(0,$now-$at);
        if($seconds<60)return __('pelican-simplify::announcements.time.just_now');
        foreach([[86400,'days'],[3600,'hours'],[60,'minutes']] as [$size,$unit]){
            if($seconds<$size)continue;
            $count=intdiv($seconds,$size);
            if($unit==='days'&&$count===1)return __('pelican-simplify::announcements.time.yesterday');
            return trans_choice('pelican-simplify::announcements.time.'.$unit,$count);
        }
        return __('pelican-simplify::announcements.time.just_now');
    }

    private static function until(int $at): string { return __('pelican-simplify::announcements.time.until', ['date'=>date('D j M H:i',$at)]); }

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
