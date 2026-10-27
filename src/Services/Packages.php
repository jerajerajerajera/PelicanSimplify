<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Server;
use App\Enums\SubuserPermission;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Support\Facades\Http;
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
        if ($flavour==='modded'||$flavour==='loader') return $mods?'mods':'';
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
    private static function json(string $path,array $query=[]): array
    {
        return Http::acceptJson()->withHeaders(['User-Agent'=>'PelicanSimplify/0.1.0'])->connectTimeout(3)->timeout(10)->get('https://api.modrinth.com/v2/'.$path,$query)->throw()->json();
    }
    public static function search(Server $s,string $q,string $version): array
    {
        Access::permission($s,SubuserPermission::FileRead);$ctx=self::context($s);
        abort_unless($ctx['loader'],422,'This server software does not support installable mods or plugins.');
        $version=$ctx['version']?:$version;abort_unless(preg_match('/^[0-9a-zA-Z._-]{1,40}$/',$version),422,'Enter the exact Minecraft version first.');
        $types=$ctx['directory']==='/plugins'?['project_type:plugin','project_type:mod']:['project_type:mod'];
        $loaders=in_array($ctx['loader'],['paper','purpur'],true)?['paper','spigot','bukkit']:($ctx['loader']==='spigot'?['spigot','bukkit']:[$ctx['loader']]);
        $result=self::json('search',['query'=>mb_substr($q,0,80),'limit'=>12,'facets'=>json_encode([$types,array_map(fn($l)=>'categories:'.$l,$loaders),['versions:'.$version]])]);
        return ['context'=>[...$ctx,'version'=>$version],'items'=>array_map(fn($h)=>array_intersect_key($h,array_flip(['project_id','title','description','icon_url','slug'])), $result['hits']??[])];
    }
    public static function installed(Server $s): array
    {
        Access::permission($s,SubuserPermission::FileRead);
        $ctx=self::context($s);if(!$ctx['loader'])return[];
        try { $files=app(DaemonFileRepository::class)->setServer($s)->getDirectory($ctx['directory']); } catch(\Throwable) { $files=[]; }
        $tracked=JsonFile::read('simplify/packages/'.$s->uuid.'.json');
        return ['context'=>$ctx,'tracked'=>$tracked,'files'=>array_values(array_filter($files,fn($f)=>str_ends_with(strtolower($f['name']??''),'.jar')))];
    }
    public static function install(Server $s,string $project,string $version): void
    {
        Access::permission($s,SubuserPermission::FileRead);
        Access::permission($s,SubuserPermission::FileCreate);Access::permission($s,SubuserPermission::FileUpdate);Access::offline($s);
        abort_unless(preg_match('/^[a-zA-Z0-9_-]{1,128}$/',$project),422);
        $ctx=self::context($s);abort_unless($ctx['loader'],422);
        $version=$ctx['version']?:$version;abort_unless(preg_match('/^[0-9a-zA-Z._-]{1,40}$/',$version),422);
        $loaders=in_array($ctx['loader'],['paper','purpur'],true)?['paper','spigot','bukkit']:($ctx['loader']==='spigot'?['spigot','bukkit']:[$ctx['loader']]);
        $releases=self::json('project/'.rawurlencode($project).'/version',['loaders'=>json_encode($loaders),'game_versions'=>json_encode([$version])]);
        $release=null;foreach($releases as $r) if(($r['version_type']??'')==='release' && array_intersect($loaders,$r['loaders']??[]) && in_array($version,$r['game_versions']??[])){$release=$r;break;}
        abort_unless($release,422,'No compatible stable release was found.');
        $file=null;foreach($release['files']??[] as $f) if(($f['primary']??false) && str_ends_with(strtolower($f['filename']??''),'.jar')){$file=$f;break;}
        abort_unless($file && preg_match('/^[a-zA-Z0-9._+ -]+\.jar$/',$file['filename']) && parse_url($file['url'],PHP_URL_SCHEME)==='https' && parse_url($file['url'],PHP_URL_HOST)==='cdn.modrinth.com',422,'Unsupported package download.');
        $path='simplify/packages/'.$s->uuid.'.json';$tracked=JsonFile::read($path);
        $files=app(DaemonFileRepository::class)->setServer($s);
        // Dependencies are shown in the UI; refuse automatic install until required
        // dependencies are already tracked or are included by this package.
        foreach($release['dependencies']??[] as $dep) if(($dep['dependency_type']??'')==='required' && !empty($dep['project_id'])) {
            abort_unless(isset($tracked[$dep['project_id']]),422,'Install required dependency '.$dep['project_id'].' first.');
        }
        if (isset($tracked[$project]) && $tracked[$project]['filename']!==$file['filename']) {
            Access::permission($s,SubuserPermission::FileDelete);
            $archive='/.simplify-package-backups';try{$files->createDirectory('.simplify-package-backups','/')->throw();}catch(\Throwable){$files->getDirectory($archive);}
            $files->renameFiles('/',[['from'=>ltrim($ctx['directory'],'/').'/'.$tracked[$project]['filename'],'to'=>'.simplify-package-backups/'.time().'-'.$tracked[$project]['filename']]])->throw();
        }
        $files->pull($file['url'],$ctx['directory'],['filename'=>$file['filename'],'foreground'=>true])->throw();
        $tracked[$project]=['project_id'=>$project,'filename'=>$file['filename'],'version_id'=>$release['id'],'version'=>$release['version_number'],'directory'=>$ctx['directory']];
        Storage::disk('local')->put($path,json_encode($tracked,JSON_THROW_ON_ERROR));
    }
    public static function remove(Server $s,string $filename): void
    {
        Access::permission($s,SubuserPermission::FileDelete);Access::permission($s,SubuserPermission::FileUpdate);Access::offline($s);
        abort_unless(preg_match('/^[a-zA-Z0-9._+ -]+\.jar$/',$filename),422);
        $ctx=self::context($s);abort_unless($ctx['loader'],422);
        $files=app(DaemonFileRepository::class)->setServer($s);
        try{$files->createDirectory('.simplify-package-backups','/')->throw();}catch(\Throwable){$files->getDirectory('/.simplify-package-backups');}
        $files->renameFiles('/',[['from'=>ltrim($ctx['directory'],'/').'/'.$filename,'to'=>'.simplify-package-backups/'.time().'-'.$filename]])->throw();
        $path='simplify/packages/'.$s->uuid.'.json';$tracked=JsonFile::read($path);
        foreach($tracked as $id=>$item)if($item['filename']===$filename)unset($tracked[$id]);
        Storage::disk('local')->put($path,json_encode($tracked,JSON_THROW_ON_ERROR));
    }
}
