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
        // Simplify's EULA step replaces Pelican's popup; the first registration of an id wins, so ours goes in first.
        if(class_exists(\App\Extensions\Features\FeatureService::class)&&class_exists(\App\Extensions\Features\Schemas\MinecraftEulaSchema::class)){
            $this->app->extend(\App\Extensions\Features\FeatureService::class,function($features){
                $replaced=new \App\Extensions\Features\FeatureService();$replaced->register(new \Simplify\Panel\Features\MinecraftEula());
                foreach($features->getAll() as $schema)$replaced->register($schema);
                return $replaced;
            });
        }
    }
    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::get('/simplify/assets/{asset}',\Simplify\Panel\Http\Controllers\AssetController::class)->name('simplify.asset');
        \Illuminate\Support\Facades\Route::get('/simplify/background/{id}',\Simplify\Panel\Http\Controllers\BackgroundController::class)->name('simplify.background');
        \Illuminate\Support\Facades\Route::get('/simplify/banner/{id}',\Simplify\Panel\Http\Controllers\BannerController::class)->middleware(['web','auth'])->name('simplify.banner');
        \Illuminate\Support\Facades\Route::get('/simplify/server-icon/{uuid}',\Simplify\Panel\Http\Controllers\ServerIconController::class)->middleware(['web','auth'])->name('simplify.server-icon');
        \Illuminate\Support\Facades\Route::get('/simplify/logo',\Simplify\Panel\Http\Controllers\LogoController::class)->middleware(['web','auth'])->name('simplify.logo');
        \Illuminate\Support\Facades\Route::get('/simplify/favicon.svg',\Simplify\Panel\Http\Controllers\FaviconController::class)->name('simplify.favicon');
        // Emergency page for "Explain this error": plain HTML without Filament or Livewire, for when the panel shows 500.
        // The second address comes from `php artisan simplify:explain --url` and needs no session (it skips the web group).
        \Illuminate\Support\Facades\Route::match(['get','post'],'/simplify/explain',[\Simplify\Panel\Http\Controllers\EmergencyExplainController::class,'session'])->middleware('web')->name('simplify.explain');
        \Illuminate\Support\Facades\Route::match(['get','post'],'/simplify/explain/access/{token}',[\Simplify\Panel\Http\Controllers\EmergencyExplainController::class,'token'])->where('token','[0-9a-f]{40}')->name('simplify.explain.access');
        // Installable web app: manifest, icons and the service worker (served at the root so its scope is the whole panel).
        \Illuminate\Support\Facades\Route::get('/simplify/manifest.webmanifest',[\Simplify\Panel\Http\Controllers\WebAppController::class,'manifest'])->name('simplify.manifest');
        \Illuminate\Support\Facades\Route::get('/simplify/app-icon/{size}.png',[\Simplify\Panel\Http\Controllers\WebAppController::class,'icon'])->whereNumber('size')->name('simplify.app-icon');
        \Illuminate\Support\Facades\Route::get('/simplify-worker.js',[\Simplify\Panel\Http\Controllers\WebAppController::class,'worker'])->name('simplify.worker');
        // Website embed add-on: public, read-only and without a session, so any website can show the server list.
        \Illuminate\Support\Facades\Route::get('/simplify/embed/servers.json',[\Simplify\Panel\Http\Controllers\EmbedController::class,'servers'])->middleware('throttle:120,1')->name('simplify.embed.servers');
        \Illuminate\Support\Facades\Route::get('/simplify/embed.js',[\Simplify\Panel\Http\Controllers\EmbedController::class,'script'])->name('simplify.embed.script');
        \Illuminate\Support\Facades\Route::get('/simplify/embed',[\Simplify\Panel\Http\Controllers\EmbedController::class,'frame'])->middleware('throttle:120,1')->name('simplify.embed');
        // Keep account creation and server linking administrator-only, including
        // direct requests to native pages/APIs outside this dashboard.
        \App\Models\Server::created(function($server):void{
            // Automatic backups start with the configured defaults; a storage error never blocks creation.
            try { \Simplify\Panel\Services\AutomaticBackups::initialize($server); } catch (\Throwable $e) { report($e); }
        });
        \App\Models\Server::deleted(function($server):void{
            // Removed after the response; simplify:subdomains retries when Cloudflare is unavailable.
            $uuid=(string)$server->uuid;
            \Illuminate\Support\Facades\Storage::disk('local')->delete('simplify/install/'.$uuid.'.json');
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
            Schedule::command('simplify:announcements')->everyMinute()->withoutOverlapping(10);
        });
    }
}
