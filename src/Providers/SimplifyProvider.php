<?php
namespace Simplify\Panel\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schedule;
final class SimplifyProvider extends ServiceProvider
{
    public function register(): void
    {
        $path=plugin_path('pelican-simplify','config/compatibility.json');
        $legacy=is_file($path)?json_decode(file_get_contents($path),true):[];
        foreach(is_array($legacy)?$legacy:[] as $namespace){
            if(!is_string($namespace)||!preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/',$namespace))continue;
            $old=$namespace.'\\Jobs\\ApplyServerProperties';
            if(!class_exists($old))class_alias(\Simplify\Panel\Jobs\ApplyServerProperties::class,$old);
        }
        $this->app->scoped(\Simplify\Panel\Services\FeatureSettings::class);
        $this->app->scoped(\Simplify\Panel\Services\AppearanceSettings::class);
        $this->app->scoped(\Simplify\Panel\Services\Extensions::class);
        $this->app->scoped(\Simplify\Panel\Services\CreatorAccess::class);
        $this->app->scoped(\Simplify\Panel\Services\PerformanceSettings::class);
    }
    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::get('/simplify/assets/{asset}',\Simplify\Panel\Http\Controllers\AssetController::class)->name('simplify.asset');
        \Illuminate\Support\Facades\Route::get('/simplify/background/{id}',\Simplify\Panel\Http\Controllers\BackgroundController::class)->name('simplify.background');
        \Illuminate\Support\Facades\Route::get('/simplify/banner/{id}',\Simplify\Panel\Http\Controllers\BannerController::class)->middleware(['web','auth'])->name('simplify.banner');
        \Illuminate\Support\Facades\Route::get('/simplify/server-icon/{uuid}',\Simplify\Panel\Http\Controllers\ServerIconController::class)->middleware(['web','auth'])->name('simplify.server-icon');
        \Illuminate\Support\Facades\Route::get('/simplify/logo',\Simplify\Panel\Http\Controllers\LogoController::class)->middleware(['web','auth'])->name('simplify.logo');
        \Illuminate\Support\Facades\Route::get('/simplify/favicon.svg',\Simplify\Panel\Http\Controllers\FaviconController::class)->name('simplify.favicon');
        // Keep account creation and server linking administrator-only, including
        // direct requests to native pages/APIs outside this dashboard.
        \App\Models\Server::created(function($server):void{
            // Automatic backups start with the configured defaults; a storage error never blocks creation.
            try { \Simplify\Panel\Services\AutomaticBackups::initialize($server); } catch (\Throwable $e) { report($e); }
        });
        \App\Models\Server::deleted(function($server):void{
            // Removed after the response; simplify:subdomains retries when Cloudflare is unavailable.
            $uuid=(string)$server->uuid;
            if(\Simplify\Panel\Services\FeatureSettings::enabled('subdomains'))app()->terminating(function()use($uuid):void{try{\Simplify\Panel\Services\Subdomains::forget($uuid);}catch(\Throwable $e){report($e);}});
        });
        \App\Models\User::creating(function():void {
            if(\Simplify\Panel\Services\FeatureSettings::enabled('restrict_access')) abort_unless(user()?->isAdmin(),403,'Only an administrator can create accounts.');
        });
        foreach(['creating','updating','deleting'] as $event) {
            \App\Models\Subuser::$event(function():void {
                if(\Simplify\Panel\Services\FeatureSettings::enabled('restrict_access')) abort_unless(user()?->isAdmin(),403,'Only an administrator can change server access.');
            });
        }
        foreach(['creating','updating','deleting'] as $event) {
            \App\Models\Schedule::$event(function():void {
                if(user() && \Simplify\Panel\Services\FeatureSettings::enabled('restrict_access')) abort_unless(user()->isAdmin(),403,'Only an administrator can manage additional scheduled tasks.');
            });
        }
        \Illuminate\Support\Facades\Gate::before(function(\App\Models\User $user,string $ability,array $arguments):?bool{
            if(\Simplify\Panel\Services\FeatureSettings::enabled('easy_creator')&&$ability==='create'&&($arguments[0]??null)===\App\Models\Server::class&&!$user->isAdmin())return false;
            return null;
        });
        $this->app->booted(function():void {
            Schedule::command('simplify:maintenance')->everyMinute()->withoutOverlapping(5);
            Schedule::command('simplify:plugin-updates')->everyMinute()->withoutOverlapping(60);
            Schedule::command('simplify:subdomains')->everyFiveMinutes()->withoutOverlapping(10);
        });
    }
}
