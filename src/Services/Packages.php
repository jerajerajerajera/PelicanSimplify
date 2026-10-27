<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Server;
use App\Enums\SubuserPermission;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Support\Facades\Storage;
final class Packages
{
    /** Cheap classification for the dashboard: no Modrinth or Wings requests. */
    public static function capability(Server $s): string
    {
        if (!ManagedSettings::minecraft($s)) return '';
        $flavour=ServerMetadata::read($s)['server_flavour']??null;
        $egg=strtolower((string)$s->egg?->name);
        $plugins=preg_match('/paper|purpur|spigot|bukkit/',$egg)===1;
        $mods=preg_match('/neoforge|forge|fabric|quilt|modrinth|curseforge|ftb|technic|tekkit|hexxit|blightfall|b-team/',$egg)===1;
        if ($flavour==='vanilla') return '';
        if ($flavour==='plus') return $plugins?'plugins':'';
        if ($flavour==='modded') return $mods?'mods':'';
        return $plugins?'plugins':($mods?'mods':'');
    }
    public static function context(Server $s): array
    {
        $kind=self::capability($s);
        if (!$kind) return ['loader'=>'','version'=>'','directory'=>'','kind'=>''];
        $egg=strtolower($s->egg->name);
        $loader=match(true) { str_contains($egg,'neoforge')=>'neoforge',str_contains($egg,'fabric')=>'fabric',str_contains($egg,'quilt')=>'quilt',str_contains($egg,'forge')=>'forge',str_contains($egg,'purpur')=>'purpur',str_contains($egg,'paper')=>'paper',str_contains($egg,'spigot')=>'spigot',str_contains($egg,'bukkit')=>'bukkit',default=>'' };
        $version='';foreach($s->variables as $v) if(in_array($v->env_variable,['MINECRAFT_VERSION','MC_VERSION','SERVER_VERSION'])) $version=(string)($v->server_value??$v->default_value);
        if ($version==='latest') $version='';
        if ($version==='' && $kind==='plugins') { try { $version=self::runningVersion($s); } catch(\Throwable) {} }
        $env=[];foreach($s->variables as $v)$env[$v->env_variable]=(string)($v->server_value??$v->default_value);
        $provider=str_contains($egg,'ftb')?'ftb':(str_contains($egg,'modrinth')?'modrinth':'');
        if($provider){
            $id=$env[$provider==='ftb'?'FTB_MODPACK_ID':'PROJECT_ID']??'';$release=$env[$provider==='ftb'?'FTB_MODPACK_VERSION_ID':'VERSION_ID']??'';
            $runtime=ModpackCatalog::runtime($provider.':'.$id,$release);$version=$version?:($runtime['minecraft']??'');
        }
        if(!$loader && $provider==='modrinth' && preg_match('/^[a-zA-Z0-9_-]{1,128}$/',$release??'')) {
            $metadata=ModpackCatalog::json('https://api.modrinth.com/v2/version/'.rawurlencode($release));
            foreach(['neoforge','fabric','forge','quilt'] as $candidate)if(in_array($candidate,$metadata['loaders']??[],true)){$loader=$candidate;break;}
        }
        if(!$loader && $provider==='ftb') {
            $files=app(DaemonFileRepository::class)->setServer($s);
            foreach(['neoforge'=>'/libraries/net/neoforged/neoforge','forge'=>'/libraries/net/minecraftforge/forge','fabric'=>'/.fabric'] as $candidate=>$dir){
                try{if($files->getDirectory($dir)){$loader=$candidate;break;}}catch(\Throwable){}
            }
        }
        if (($kind==='plugins')!==in_array($loader,['paper','purpur','spigot','bukkit'],true)) $loader='';
        return ['loader'=>$loader,'version'=>$version,'directory'=>$kind==='plugins'?'/plugins':'/mods','kind'=>$kind];
    }
    /** Paper/Purpur servers set to "latest": the version they really run, from the file Paper writes on start. */
    private static function runningVersion(Server $s): string
    {
        return (string) Remember::value('simplify.packages.running.'.$s->uuid, 300, function () use ($s): ?string {
            $history = json_decode(app(DaemonFileRepository::class)->setServer($s)->getContent('/version_history.json', 65536), true);
            return preg_match('/\(MC: ([0-9][0-9a-zA-Z._-]{0,39})\)/', (string) ($history['currentVersion'] ?? ''), $m) ? $m[1] : null;
        }, 60);
    }
    private static function target(Server $s,string $version): array
    {
        $ctx=self::context($s);abort_unless($ctx['loader'],422,'This server software does not support installable mods or plugins.');
        $version=$ctx['version']?:$version;abort_unless(preg_match('/^[0-9a-zA-Z._-]{1,40}$/',$version),422,'Enter the exact Minecraft version first.');
        return [...$ctx,'version'=>$version];
    }
    public static function search(Server $s,string $q,string $version): array
    {
        Access::permission($s,SubuserPermission::FileRead);$ctx=self::target($s,$version);
        $results=PluginSources::fetchAll(PluginSources::searchUrls($q,$ctx['loader'],$ctx['kind'],CurseForge::hasKey()));
        abort_if(array_filter($results)===[],503,'The plugin sites did not answer. Try again in a moment.');
        return ['context'=>$ctx,'items'=>PluginSources::merge($results,$q,$ctx['version'],$ctx['kind']),'offline'=>array_keys(array_filter($results,fn($r)=>$r===null))];
    }
    /** What "Install" would download for each search result: version, site and whether it is made for this version. */
    public static function details(Server $s,array $keys,string $version): array
    {
        Access::permission($s,SubuserPermission::FileRead);$ctx=self::target($s,$version);
        return array_map(fn($r)=>array_diff_key($r,array_flip(['url','needs'])),PluginSources::resolveMany($keys,$ctx['version'],$ctx['loader'],$ctx['kind']));
    }
    public static function installed(Server $s): array
    {
        Access::permission($s,SubuserPermission::FileRead);
        $ctx=self::context($s);if(!$ctx['loader'])return[];
        try { $files=app(DaemonFileRepository::class)->setServer($s)->getDirectory($ctx['directory']); } catch(\Throwable) { $files=[]; }
        $tracked=self::tracked($s);$byFile=[];foreach($tracked as $t)$byFile[$t['filename']]=$t;
        $items=[];
        foreach($files as $f){
            $name=(string)($f['name']??'');if(!preg_match('/\.jar(\.disabled)?$/i',$name))continue;
            $on=!str_ends_with(strtolower($name),'.disabled');$t=$byFile[preg_replace('/\.disabled$/i','',$name)]??null;
            [$title,$v]=PluginSources::fromFilename($name);
            $items[]=['file'=>$name,'enabled'=>$on,'title'=>$t['title']??$title,'version'=>$t['version']??$v,'source'=>$t['source']??'','label'=>$t?(PluginSources::LABELS[$t['source']]??'').(!empty($t['via'])?' via '.$t['via']:''):'Added by hand',
                'key'=>$t['key']??'','icon'=>$t['icon']??'','auto_off'=>!$on?($t['auto_off']??''):'','watch'=>$on&&!empty($t['watch']),'page'=>$t['page']??'','size'=>(int)($f['size']??0)];
        }
        usort($items,fn($a,$b)=>strcasecmp($a['title'],$b['title']));
        return ['context'=>[...$ctx,'curseforge'=>CurseForge::hasKey(),'curseforge_own'=>CurseForge::allowUserKeys(),'curseforge_mine'=>CurseForge::userHasKey()],'items'=>$items];
    }
    /** Tracked installs, keyed by "source:id". Entries from before 0.5.3 were Modrinth only, keyed by project id. */
    private static function tracked(Server $s): array
    {
        $out=[];
        foreach(JsonFile::read('simplify/packages/'.$s->uuid.'.json') as $id=>$t){
            if(!is_array($t)||empty($t['filename']))continue;
            $key=$t['key']??('modrinth:'.($t['project_id']??$id));
            $out[$key]=['key'=>$key,'source'=>$t['source']??'modrinth',...$t];
        }
        return $out;
    }
    private static function saveTracked(Server $s,array $tracked): void
    {
        Storage::disk('local')->put('simplify/packages/'.$s->uuid.'.json',json_encode($tracked,JSON_THROW_ON_ERROR));
    }
    public static function install(Server $s,string $key,string $version,bool $anyway=false,array $card=[]): array
    {
        Access::permission($s,SubuserPermission::FileRead);
        Access::permission($s,SubuserPermission::FileCreate);Access::permission($s,SubuserPermission::FileUpdate);Access::offline($s);
        if(preg_match('/^[a-zA-Z0-9_-]{1,128}$/',$key))$key='modrinth:'.$key;
        abort_unless(PluginSources::parseKey($key),422,'Unknown plugin.');
        $ctx=self::target($s,$version);
        $release=PluginSources::resolve($key,$ctx['version'],$ctx['loader'],$ctx['kind']);
        abort_unless($release['installable']??false,422,$release['reason']??'This plugin cannot be installed automatically.');
        abort_unless($release['fits']||$anyway,409,'There is no release for Minecraft '.$ctx['version'].'.');
        abort_unless(PluginSources::downloadable($release['url'],$release['filename']),422,'Unsupported package download.');
        $tracked=self::tracked($s);
        // Modrinth lists dependencies by project; refuse until required ones are installed.
        foreach($release['needs']??[] as $dep) abort_unless(isset($tracked['modrinth:'.$dep]),422,'Install required dependency '.$dep.' first.');
        $files=app(DaemonFileRepository::class)->setServer($s);
        $old=$tracked[$key]['filename']??null;
        if ($old!==null && $old!==$release['filename']) self::archive($s,$ctx['directory'],[$old]);
        $files->pull($release['url'],$ctx['directory'],['filename'=>$release['filename'],'foreground'=>true])->throw();
        $tracked[$key]=['key'=>$key,'source'=>$release['source'],'via'=>$release['via']??'','filename'=>$release['filename'],'version'=>$release['version'],'version_id'=>$release['version_id'],'directory'=>$ctx['directory'],
            'title'=>mb_substr((string)($card['title']??($tracked[$key]['title']??PluginSources::fromFilename($release['filename'])[0])),0,80),
            'icon'=>str_starts_with((string)($card['icon']??''),'https://')?$card['icon']:($tracked[$key]['icon']??''),
            'page'=>str_starts_with((string)($card['page']??''),'https://')?$card['page']:($tracked[$key]['page']??''),'fits'=>$release['fits'],
            // Watched until the next start: a plugin that fails to load then is switched off (checkHealth).
            'watch'=>$ctx['kind']==='plugins'?time():0];
        self::saveTracked($s,$tracked);
        return ['version'=>$release['version'],'label'=>$release['label'],'fits'=>$release['fits']];
    }
    /** Whether a plugin installed here waits for its first start (cheap: one local file, for the server page). */
    public static function watching(Server $s): bool
    {
        foreach(JsonFile::read('simplify/packages/'.$s->uuid.'.json') as $t) if(is_array($t)&&!empty($t['watch'])) return true;
        return false;
    }
    /**
     * After a start: plugins installed through Simplify that failed to load are switched off (.jar.disabled) so the next
     * start is clean, and listed with the log line. Plugins that loaded fine are no longer watched.
     */
    public static function checkHealth(Server $s): array
    {
        Access::permission($s,SubuserPermission::FileRead);
        $tracked=self::tracked($s);$watched=array_filter($tracked,fn($t)=>!empty($t['watch']));
        if(!$watched) return ['disabled'=>[],'watching'=>false];
        $files=app(DaemonFileRepository::class)->setServer($s);
        try {
            $at=0;foreach($files->getDirectory('/logs') as $f) if(($f['name']??'')==='latest.log') $at=(int)strtotime((string)($f['modified']??$f['modified_at']??''));
            // The log must be from a start after the install; installing needs a stopped server, so the next start writes a new one.
            if($at<min(array_column($watched,'watch'))) return ['disabled'=>[],'watching'=>true];
            $log=$files->getContent('/logs/latest.log',4194304);
        } catch(\Throwable) { return ['disabled'=>[],'watching'=>true]; }
        $failed=PluginSources::failures($log,$watched);
        $ready=preg_match('/Done \(\d/',$log)===1;
        $disabled=[];
        foreach($watched as $key=>$t){
            if(isset($failed[$key])){
                $dir=ltrim((string)($t['directory']??'/plugins'),'/');
                try { $files->renameFiles('/',[['from'=>$dir.'/'.$t['filename'],'to'=>$dir.'/'.$t['filename'].'.disabled']])->throw(); } catch(\Throwable) { continue; }
                $tracked[$key]=[...$t,'watch'=>0,'auto_off'=>$failed[$key]['reason'],'auto_off_lines'=>$failed[$key]['lines']];
                $disabled[]=['title'=>$t['title']??$t['filename'],'version'=>$t['version']??'','reason'=>$failed[$key]['reason'],'file'=>$t['filename'].'.disabled'];
            } elseif($ready) $tracked[$key]=[...$t,'watch'=>0];
        }
        self::saveTracked($s,$tracked);
        return ['disabled'=>$disabled,'watching'=>(bool)array_filter($tracked,fn($t)=>!empty($t['watch']))];
    }
    /** The saved log lines of a plugin Simplify switched off, explained by the AI an admin set up. */
    public static function explainFailure(Server $s,string $filename): array
    {
        Access::permission($s,SubuserPermission::FileRead);
        $base=preg_replace('/\.disabled$/','',$filename);
        foreach(self::tracked($s) as $t) if($t['filename']===$base&&!empty($t['auto_off_lines'])) return ErrorExplainer::explainPlugin($s,$t['auto_off_lines']);
        return ['ok'=>false,'error'=>'There are no saved log lines for this plugin.'];
    }
    /** Moves files to the backup folder instead of deleting them. */
    private static function archive(Server $s,string $directory,array $names): void
    {
        Access::permission($s,SubuserPermission::FileDelete);
        $files=app(DaemonFileRepository::class)->setServer($s);
        try{$files->createDirectory('.simplify-package-backups','/')->throw();}catch(\Throwable){$files->getDirectory('/.simplify-package-backups');}
        $files->renameFiles('/',array_map(fn($n)=>['from'=>ltrim($directory,'/').'/'.$n,'to'=>'.simplify-package-backups/'.time().'-'.$n],$names))->throw();
    }
    public static function remove(Server $s,string $filename): void
    {
        Access::permission($s,SubuserPermission::FileDelete);Access::permission($s,SubuserPermission::FileUpdate);Access::offline($s);
        abort_unless(preg_match('/^[a-zA-Z0-9._+ -]+\.jar(\.disabled)?$/',$filename),422);
        $ctx=self::context($s);abort_unless($ctx['loader'],422);
        self::archive($s,$ctx['directory'],[$filename]);
        $base=preg_replace('/\.disabled$/','',$filename);
        self::saveTracked($s,array_filter(self::tracked($s),fn($t)=>$t['filename']!==$base));
    }
    /** Off = renamed to .jar.disabled, which the server skips on start. */
    public static function toggle(Server $s,string $filename,bool $on): void
    {
        Access::permission($s,SubuserPermission::FileUpdate);Access::offline($s);
        abort_unless(preg_match('/^[a-zA-Z0-9._+ -]+\.jar(\.disabled)?$/',$filename),422);
        $ctx=self::context($s);abort_unless($ctx['loader'],422);
        $base=preg_replace('/\.disabled$/','',$filename);$target=$on?$base:$base.'.disabled';
        if($target===$filename)return;
        $dir=ltrim($ctx['directory'],'/');
        app(DaemonFileRepository::class)->setServer($s)->renameFiles('/',[['from'=>$dir.'/'.$filename,'to'=>$dir.'/'.$target]])->throw();
        $tracked=self::tracked($s);
        foreach($tracked as $key=>$t) if($t['filename']===$base) $tracked[$key]=[...$t,'auto_off'=>'','auto_off_lines'=>[],'watch'=>$on?time():0];
        self::saveTracked($s,$tracked);
    }
}
