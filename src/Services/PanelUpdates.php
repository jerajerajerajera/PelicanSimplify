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
        $list=Cache::get(self::CACHE);
        // Before the first scheduled check (or without Pelican's cron) Simplify's own update still shows.
        if(!is_array($list))$list=self::seen(self::simplify());
        // Gone as soon as that Simplify version runs, also when Pelican's own updater installed it.
        $running=(string)BuildInfo::version();
        $list=array_filter($list,fn(array $item):bool=>!isset($item['version'])||$running===''||version_compare($running,$item['version'],'<'));
        return array_values(array_map(fn(array $item):array=>self::present($item,$now),$list));
    }

    /** Drops the list after an update; the next scheduled check fills it again. */
    public static function forget(): void { Cache::forget(self::CACHE); }

    /** Checks again and keeps the result until a little after the next scheduled check. */
    public static function refresh(): array
    {
        $list=self::collect();
        Cache::put(self::CACHE,$list,now()->addMinutes(30));
        return $list;
    }

    public static function present(array $item,int $now): array
    {
        $at=(int)($item['seen']??$now);
        return ['key'=>$item['key'],'source'=>'panel','category'=>'info','tone'=>'info','title'=>$item['title'],'text'=>$item['text'],'from'=>'Panel','audience'=>'admins',
            'at'=>$at,'time'=>Announcements::ago($at,$now),'until'=>null,'action'=>$item['action']??null];
    }

    /** @return list<array{key: string, title: string, text: string, action: ?array, seen: int}> */
    public static function collect(): array
    {
        $items=self::simplify();
        try{
            foreach(PluginUpdater::available() as $plugin){
                if($plugin->id==='pelican-simplify'||!($latest=PluginUpdater::latestVersion($plugin)))continue;
                $items[]=['key'=>'panel-plugin-'.self::slug((string)$plugin->id).'-'.self::slug($latest),'title'=>$plugin->name.' '.$latest.' is available',
                    'text'=>'You run '.$plugin->version.'. Update it in the plugin manager.','action'=>self::pluginsLink()];
            }
        }catch(\Throwable){}
        $versions=self::versionService();
        if($versions){
            $wings=self::version(fn()=>$versions->latestWingsVersion());
            if($wings)foreach(self::nodes() as $node){
                $current=self::version(fn()=>self::nodeVersion($node));
                if(!$current||!version_compare($wings,$current,'>'))continue;
                $name=(string)($node->name??'Node '.$node->id);
                $items[]=['key'=>'panel-wings-'.(int)$node->id.'-'.self::slug($wings),'title'=>'Wings '.$wings.' is available for '.$name,
                    'text'=>$name.' runs Wings '.$current.'. Update Wings on that machine when it suits you.','action'=>['label'=>'How to update','url'=>'https://pelican.dev/docs/wings/update']];
            }
            $panel=self::version(fn()=>$versions->latestPanelVersion());$current=self::version(fn()=>PluginUpdater::panelVersion());
            if($panel&&$current&&version_compare($panel,$current,'>'))$items[]=['key'=>'panel-pelican-'.self::slug($panel),'title'=>'Pelican '.$panel.' is available',
                'text'=>'You run '.$current.'. Read the release notes before you update.','action'=>['label'=>'Release notes','url'=>'https://github.com/pelican-dev/panel/releases']];
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
