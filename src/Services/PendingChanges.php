<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Server;
use App\Enums\SubuserPermission;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Repositories\Daemon\DaemonServerRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
final class PendingChanges
{
    private static function path(Server $s): string { return 'simplify/pending/'.$s->uuid.'.json'; }
    public static function read(Server $s): ?array
    {
        $raw=Storage::disk('local')->get(self::path($s));
        return $raw ? json_decode($raw,true,512,JSON_THROW_ON_ERROR) : null;
    }
    public static function stage(Server $s,array $state,?object $icon=null,bool $resourcesOnly=false): array
    {
        $minecraft=ManagedSettings::minecraft($s) && !$resourcesOnly;
        if ($minecraft) {
            Access::permission($s,SubuserPermission::FileUpdate);Access::permission($s,SubuserPermission::FileReadContent);
            if ($icon) Access::permission($s,SubuserPermission::FileCreate);
            $prepared=ServerProperties::prepare([...$state,'properties_enabled'=>true,'server_icon'=>$icon],false);
            $original=app(DaemonFileRepository::class)->setServer($s)->getContent('/server.properties',1048576);
        } else {
            Access::owner($s);abort_unless(ManagedResources::canEdit($s),403);
            $prepared=['properties'=>[],'icon'=>null];$original='';
        }
        $result=Cache::lock('simplify.pending.'.$s->uuid,15)->block(3,function()use($s,$state,$prepared,$original,$minecraft,$resourcesOnly):array {
            $s->refresh();
            $old=self::read($s);
            abort_if(!empty($old['restart_requested']),409,'A restart is already applying these changes.');
            abort_if($old && (int)($old['actor_id']??0)!==(int)user()->id,409,'Another user has pending changes. Apply or discard them first.');
            $data=['actor_id'=>user()->id,'minecraft'=>$minecraft,'properties'=>$minecraft?SettingsDifference::properties($prepared['properties'],PropertiesReader::parse($original)):[],'icon'=>$prepared['icon'],
                'hash'=>!empty($old['minecraft'])&&empty($old['file_only'])?($old['hash']??hash('sha256',$original)):hash('sha256',$original),'created_at'=>time(),'restart_at'=>null,'restart_requested'=>false];
            if ($data['icon'] !== null) {
                try {$existingIcon=app(DaemonFileRepository::class)->setServer($s)->getContent('/server-icon.png',102400);if(hash_equals(hash('sha256',$existingIcon),hash('sha256',base64_decode($data['icon'],true))))$data['icon']=null;}catch(\Throwable){}
            } elseif (!$resourcesOnly && !empty($old['icon'])) $data['icon']=$old['icon'];
            if(!empty($old['file_changes']))$data['file_changes']=true;
            if (isset($old['resources'])) $data['resources']=$old['resources'];
            if ($resourcesOnly && $old && empty($old['file_only'])) foreach (['minecraft','properties','icon','hash'] as $key) if (array_key_exists($key,$old)) $data[$key]=$old[$key];
            if (isset($state['memory_gb'],$state['disk_mb'],$state['cpu'])) {
                Access::owner($s);
                abort_unless(ManagedResources::canEdit($s),403);
                $limits=ResourceLimits::from($state);$r=ResourceLimits::pending($limits);
                if (!CreatorAccess::admin()) {
                    $other=Server::where('owner_id',user()->id)->whereKeyNot($s->id)->get(['memory','disk']);
                    $quota=CreatorAccess::settings();$quota['max_servers']=PHP_INT_MAX;$quota['max_backups']=max($quota['max_backups'],$s->backup_limit);
                    CreatorAccess::checkQuota([...$limits,'backup_limit'=>$s->backup_limit],['count'=>0,'memory'=>$other->sum('memory'),'disk'=>$other->sum('disk')],$quota);
                }
                if(SettingsDifference::resources($r,$s))$data['resources']=$r;else unset($data['resources']);
            }
            $data['minecraft'] = !empty($data['properties']) || $data['icon'] !== null;
            $hasChanges = $data['minecraft'] || isset($data['resources']) || !empty($data['file_changes']);
            if (!$hasChanges) {
                if ($old) Storage::disk('local')->delete(self::path($s));
                return ['pending'=>false,'changed'=>$old!==null];
            }
            if (!$data['minecraft'] && !empty($data['file_changes'])) $data['file_only']=true;
            if ($old && ($old['properties']??[])===$data['properties'] && ($old['icon']??null)===$data['icon']
                && ($old['resources']??null)===($data['resources']??null) && !empty($old['file_changes'])===!empty($data['file_changes'])) {
                return ['pending'=>true,'changed'=>false,'created_at'=>$old['created_at']];
            }
            if (!Storage::disk('local')->put(self::path($s),json_encode($data,JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save pending changes.');
            return ['pending'=>true,'changed'=>true,'created_at'=>$data['created_at']];
        });
        if($result['changed'])self::progress($s,true);
        $result['pending']=self::read($s)!==null;
        return $result;
    }
    /** Native file saves are already written; this marker only tracks a restart reminder. */
    public static function fileChanged(Server $s): void
    {
        Access::permission($s,SubuserPermission::FileUpdate);
        Access::permission($s,SubuserPermission::FileReadContent);
        Cache::lock('simplify.pending.'.$s->uuid,15)->block(3,function()use($s):void {
            $data=self::read($s);
            abort_if(!empty($data['restart_requested']),409,'A restart is already in progress.');
            abort_if($data && (int)($data['actor_id']??0)!==(int)user()->id,409,'Another user has pending changes.');
            $data ??= ['actor_id'=>user()->id,'minecraft'=>ManagedSettings::minecraft($s),'file_only'=>true,'properties'=>[],'icon'=>null,'hash'=>'','created_at'=>time(),'restart_at'=>null,'restart_requested'=>false];
            $data['file_changes']=true;
            if(!Storage::disk('local')->put(self::path($s),json_encode($data,JSON_THROW_ON_ERROR)))throw new \RuntimeException('Could not save the restart reminder.');
        });
    }
    public static function discard(Server $s): void
    {
        if(ManagedSettings::minecraft($s)||!empty(self::read($s)['file_only'])) Access::permission($s,SubuserPermission::FileUpdate);else Access::owner($s);
        Cache::lock('simplify.pending.'.$s->uuid,15)->block(3,function()use($s):void {
            abort_if(!empty(self::read($s)['restart_requested']),409,'Restart is already in progress.');
            Storage::disk('local')->delete(self::path($s));
        });
    }
    public static function requestRestart(Server $s,int $minutes): void { self::requestApply($s,$minutes,true); }
    public static function requestStop(Server $s): void { self::requestApply($s,0,false); }
    private static function requestApply(Server $s,int $minutes,bool $startAfter): void
    {
        if ($startAfter) Access::permission($s,SubuserPermission::ControlRestart);
        Access::permission($s,SubuserPermission::ControlStop);
        if ($startAfter) Access::permission($s,SubuserPermission::ControlStart);
        abort_unless($minutes>=0 && $minutes<=10080,422);
        abort_if(!$s->installed_at || $s->status!==null,409,'Wait until the current installation or recovery is complete.');
        Cache::lock('simplify.pending.'.$s->uuid,15)->block(3,function()use($s,$minutes,$startAfter):void {
            $data=self::read($s);abort_unless($data,409,'There are no pending changes.');
            abort_if(!empty($data['restart_requested']),409,'Restart is already in progress.');
            unset($data['error']);
            $data['restart_at']=time()+$minutes*60;$data['restart_actor_id']=user()->id;$data['start_after_apply']=$startAfter;
            Storage::disk('local')->put(self::path($s),json_encode($data,JSON_THROW_ON_ERROR));
        });
        if ($minutes===0) self::progress($s);
    }
    /** Advance due changes from HTTP requests without changing the signed-in user. */
    public static function progress(Server $s,bool $onlyOffline=false): void
    {
        $caller=auth()->user();
        try { self::tick($s,$onlyOffline); }
        catch (\Throwable $e) { report($e);self::failed($s,$e->getMessage());throw $e; }
        finally { if ($caller) auth()->setUser($caller);else auth()->forgetUser(); }
    }
    public static function tick(Server $s,bool $onlyOffline=false): void
    {
        if (!$s->installed_at || $s->status!==null) return;
        Cache::lock('simplify.pending.'.$s->uuid,60)->get(function()use($s,$onlyOffline):void {
            $data=self::read($s);
            if (!$data) return;
            if ($onlyOffline && (isset($data['restart_at']) || !empty($data['restart_requested']))) return;
            if (!$onlyOffline && (!isset($data['restart_at']) || $data['restart_at']>time())) return;
            $actor=\App\Models\User::find($data['actor_id']);$restart=$onlyOffline?$actor:\App\Models\User::find($data['restart_actor_id']??0);
            if (!$actor || !$restart) throw new \RuntimeException('Pending-change user no longer exists.');
            $startAfter=!$onlyOffline && ($data['start_after_apply']??true);
            auth()->setUser($restart);if ($startAfter) Access::permission($s,SubuserPermission::ControlRestart);
            if (!$onlyOffline) Access::permission($s,SubuserPermission::ControlStop);
            if ($startAfter) Access::permission($s,SubuserPermission::ControlStart);
            auth()->setUser($actor);if(($data['minecraft']??true)||!empty($data['file_only'])){Access::permission($s,SubuserPermission::FileReadContent);Access::permission($s,SubuserPermission::FileUpdate);}else{Access::owner($s);}
            $daemon=app(DaemonServerRepository::class)->setServer($s);$status=$daemon->getDetails()['state']??'unknown';
            if ($onlyOffline && $status!=='offline') return;
            if (!empty($data['applied'])) {
                if (!$startAfter || $status==='running') { Storage::disk('local')->delete(self::path($s));return; }
                if ($status==='offline') { auth()->setUser($restart);$daemon->power('start')->throw();Storage::disk('local')->delete(self::path($s)); }
                return;
            }
            if (in_array($status,['running','starting'],true)) {
                if (!empty($data['restart_requested'])) return;
                $daemon->power('stop')->throw();$data['restart_requested']=true;
                Storage::disk('local')->put(self::path($s),json_encode($data,JSON_THROW_ON_ERROR));return;
            }
            if ($status!=='offline') return;
            $content='';$merged='';
            if(($data['minecraft']??true)&&empty($data['file_only'])){
            $files=app(DaemonFileRepository::class)->setServer($s);$content=$files->getContent('/server.properties',1048576);
            abort_unless(hash_equals($data['hash'],hash('sha256',$content)),409,'server.properties changed. Discard and save the settings again.');
            $path='simplify/settings-backups/'.$s->uuid.'/'.(string)\Illuminate\Support\Str::uuid().'.properties';
            if (!Storage::disk('local')->put($path,$content)) throw new \RuntimeException('Could not create settings backup.');
            $values=$data['properties'];$parsed=PropertiesReader::parse($content);
            foreach(['difficulty'=>['peaceful','easy','normal','hard'],'gamemode'=>['survival','creative','adventure','spectator']] as $key=>$choices) {
                if (isset($values[$key],$parsed[$key]) && preg_match('/^[0-3]$/',$parsed[$key])) $values[$key]=(string)array_search($values[$key],$choices,true);
            }
            $merged=ServerProperties::merge($content,$values);$files->putContent('/server.properties',$merged)->throw();
            if ($data['icon']) { Access::permission($s,SubuserPermission::FileCreate);$files->putContent('/server-icon.png',base64_decode($data['icon'],true))->throw(); }
            }
            if (isset($data['resources'])) ManagedResources::save($s,$data['resources']);
            // Preserve a retryable hash after writing, including a delayed Wings start.
            $data['hash']=hash('sha256',$merged);$data['restart_requested']=true;$data['applied']=true;
            Storage::disk('local')->put(self::path($s),json_encode($data,JSON_THROW_ON_ERROR));
            if ($startAfter) {
                auth()->setUser($restart);Access::permission($s,SubuserPermission::ControlStart);
                $daemon->power('start')->throw();
            }
            Storage::disk('local')->delete(self::path($s));
        });
    }
    public static function failed(Server $s,string $message): void
    {
        $data=self::read($s);if(!$data)return;
        $data['restart_at']=null;$data['restart_requested']=false;$data['error']=mb_substr($message,0,300);
        Storage::disk('local')->put(self::path($s),json_encode($data,JSON_THROW_ON_ERROR));
    }
}
