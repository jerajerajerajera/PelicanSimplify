<?php
namespace Illuminate\Http {
    class Request {
        public function __construct(public string $method,public string $route){}
        public function isMethod($method){return $this->method===$method;}
        public function routeIs(...$routes){foreach($routes as $route)if(fnmatch($route,$this->route))return true;return false;}
    }
}
namespace {
    require __DIR__.'/../src/Http/Middleware/CreatorFramePolicy.php';
    $authenticated=true;
    function user(){return $GLOBALS['authenticated']?(object)['id'=>1]:null;}
    class Headers {
        public array $values=['X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"default-src 'self'; frame-ancestors 'none'; script-src 'self' 'unsafe-inline'"];
        public function get($name){return $this->values[$name]??null;}
        public function set($name,$value){$this->values[$name]=$value;}
    }
    $policy=new \Simplify\Panel\Http\Middleware\CreatorFramePolicy;
    $run=function($route,$method='GET')use($policy){
        $response=(object)['headers'=>new Headers];
        $response=$policy->handle(new \Illuminate\Http\Request($method,$route),fn()=>$response);
        // Native security middleware adds DENY only when the header has not been set.
        $response->headers->values['X-Frame-Options']??='DENY';return $response->headers->values;
    };
    $check=function($condition){if(!$condition)throw new \RuntimeException('Frame policy assertion failed');};
    foreach(['filament.app.auth.profile','filament.app.pages.simple-server-creator','filament.app.pages.pelican-simplify-settings','filament.admin.pages.pelican-simplify-settings','filament.app.pages.simplify','filament.app.pages.simplify-widgets','filament.app.pages.create-server','filament.admin.resources.servers.create','filament.admin.resources.servers.edit','filament.admin.resources.servers.view'] as $route){$r=$run($route);$check($r['X-Frame-Options']==='SAMEORIGIN'&&$r['X-Content-Type-Options']==='nosniff');}
    foreach(['filament.server.resources.files.index','filament.server.resources.backups.index','filament.server.pages.settings','filament.server.resources.schedules.index','filament.server.pages.activity-log'] as $route){$r=$run($route);$check($r['X-Frame-Options']==='SAMEORIGIN');$check($r['Content-Security-Policy']==="default-src 'self'; frame-ancestors 'self'; script-src 'self' 'unsafe-inline'");$check($run($route,'POST')['X-Frame-Options']==='DENY');}
    foreach(['filament.admin.resources.eggs.index','filament.admin.resources.users.edit','filament.admin.pages.settings'] as $route)$check($run($route)['X-Frame-Options']==='SAMEORIGIN');
    $check($run('filament.admin.resources.eggs.index','POST')['X-Frame-Options']==='DENY');
    $check($run('filament.app.pages.simple-server-creator','POST')['X-Frame-Options']==='DENY');
    $check($run('filament.app.auth.profile','POST')['X-Frame-Options']==='DENY');
    $check($run('filament.app.auth.login')['X-Frame-Options']==='DENY');
    $authenticated=false;$check($run('filament.admin.auth.login')['X-Frame-Options']==='DENY');$check($run('filament.app.auth.profile')['X-Frame-Options']==='DENY');$check($run('filament.app.pages.simple-server-creator')['X-Frame-Options']==='DENY');
    echo "PASS creator and server frame policy checks: authenticated allowlist, admin tools popups, same-origin framing, native DENY elsewhere and other headers preserved\n";
}
