<?php
declare(strict_types=1);
namespace Simplify\Panel\Filament\App\Pages;
use App\Enums\SubuserPermission as P;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Repositories\Daemon\DaemonFileRepository;
use Simplify\Panel\Services\Access;
use Simplify\Panel\Services\Extensions;
use Simplify\Panel\Services\ManagedSettings;
use Simplify\Panel\Services\ManagedResources;
use Simplify\Panel\Services\PendingChanges;
use Simplify\Panel\Services\PropertiesReader;
use Simplify\Panel\Services\MinecraftStatus;
use Simplify\Panel\Services\AutomaticBackups;
use Simplify\Panel\Services\Packages;
use Simplify\Panel\Services\ServerArtwork;
use Simplify\Panel\Services\OverviewUsage;
use Simplify\Panel\Services\HostHealth;
use Simplify\Panel\Services\ServerAppearance;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\WithFileUploads;
final class Dashboard extends Page
{
    use WithFileUploads;
    protected static ?string $slug='simplify';
    protected static ?string $title='Pelican - Enhanced by Simplify';
    protected static bool $shouldRegisterNavigation=false;
    protected string $view='pelican-simplify::dashboard';
    #[Locked] public ?string $serverUuid=null;
    public $iconUpload=null;
    public $bannerUpload=null;
    public function boot(): void { app()->setLocale('en'); }
    public function mount(): void
    {
        $uuid=request()->query('server');
        if(is_string($uuid) && $uuid!==''){Access::server($uuid);$this->serverUuid=$uuid;}
    }
    private function server(): Server { abort_unless($this->serverUuid,404);return Access::server($this->serverUuid); }
    private ?array $initialData=null;
    /** Everything the page needs on first paint. Computed once per request; actions never re-render it. */
    public function initial(): array
    {
        return $this->initialData ??= $this->buildInitial();
    }
    private function buildInitial(): array
    {
        $admin=user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));
        $base=['banner_upload_bytes'=>\Simplify\Panel\Services\UploadLimits::bannerBytes(),'build_version'=>\Simplify\Panel\Services\BuildInfo::version(),'admin_url'=>$admin?\Filament\Facades\Filament::getPanel('admin')->getUrl():(\Simplify\Panel\Services\RoleAccess::can('legacy')?route(\Simplify\Panel\Services\LegacyNavigation::serverListRoute(),['simplify_legacy'=>1]):null),
            'legacy_create_url'=>\App\Filament\Admin\Resources\Servers\ServerResource::canCreate()?\App\Filament\Admin\Resources\Servers\ServerResource::getUrl('create',panel:'admin'):null,'create_url'=>Extensions::createUrl(),'game_create_url'=>\Simplify\Panel\Services\FeatureSettings::enabled('game_creator')&&Extensions::canCreate()?\Simplify\Panel\Filament\App\Pages\CreateServer::getUrl(panel:'app'):null,'can_create'=>Extensions::canCreate(),'create_games'=>Extensions::createGames(),'web'=>Extensions::web(),
            'native_list_url'=>\Filament\Facades\Filament::getPanel('app')->getUrl(),
            'profile_url'=>\App\Filament\Pages\Auth\EditProfile::getUrl(panel:'app'),'appearance'=>(fn(array $appearance)=>[...$appearance,'brand'=>\Simplify\Panel\Services\BrandSettings::forDisplay($appearance['brand'])])(\Simplify\Panel\Services\AppearanceSettings::all()),'panel_name'=>(string)config('app.name','Pelican'),'features'=>array_replace(\Simplify\Panel\Services\FeatureSettings::all(),array_combine(['settings','files','players','backups','packages','software','resources','advanced','activity','schedules'],array_map(fn($key)=>Access::feature($key),['settings','files','players','backups','packages','software','resources','advanced','activity','schedules']))),'settings_url'=>user()?->isRootAdmin()?\Simplify\Panel\Filament\Admin\Pages\SimplifySettings::getUrl(panel:'app'):null,'website_url'=>Extensions::webAdapter()?->url(),'list_url'=>self::getUrl(panel:'app'),'server'=>null,'servers'=>[],'user'=>user()->username,'avatar'=>user()->getFilamentAvatarUrl(),'favicon_url'=>route('simplify.favicon').'?v='.\Simplify\Panel\Services\BuildInfo::hash('core.css'),
            'performance'=>\Simplify\Panel\Services\PerformanceSettings::client(),'update'=>user()?->isRootAdmin()?\Simplify\Panel\Services\UpdateCheck::status():null,'panel_notifications'=>\Simplify\Panel\Services\PanelNotifications::unread(),'admin_pages'=>\Simplify\Panel\Services\AdminPages::menu(),'health_checks'=>\Simplify\Panel\Services\HealthChecks::latest(),'host_extras'=>\Simplify\Panel\Services\AdminPages::hostExtras(),'host_mode_auto'=>\Simplify\Panel\Services\FeatureSettings::enabled('host_mode')];
        if(!$this->serverUuid){$base['servers']=$this->listing();$base['host_health']=$this->hostHealth();return$base;}
        $s=$this->server();$base['available_colors']=ServerAppearance::choices($s->uuid);$permissions=[];foreach(P::cases() as $p)$permissions[$p->value]=user()->can($p,$s);
        $meta=ManagedSettings::content($s);$mc=MinecraftStatus::get($s);
        $properties=[];
        if(user()->can(P::FileReadContent,$s) && ManagedSettings::minecraft($s)) {
            try{$properties=PropertiesReader::parse(app(DaemonFileRepository::class)->setServer($s)->getContent('/server.properties',1048576));}catch(\Throwable $e){report($e);}
        }
        $pending=PendingChanges::read($s);
        if ($pending && !isset($pending['restart_at']) && empty($pending['error'])) {
            try { PendingChanges::progress($s,true); } catch (\Throwable $e) { /* Keep apply errors in the pending-change banner. */ }
            $pending=PendingChanges::read($s);
        }
        $artworkMeta=Extensions::web()?Extensions::webAdapter()->metadata($s):$meta;
        $base['background_choices']=array_map(fn($id)=>['id'=>$id,'name'=>ucfirst($id),'url'=>ServerArtwork::backgroundUrl($id)],array_keys(ServerArtwork::BACKGROUNDS));
        $base['uploaded_backgrounds']=\Simplify\Panel\Services\ServerBanners::entries($s);
        $base['server']=['external_pages'=>\Simplify\Panel\Services\ExternalPlugins::serverPages($s),'background_id'=>ServerArtwork::selection($s) ?? ($artworkMeta['background_id'] ?? 'auto'),'color'=>ServerAppearance::color($s),'uuid'=>$s->uuid,'short'=>$s->uuid_short,'name'=>$s->name,'address'=>\Simplify\Panel\Services\Subdomains::address($s) ?? $s->allocation?->display_address ?? $s->allocation?->address,
            'memory'=>$s->memory,'disk'=>$s->disk,'cpu'=>$s->cpu,'software'=>$s->egg?->name,'image'=>user()->can(P::StartupRead,$s)?$s->image:'Restricted','startup'=>user()->can(P::StartupRead,$s)?$s->startup:'Restricted',
            'alias'=>\Simplify\Panel\Services\ServerMetadata::read($s)['server_alias']??'','sftp'=>\Simplify\Panel\Services\SftpDetails::forServer($s),'package_kind'=>Packages::capability($s),'native_sections'=>$this->nativeSections($s),
            'owner'=>user()->isRootAdmin() || (int)$s->owner_id===(int)user()->id,'minecraft'=>ManagedSettings::minecraft($s),'can_resources'=>ManagedResources::canEdit($s),'permissions'=>$permissions,
            'properties'=>array_intersect_key($properties,array_flip(['motd','max-players','difficulty','gamemode','pvp','white-list','allow-flight','view-distance','spawn-protection'])),
            'properties_available'=>!ManagedSettings::minecraft($s) || !empty($properties),'pending'=>$pending?['properties'=>user()->can(P::FileReadContent,$s)?($pending['properties']??[]):[],'resources'=>$pending['resources']??[],'pending'=>true,'file_only'=>!empty($pending['file_only']),'file_changes'=>!empty($pending['file_changes']),'restart_at'=>$pending['restart_at'],'restart_requested'=>$pending['restart_requested'],'error'=>$pending['error']??null]:null,
            'pack'=>array_intersect_key($meta,array_flip(['modpack_name','modpack_version','modpack_description','modpack_icon_url','modpack_url','plugins','version','motd','icon_url'])),
            'reported_version'=>$mc['version']??'','setup_state'=>$s->status?->value,'status'=>$s->retrieveStatus()->value,'players'=>$mc['players']??null,'max_players'=>$mc['max_players']??null,'player_names'=>$mc['names']??[],
            'software_icon'=>ServerArtwork::softwareIcon($s,$meta),'server_icon'=>ServerArtwork::serverIcon($s),
            'icon'=>ServerArtwork::icon($s,$meta),'background'=>ServerArtwork::background($s,$artworkMeta),'backup_options'=>AutomaticBackups::settings($s),'root_admin'=>user()->isRootAdmin(),
            'files_url'=>\App\Filament\Server\Resources\Files\FileResource::getUrl('index',panel:'server',tenant:$s),
            'backups_url'=>\App\Filament\Server\Resources\Backups\BackupResource::getUrl('index',panel:'server',tenant:$s),
            'admin_server_url'=>$admin?\App\Filament\Admin\Resources\Servers\ServerResource::getUrl('edit',['record'=>$s],panel:'admin'):null];
        return$base;
    }
    /** Resolve registered pages instead of depending on version-specific URL paths. */
    private function nativeSections(Server $s): array
    {
        $panel=\Filament\Facades\Filament::getPanel('server');
        $sections=[];
        $targets=[
            'schedules'=>['ScheduleResource','SchedulesResource'],
            'activity'=>['Activity','ActivityLog','ActivityLogs','ActivityResource','ActivitiesResource','ActivityLogResource','ActivityLogsResource'],
            'sftp'=>['Settings'],
            'startup'=>['Startup'],
            'databases'=>['DatabaseResource','DatabasesResource'],
            'access'=>['UserResource','UsersResource','SubuserResource','SubusersResource'],
        ];
        foreach ([true=>$panel->getResources(),false=>$panel->getPages()] as $resource=>$classes) {
            foreach ($classes as $class) foreach ($targets as $key=>$names) {
                if (!in_array(class_basename($class),$names,true)) continue;
                try { $sections[$key]=$resource?$class::getUrl('index',panel:'server',tenant:$s):$class::getUrl(panel:'server',tenant:$s); }
                catch (\Throwable $e) { report($e); }
            }
        }
        return $sections;
    }
    /** Pelican's own (legacy) notifications, shown in the Simplify bell. */
    #[Renderless]
    public function panelNotifications(): array { return \Simplify\Panel\Services\PanelNotifications::unread(); }
    #[Renderless]
    public function markPanelNotificationsRead(array $ids): array { \Simplify\Panel\Services\PanelNotifications::markRead($ids); return \Simplify\Panel\Services\PanelNotifications::unread(); }
    #[Renderless]
    public function hostHealth(): array { return \Simplify\Panel\Services\FeatureSettings::enabled('host_health')?HostHealth::snapshot():[]; }
    #[Renderless]
    public function listing(): array
    {
        $list=[];
        $query=Access::overviewQuery();
        foreach($query->get() as $s){
            $linked=user()->isAdmin()||(int)$s->owner_id===(int)user()->id||(bool)$s->simplify_assigned;$m=Extensions::web()?Extensions::webAdapter()->metadata($s):[];
            $status=$s->retrieveStatus()->value;$mc=$status==='offline'?['players'=>0]:MinecraftStatus::get($s);
            $metadata=\Simplify\Panel\Services\ServerMetadata::read($s);$minecraft=ManagedSettings::minecraft($s);$serverIcon=ServerArtwork::serverIcon($s);
            $list[]=['background_id'=>ServerArtwork::selection($s),'alias'=>$metadata['server_alias']??'','color'=>ServerAppearance::color($s),'uuid'=>$s->uuid,'name'=>$s->name,'software'=>$s->egg?->name,'game'=>$minecraft?'minecraft':'other','game_label'=>$minecraft?'Minecraft':($s->egg?->name ?: 'Other game'),'status'=>$status,'linked'=>$linked,
                'node_id'=>(int)$s->node_id,'node_name'=>(string)($s->node?->name??''),'permissions'=>array_combine(['control.start','control.stop','control.restart'],array_map(fn($permission)=>$linked&&user()->can($permission,$s),[P::ControlStart,P::ControlStop,P::ControlRestart])),
                'url'=>$linked?self::getUrl(['server'=>$s->uuid],panel:'app'):null,
                'server_icon'=>$serverIcon,'software_icon'=>ServerArtwork::softwareIcon($s,$m),'egg_icon'=>$s->egg?->icon,
                'icon'=>$serverIcon ?: ServerArtwork::icon($s,$m),'background'=>ServerArtwork::background($s,$m),'players'=>$mc['players']??null,'max_players'=>$mc['max_players']??null,
                'reported_version'=>$mc['version']??'','minecraft_version'=>$metadata['modpack']['version']??'','pack'=>$m['modpack_name']??'','motd'=>\Simplify\Panel\Services\MinecraftMotd::forServer($s,$mc['motd']??null),'visible'=>$m['visible']??false,'usage'=>OverviewUsage::get($s,$status),
                'can_web'=>Extensions::web() && $linked && ((int)$s->owner_id===(int)user()->id || user()->isRootAdmin())];
        }
        return$list;
    }
    #[Renderless]
    public function socket(): array
    {
        $s=$this->server();Access::permission($s,P::WebsocketConnect);
        $token=app(\App\Services\Nodes\NodeJWTService::class)->setExpiresAt(now()->addMinutes(10)->toImmutable())
            ->setScopes(\App\Enums\NodeJwtScope::Websocket)->setUser(user())
            ->setClaims(['server_uuid'=>$s->uuid,'permissions'=>app(\App\Services\Servers\GetUserPermissionsService::class)->handle($s,user())])
            ->handle($s->node,user()->id.$s->uuid)->toString();
        return['token'=>$token,'url'=>str_replace(['https://','http://'],['wss://','ws://'],$s->node->getConnectionAddress()).'/api/servers/'.$s->uuid.'/ws'];
    }
    #[Renderless]
    public function power(string $action): void
    {
        $this->performPower($this->server(),$action);
    }
    #[Renderless]
    public function overviewPower(string $uuid, string $action): void
    {
        $this->performPower(Access::server($uuid),$action);
    }
    private function performPower(Server $s, string $action): void
    {
        $p=match($action){'start'=>P::ControlStart,'stop','kill'=>P::ControlStop,'restart'=>P::ControlRestart,default=>null};abort_unless($p,422);Access::permission($s,$p);
        if($action==='stop' && PendingChanges::read($s)){PendingChanges::requestStop($s);return;}
        if($action==='restart' && PendingChanges::read($s)){PendingChanges::requestRestart($s,0);return;}
        abort_if($s->isInConflictState(),409,'Wait until the current installation or recovery is complete.');
        app(DaemonServerRepository::class)->setServer($s)->power($action)->throw();
    }
    /** Accept the Minecraft EULA for a server that stopped on the EULA prompt, then start it. */
    #[Renderless]
    public function acceptEula(): void
    {
        $s=$this->server();Access::permission($s,P::FileUpdate);Access::permission($s,P::ControlStart);
        abort_if($s->isInConflictState(),409,'Wait until the current installation or recovery is complete.');
        app(DaemonFileRepository::class)->setServer($s)->putContent('/eula.txt',"# Accepted in Simplify by ".user()->username.".\neula=true\n")->throw();
        app(DaemonServerRepository::class)->setServer($s)->power('start')->throw();
    }
    #[Renderless]
    public function fileRevision(string $path): string
    {
        $s=$this->server();Access::permission($s,P::FileReadContent);
        abort_unless(strlen($path)<=4096 && !str_contains($path,"\0") && !in_array('..',explode('/',$path),true),422);
        $limit=(int)config('panel.files.max_edit_size',1048576);
        try { $content=app(DaemonFileRepository::class)->setServer($s)->getContent($path,$limit); }
        catch (\Illuminate\Http\Client\ConnectionException) { abort(503,'The node is not reachable.'); }
        abort_if($limit>0 && strlen($content)>$limit,413,'This file is too large to edit.');
        return hash('sha256',$content);
    }
    #[Renderless]
    public function saveAlias(string $alias): array
    {
        $s=$this->server();Access::permission($s,P::SettingsRename);
        $state=validator(['alias'=>trim($alias)],['alias'=>['nullable','string','max:80','regex:/^[^\\x00-\\x1F\\x7F]*$/uD']])->validate();
        \Simplify\Panel\Services\ServerMetadata::save($s,['server_alias'=>$state['alias']??'']);
        return ['alias'=>$state['alias']??''];
    }
    #[Renderless]
    public function saveSettings(array $state): array { abort_unless(Access::feature('settings'),404);return PendingChanges::stage($this->server(),$state,$this->iconUpload); }
    #[Renderless]
    public function saveResourceLimits(array $state): array
    {abort_unless(Access::feature('resources'),404);
        $s=$this->server();Access::owner($s);abort_unless(ManagedResources::canEdit($s),403);
        $state=array_intersect_key($state,array_flip(['memory_gb','cpu','disk_mb']));
        abort_unless(isset($state['memory_gb'],$state['cpu'],$state['disk_mb']),422,'Provide all three resource limits.');
        return PendingChanges::stage($s,$state,null,true);
    }
    #[Renderless]
    public function discard(): void { PendingChanges::discard($this->server()); }
    #[Renderless]
    public function fileChanged(string $path,string $previousHash): array
    {
        $s=$this->server();Access::permission($s,P::FileUpdate);
        abort_unless(preg_match('/^[a-f0-9]{64}$/D',$previousHash),422);
        if(!hash_equals($previousHash,$this->fileRevision($path)))PendingChanges::fileChanged($s);
        return $this->refreshInfo();
    }
    #[Renderless]
    public function restartChanges(int $minutes=0): void { PendingChanges::requestRestart($this->server(),$minutes); }
    #[Renderless]
    public function rename(string $name): void
    {
        $s=$this->server();Access::permission($s,P::SettingsRename);
        $name=trim($name);validator(['name'=>$name],['name'=>'required|string|max:255'])->validate();$s->update(['name'=>$name]);
    }
    #[Renderless]
    public function saveServerBackground(string $id): array
    {
        return ServerArtwork::save($this->server(), $id);
    }
    public function uploadServerBanner(): array
    {
        $server=$this->server();$data=\Simplify\Panel\Services\ServerBanners::upload($server,$this->bannerUpload);
        $this->bannerUpload=null;
        return [...ServerArtwork::save($server,$data['id']),'uploaded_backgrounds'=>\Simplify\Panel\Services\ServerBanners::entries($server)];
    }
    #[Renderless]
    public function deleteServerBanner(string $id): array
    {
        $server=$this->server();\Simplify\Panel\Services\ServerBanners::delete($id,$server);
        return ['background_id'=>ServerArtwork::selection($server)??'auto','background'=>ServerArtwork::background($server,[]),'uploaded_backgrounds'=>\Simplify\Panel\Services\ServerBanners::entries($server)];
    }
    #[Renderless]
    public function refreshInfo(): array
    {
        $s=$this->server();$p=PendingChanges::read($s);
        if ($p && (!isset($p['restart_at']) || $p['restart_at']<=time())) {
            try { PendingChanges::progress($s,!isset($p['restart_at'])); } catch (\Throwable $e) { /* Error remains visible in the pending-change banner. */ }
            $p=PendingChanges::read($s);
        }
        $data=MinecraftStatus::get($s);
        return['setup_state'=>$s->fresh()?->status?->value,'color'=>ServerAppearance::color($s),'name'=>$s->name,'server_icon'=>ServerArtwork::serverIcon($s),'reported_version'=>$data['version']??'','players'=>$data['players']??null,'max_players'=>$data['max_players']??null,'player_names'=>$data['names']??[],
            'pending'=>$p?['pending'=>true,'file_only'=>!empty($p['file_only']),'file_changes'=>!empty($p['file_changes']),'restart_at'=>$p['restart_at'],'restart_requested'=>$p['restart_requested'],'error'=>$p['error']??null]:null];
    }
    #[Renderless]
    public function availableServerColors(): array { return ServerAppearance::choices($this->server()->uuid); }
    #[Renderless]
    public function saveServerColor(string $color): string { return ServerAppearance::save($this->server(),$color); }
    #[Renderless]
    public function website(?string $uuid=null): array
    {
        abort_unless(Extensions::web(),404);$s=$uuid?Access::server($uuid):$this->server();Access::owner($s);
        return Extensions::webAdapter()->metadata($s);
    }
    #[Renderless]
    public function saveWebsite(array $input,?string $uuid=null): void
    {
        abort_unless(Extensions::web(),404);$s=$uuid?Access::server($uuid):$this->server();Access::owner($s);
        $input=array_intersect_key($input,array_flip(['visible','description','background_id','motd','icon_url']));
        // Checked here as well, whatever the website extension does with it.
        $valid=\Illuminate\Support\Facades\Validator::make($input,[
            'visible'=>'sometimes|boolean','description'=>'sometimes|nullable|string|max:1000','motd'=>'sometimes|nullable|string|max:500',
            'background_id'=>['sometimes','nullable','string','max:64','regex:/^[A-Za-z0-9_-]+$/'],'icon_url'=>'sometimes|nullable|string|max:2048|url:https',
        ])->validate();
        $old=Extensions::webAdapter()->metadata($s);
        Extensions::webAdapter()->save($s,array_replace($old,$valid));
    }
    #[Renderless]
    public function packageSearch(string $query,string $version): array {abort_unless(Access::feature('packages'),404); return Packages::search($this->server(),$query,$version); }
    #[Renderless]
    public function installedPackages(): array {abort_unless(Access::feature('packages'),404); return Packages::installed($this->server()); }
    #[Renderless]
    public function installPackage(string $id,string $version): void {abort_unless(Access::feature('packages'),404); Packages::install($this->server(),$id,$version); }
    #[Renderless]
    public function removePackage(string $filename): void {abort_unless(Access::feature('packages'),404); Packages::remove($this->server(),$filename); }
    #[Renderless]
    public function players(): array
    {abort_unless(Access::feature('players'),404);
        $s=$this->server();Access::permission($s,P::FileReadContent);$files=app(DaemonFileRepository::class)->setServer($s);$lists=[];
        foreach(['whitelist','ops','banned-players'] as $key){try{$list=json_decode($files->getContent('/'.$key.'.json',1048576),true);$lists[$key]=is_array($list)?$list:[];}catch(\Throwable){$lists[$key]=[];}}
        return$lists;
    }
    #[Renderless]
    public function subdomain(string $name): ?array
    {
        $s=$this->server();Access::owner($s);
        validator(['name'=>$name],['name'=>'nullable|string|max:63|regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/'])->validate();
        if(\Simplify\Panel\Services\Subdomains::active()){
            // Returned rather than thrown, so the dashboard can show why a name is refused.
            try{\Simplify\Panel\Services\Subdomains::request($s,(string)$name);}catch(\Illuminate\Validation\ValidationException $e){return ['error'=>(string)collect($e->errors())->flatten()->first()];}
            return null;
        }
        abort_unless(Extensions::web(),404);Extensions::webAdapter()->subdomain($s,$name);return null;
    }
    #[Renderless]
    public function deleteServer(string $confirmation): void
    {
        $s=$this->server();abort_unless(Access::feature('advanced'),404);Access::owner($s);Access::offline($s);abort_unless(hash_equals($s->name,$confirmation),422,'Enter the exact server name.');
        app(\App\Services\Servers\ServerDeletionService::class)->handle($s);
        $this->serverUuid=null;
        $this->redirect(self::getUrl(panel:'app'));
    }
    #[Renderless]
    public function backupOptions(array $state): void {abort_unless(Access::feature('backups'),404); AutomaticBackups::save($this->server(),$state); }
    #[Renderless]
    public function backupList(): array
    {abort_unless(Access::feature('backups'),404);
        $s=$this->server();Access::permission($s,P::BackupRead);
        return ['configured'=>$s->node->backupHosts()->exists() || \App\Models\BackupHost::doesntHave('nodes')->exists(),
            'items'=>$s->backups()->orderByDesc('created_at')->get()->map(fn($b)=>['uuid'=>$b->uuid,'name'=>$b->name,'bytes'=>$b->bytes,
                'created_at'=>$b->created_at?->toIso8601String(),'successful'=>$b->is_successful,'completed'=>(bool)$b->completed_at])->all()];
    }
    #[Renderless]
    public function downloadBackup(string $uuid): string
    {abort_unless(Access::feature('backups'),404);
        $s=$this->server();Access::permission($s,P::BackupDownload);$b=$s->backups()->where('uuid',$uuid)->firstOrFail();
        abort_unless($b->is_successful && $b->completed_at,409);
        return app(\App\Services\Backups\DownloadLinkService::class)->handle($b,user());
    }
    #[Renderless]
    public function restoreBackup(string $uuid,string $confirm): void
    {abort_unless(Access::feature('backups'),404);
        $s=$this->server();Access::permission($s,P::BackupRestore);Access::offline($s);abort_unless(hash_equals($s->name,$confirm),422,'Enter the server name.');
        $b=$s->backups()->where('uuid',$uuid)->firstOrFail();abort_unless($b->is_successful && $b->completed_at,409);
        $url=app(\App\Services\Backups\DownloadLinkService::class)->handle($b,user());
        $s->update(['status'=>\App\Enums\ServerState::RestoringBackup]);
        try{app(\App\Repositories\Daemon\DaemonBackupRepository::class)->setServer($s)->restore($b,$url,false)->throw();}
        catch(\Throwable $e){$s->update(['status'=>null]);throw$e;}
    }
    #[Renderless]
    public function connectionData(): array
    {
        $s=$this->server();Access::permission($s,P::StartupRead);
        if(\Simplify\Panel\Services\Subdomains::active())return ['subdomains'=>true,'requested_subdomain'=>($state=\Simplify\Panel\Services\Subdomains::state($s))['name'],'suggested_subdomain'=>$state['name']===''?\Simplify\Panel\Services\Subdomains::suggest((string)$s->name,$s->uuid):'','subdomain'=>$state,'address'=>\Simplify\Panel\Services\Subdomains::address($s)];
        return ['requested_subdomain'=>Extensions::web()?Extensions::webAdapter()->metadata($s)['requested_subdomain']??'':''];
    }
    #[Renderless]
    public function advancedData(): array { abort_unless(Access::feature('advanced')||Access::feature('software'),404);return \Simplify\Panel\Services\Software::data($this->server()); }
    #[Renderless]
    public function packSearch(string $query): array {abort_unless(Access::feature('software'),404); $this->server();return \Simplify\Panel\Services\ModpackCatalog::results($query); }
    #[Renderless]
    public function packVersions(string $key): array {abort_unless(Access::feature('software'),404); $this->server();return \Simplify\Panel\Services\ModpackCatalog::versions($key); }
    #[Renderless]
    public function changeSoftware(array $state,string $confirmation): void {abort_unless(Access::feature('software'),404); \Simplify\Panel\Services\Software::change($this->server(),$state,$confirmation); }
}
