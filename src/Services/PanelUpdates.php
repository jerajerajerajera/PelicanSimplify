<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Cache;
/**
 * Announcements from the Panel itself, for root admins: a new version of Simplify, of another plugin, of Wings on a
 * node or of Pelican. Nobody writes them; each one disappears once that update is installed. The scheduled command
 * simplify:announcements checks every ten minutes (with Pelican's own version service for the latest Wings and Pelican
 * releases), so a page load never waits for a slow node or update server.
 */
final class PanelUpdates
{
    private const CACHE='simplify.panel-updates';
    private const SEMVER='/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/';

    /** Dashboard shape (see Announcements::present). */
    public static function announcements(?int $now=null): array
    {
        $now??=time();
        $list=Cache::get(self::key());
        // Before the first check with this Simplify version (just installed, or without Pelican's cron) Simplify's own
        // update still shows, and the check runs once after this response, so the next page has the full list.
        if(!is_array($list)){
            $list=self::seen(self::simplify());
            self::refreshAfterResponse();
        }
        // Gone as soon as that Simplify version runs, also when Pelican's own updater installed it.
        $running=(string)BuildInfo::version();
        $list=array_filter($list,fn(array $item):bool=>!isset($item['version'])||$running===''||PluginUpdater::isNewer($item['version'],$running));
        return array_values(array_map(fn(array $item):array=>self::present($item,$now),$list));
    }

    /** Drops the list after an update; the next scheduled check fills it again. */
    public static function forget(): void { Cache::forget(self::key()); }

    /** Whether this Simplify version has checked yet (an update of Simplify starts with an empty list). */
    public static function checked(): bool { return is_array(Cache::get(self::key())); }

    /** The list is kept per Simplify version, so a new version never shows a list made by an older one (without What's new). */
    private static function key(): string
    {
        try{return self::CACHE.'.'.(string)BuildInfo::version();}catch(\Throwable){return self::CACHE;}
    }

    private static function refreshAfterResponse(): void
    {
        if(!Cache::add(self::CACHE.'.pending',true,now()->addMinutes(10)))return;
        try{app()->terminating(function():void{try{self::refresh();}catch(\Throwable $e){report($e);}});}catch(\Throwable){}
    }

    /** Checks again and keeps the result until a little after the next scheduled check. */
    public static function refresh(): array
    {
        $list=self::collect();
        Cache::put(self::key(),$list,now()->addMinutes(30));
        return $list;
    }

    public static function present(array $item,int $now): array
    {
        $at=(int)($item['seen']??$now);
        return ['key'=>$item['key'],'source'=>'panel','category'=>'info','tone'=>'info','title'=>$item['title'],'text'=>$item['text'],'from'=>'Panel','audience'=>'admins',
            'at'=>$at,'time'=>Announcements::ago($at,$now),'until'=>null,'action'=>$item['action']??null,
            'notes'=>is_array($item['notes']??null)?array_values($item['notes']):[],'notes_url'=>is_string($item['notes_url']??null)?$item['notes_url']:null];
    }

    /** @return list<array{key: string, title: string, text: string, action: ?array, seen: int}> */
    public static function collect(): array
    {
        $items=array_map(fn(array $item):array=>self::withNotes($item,self::simplifyNotes($item['version'])),self::simplify());
        try{
            foreach(PluginUpdater::available() as $plugin){
                if($plugin->id==='pelican-simplify'||!($latest=PluginUpdater::latestVersion($plugin)))continue;
                $items[]=self::withNotes(['key'=>'panel-plugin-'.self::slug((string)$plugin->id).'-'.self::slug($latest),'title'=>$plugin->name.' '.$latest.' is available',
                    'text'=>'You run '.$plugin->version.'. Update it in the plugin manager.','action'=>self::pluginsLink()],self::notesFor($plugin,$latest));
            }
        }catch(\Throwable){}
        $versions=self::versionService();
        if($versions){
            $wings=self::version(fn()=>$versions->latestWingsVersion());
            $wingsNotes=$wings?ReleaseNotes::release('pelican-dev/wings',$wings):null;
            if($wings)foreach(self::nodes() as $node){
                $current=self::version(fn()=>self::nodeVersion($node));
                if(!$current||!version_compare($wings,$current,'>'))continue;
                $name=(string)($node->name??'Node '.$node->id);
                $items[]=self::withNotes(['key'=>'panel-wings-'.(int)$node->id.'-'.self::slug($wings),'title'=>'Wings '.$wings.' is available for '.$name,
                    'text'=>$name.' runs Wings '.$current.'. Update Wings on that machine when it suits you.','action'=>['label'=>'How to update','url'=>'https://pelican.dev/docs/wings/update']],$wingsNotes);
            }
            $panel=self::version(fn()=>$versions->latestPanelVersion());$current=self::version(fn()=>PluginUpdater::panelVersion());
            if($panel&&$current&&version_compare($panel,$current,'>'))$items[]=self::withNotes(['key'=>'panel-pelican-'.self::slug($panel),'title'=>'Pelican '.$panel.' is available',
                'text'=>'You run '.$current.'. Read the release notes before you update.','action'=>['label'=>'Release notes','url'=>'https://github.com/pelican-dev/panel/releases']],ReleaseNotes::release('pelican-dev/panel',$panel));
        }
        return self::seen($items);
    }

    /** A newer Simplify for the channel this panel follows (UpdateCheck keeps the answer ten minutes). */
    private static function simplify(): array
    {
        try{
            $status=UpdateCheck::status();
            if(!$status||!$status['available'])return [];
            return [['key'=>'panel-simplify-'.self::slug($status['version']),'version'=>$status['version'],'title'=>'Simplify '.$status['version'].' is available',
                'text'=>'You run '.$status['current'].'. '.(FeatureSettings::enabled('auto_update_plugins')?'It is installed automatically with a backup.':'Update it with one click in the plugin manager.'),
                'action'=>$status['url']?['label'=>'View update','url'=>$status['url']]:null]];
        }catch(\Throwable){return [];}
    }

    /** Adds "What's new" (a few lines and a link to the full notes) when they could be read. */
    /** What's new of a version of a plugin (Simplify included), or null. @return array{lines: list<string>, url: ?string}|null */
    public static function notesFor(object $plugin,string $version): ?array
    {
        if(($plugin->id??null)==='pelican-simplify')return self::simplifyNotes($version);
        return ReleaseNotes::forPlugin($plugin->update_url??null,$plugin->url??null,$version);
    }

    private static function simplifyNotes(string $version): ?array
    {
        $branch=self::branch($version);
        return ReleaseNotes::changelog(PluginUpdater::REPOSITORY.$branch.'/update.json',$version,'https://github.com/jerajerajerajera/PelicanSimplify/blob/'.$branch.'/CHANGELOG.md');
    }

    private static function withNotes(array $item,?array $notes): array
    {
        if(!$notes||!$notes['lines'])return $item;
        return [...$item,'notes'=>$notes['lines'],'notes_url'=>$notes['url']];
    }

    /** Simplify's branch for a version: snapshots and betas have their own. */
    private static function branch(string $version): string
    {
        return str_contains($version,'snapshot')?'snapshot':(str_contains($version,'beta')?'beta':'main');
    }

    /** Adds when each update was first seen, so the bell can say "2 hours ago". */
    private static function seen(array $items): array
    {
        foreach($items as &$item){
            Cache::add('simplify.panel-update-seen.'.$item['key'],time(),now()->addDays(90));
            $item['seen']=(int)(Cache::get('simplify.panel-update-seen.'.$item['key'])??time());
        }
        unset($item);
        return $items;
    }

    /** A clean x.y.z[-pre] version, or null for "develop", errors and anything else. */
    public static function version(\Closure $read): ?string
    {
        try{$value=$read();}catch(\Throwable){return null;}
        $value=is_scalar($value)?ltrim(trim((string)$value),'vV'):'';
        return preg_match(self::SEMVER,$value)?$value:null;
    }

    private static function versionService(): ?object
    {
        $class='App\\Services\\Helpers\\SoftwareVersionService';
        if(!class_exists($class))return null;
        try{$service=app($class);}catch(\Throwable){return null;}
        return method_exists($service,'latestWingsVersion')&&method_exists($service,'latestPanelVersion')?$service:null;
    }

    private static function nodes(): iterable
    {
        try{return \App\Models\Node::query()->get();}catch(\Throwable){return [];}
    }

    /** The Wings version a node reports (Pelican caches it), null when the node is unreachable. */
    private static function nodeVersion(object $node): ?string
    {
        if(!method_exists($node,'systemInformation'))return null;
        $info=$node->systemInformation();
        return is_array($info)&&is_string($info['version']??null)?$info['version']:null;
    }

    private static function pluginsLink(): ?array
    {
        try{
            $url=FeatureSettings::enabled('plugin_manager')?\Simplify\Panel\Filament\Admin\Pages\ManagePlugins::getUrl(panel:'admin'):\App\Filament\Admin\Resources\Plugins\PluginResource::getUrl('index',panel:'admin');
            return ['label'=>'Open plugins','url'=>$url];
        }catch(\Throwable){return null;}
    }

    private static function slug(string $value): string { return substr((string)preg_replace('/[^A-Za-z0-9._-]/','-',$value),0,60); }
}
