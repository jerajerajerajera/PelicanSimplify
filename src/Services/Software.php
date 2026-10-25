<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Server;
use App\Models\Egg;
use App\Enums\SubuserPermission;
final class Software
{
    public static function data(Server $s): array
    {
        Access::permission($s,SubuserPermission::StartupRead);
        $eggs=[];foreach(Egg::orderBy('name')->get() as $e) {
            if(preg_match('/minecraft|vanilla|paper|purpur|spigot|ftb|modrinth|tekkit|hexxit|forge|fabric|curseforge/i',$e->name))$eggs[]=['id'=>$e->id,'name'=>$e->name];
        }
        return ['eggs'=>$eggs,'image'=>$s->image,'startup'=>$s->startup,'requested_subdomain'=>self::requestedSubdomain($s),
            'note'=>'Runtime is selected automatically. Administrators can override it in Pelican.'];
    }
    private static function requestedSubdomain(Server $s): string
    {
        return (string)(JsonFile::read('ssc/website-visibility/'.$s->uuid.'.json')['requested_subdomain']??'');
    }
    public static function change(Server $s,array $state,string $confirm): void
    {
        Access::permission($s,SubuserPermission::SettingsReinstall);Access::owner($s);Access::offline($s);
        abort_if(PendingChanges::read($s)!==null,409,'Apply or discard staged settings before changing software.');
        abort_unless(hash_equals($s->name,$confirm),422,'Enter the exact server name.');
        abort_unless($s->backups()->where('is_successful',true)->whereNotNull('completed_at')->where('created_at','>=',now()->subMinutes(30))->exists(),409,'Wait for a successful backup made within the last 30 minutes before changing software.');
        $state=validator($state,['server_type'=>'required|in:vanilla,plus,modded','egg_id'=>'nullable|integer','minecraft_version'=>'nullable|string|max:40','modpack_project'=>'nullable|string|max:150','modpack_version'=>'nullable|string|max:150'])->validate();
        if($state['server_type']==='modded')$egg=ModpackCatalog::egg($state['modpack_project']??'');
        else $egg=Egg::find($state['egg_id']??0);
        abort_unless($egg,422,'The required egg is not installed. Ask an administrator to import it.');
        abort_unless(preg_match('/minecraft|vanilla|paper|purpur|spigot|ftb|modrinth|tekkit|hexxit|forge|fabric|curseforge/i',$egg->name),422);
        $env=[];foreach($egg->variables as $v)$env[$v->env_variable]=$v->default_value;
        if($state['server_type']==='modded')$env=ModpackCatalog::applyVariables($egg,$state,$env);
        else {
            $version=$state['minecraft_version']??'';
            abort_unless(preg_match('/^(?:1\.[0-9]+(?:\.[0-9]+)?|2[6-9]\.[0-9]+(?:\.[0-9]+)?)$/',$version),422,'Enter an exact Minecraft release version.');
            $key=null;foreach(['MINECRAFT_VERSION','MC_VERSION','SERVER_VERSION'] as $k)if(array_key_exists($k,$env)){$key=$k;break;}
            abort_unless($key,422,'This egg has no supported Minecraft version setting.');$env[$key]=$version;
            if(isset($env['BUILD_NUMBER']))$env['BUILD_NUMBER']='latest';
        }
        $image=JavaRuntime::requireImage([...$state,'java_version'=>'auto'],$egg);
        // Only trusted egg defaults and validated provider/version IDs are supplied.
        $old=['egg_id'=>$s->egg_id,'docker_image'=>$s->image,'startup'=>$s->startup,'skip_scripts'=>$s->skip_scripts,'environment'=>[]];
        foreach($s->variables as $v)$old['environment'][$v->env_variable]=$v->server_value??$v->default_value;
        $disk=\Illuminate\Support\Facades\Storage::disk('local');$path='simplify/software-backups/'.$s->uuid.'/'.time().'.json';
        if(!$disk->put($path,json_encode($old,JSON_THROW_ON_ERROR)))throw new \RuntimeException('Could not back up startup settings.');
        $startup=app(\App\Services\Servers\StartupModificationService::class)->setUserLevel(\App\Models\User::USER_LEVEL_ADMIN);
        $changed=$startup->handle($s,['egg_id'=>$egg->id,'docker_image'=>$image,'startup'=>array_values($egg->startup_commands)[0],'environment'=>$env,'skip_scripts'=>false]);
        try { app(\App\Services\Servers\ReinstallServerService::class)->handle($changed); }
        catch(\Throwable $e){$startup->handle($changed,$old);throw$e;}
        ServerMetadata::save($s,['modpack'=>ModpackSnapshot::fromState($state,$env),'server_flavour'=>$state['server_type']]);
    }
}
