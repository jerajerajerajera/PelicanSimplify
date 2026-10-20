<?php
declare(strict_types=1);
namespace Simplify\Panel;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Actions\Action;
use Simplify\Panel\Filament\App\Pages\Dashboard;
use Simplify\Panel\Filament\Admin\Pages\SimplifySettings;
use Simplify\Panel\Filament\Admin\Pages\CreateMinecraftServer;
use Simplify\Panel\Filament\App\Pages\CreateServer;
use Simplify\Panel\Services\FeatureSettings;
final class PelicanSimplifyPlugin implements Plugin
{
    public function getId(): string { return 'pelican-simplify'; }
    public function register(Panel $panel): void
    {
        if(!in_array($panel->getId(),['app','admin','server'],true))return;
        if(class_exists(Filament\Profile\SteamProfileTab::class))Filament\Profile\SteamProfileTab::register();
        $panel->middleware([Http\Middleware\Setup::class,Http\Middleware\CreatorFramePolicy::class]);
        $panel->renderHook('panels::head.end',fn()=>view('pelican-simplify::shared-assets'));
        $panel->renderHook('panels::global-search.after',fn()=>view('pelican-simplify::legacy-return'));
        $panel->userMenuItems([
            Action::make('simplify_settings')->label('Simplify settings')->icon('tabler-settings')
                ->url(fn()=>SimplifySettings::getUrl(panel:$panel->getId()==='server'?'app':$panel->getId()))
                ->visible(fn()=>(bool)user()?->isRootAdmin()),
        ]);
        if($panel->getId()==='app'){
            $panel->pages([Dashboard::class,CreateServer::class,SimplifySettings::class]);
            $panel->middleware([Http\Middleware\Home::class]);
            $panel->homeUrl(fn()=>FeatureSettings::enabled('dashboard')?Dashboard::getUrl(panel:'app'):route(\Simplify\Panel\Services\LegacyNavigation::serverListRoute()));
        }
        if($panel->getId()==='admin'){
            $panel->topbar(true);
            $panel->middleware([Http\Middleware\PluginsHome::class]);
            $panel->pages([SimplifySettings::class,CreateMinecraftServer::class,\Simplify\Panel\Filament\Admin\Pages\ManagePlugins::class,\Simplify\Panel\Filament\Admin\Pages\ManagePeople::class]);
        }
        if($panel->getId()==='server')$panel->middleware([Http\Middleware\ServerHome::class]);
    }
    public function boot(Panel $panel): void { app('view')->addNamespace($this->getId(),plugin_path($this->getId(),'resources/views')); }
}
