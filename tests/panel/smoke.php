<?php
// Loads the plugin inside a real Pelican panel and opens its pages as an admin, so an error that only shows when Pelican, Filament or Livewire load the code (such as a repeated #[Renderless]) fails CI instead of answering 500.
// Run from the panel folder after `php artisan p:plugin:install pelican-simplify`: php plugins/pelican-simplify/tests/panel/smoke.php
// Every page opens in its own PHP process, like a real request, so one page cannot leave state (current panel, redirects) behind for the next.
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Symfony\Component\Process\Process;

define('LARAVEL_START',microtime(true));
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
const SMOKE_EMAIL='smoke@example.com';

// The message plus where it came from: the frames in the plugin and in Filament or Livewire, innermost first.
function problem(\Throwable $e):string{
    while($e->getPrevious()&&$e instanceof \Illuminate\View\ViewException)$e=$e->getPrevious();
    $frames=array_filter($e->getTrace(),fn($f)=>preg_match('#pelican-simplify/(src|resources)|vendor/(filament|livewire|charrafimed)/|panel/app/|storage/framework/views/#',$f['file']??''));
    $where=array_map(fn($f)=>preg_replace('#^.*/(plugins|vendor|panel)/#','',$f['file']).':'.$f['line'],array_slice(array_values($frames),0,24));
    return get_class($e).': '.substr($e->getMessage(),0,300).' ('.basename($e->getFile()).':'.$e->getLine().')'.($where?' via '.implode(' < ',$where):'');
}
function result(bool $ok,string $line):never{echo json_encode(['ok'=>$ok,'line'=>$line]),"\n";exit(0);}

// Child: open one page as the admin over HTTP, the way Laravel's feature tests request pages. A redirect fails too: then the page itself was not tested.
if(($argv[1]??'')==='page'){
    [$what,$url]=[$argv[2],$argv[3]];
    try{
        $client=new class($app){use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests,Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication,Illuminate\Foundation\Testing\Concerns\InteractsWithSession;public function __construct(public $app){}};
        $response=$client->actingAs(User::where('email',SMOKE_EMAIL)->firstOrFail())->get($url);
        $status=$response->getStatusCode();
        if($status!==200)result(false,$what.' ('.$url.'): HTTP '.$status.($response->isRedirect()?' to '.$response->headers->get('Location'):'').($response->exception?' '.problem($response->exception):''));
        result(true,$what.' renders (HTTP 200 at '.parse_url($url,PHP_URL_PATH).')');
    }catch(\Throwable $e){result(false,$what.' ('.$url.'): '.problem($e));}
}

// Child: the overview as a Livewire component, with the calls the browser makes.
if(($argv[1]??'')==='livewire'){
    $admin=User::where('email',SMOKE_EMAIL)->firstOrFail();$server=Server::where('owner_id',$admin->id)->firstOrFail();
    try{
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $overview=Livewire::actingAs($admin)->test(\Simplify\Panel\Filament\App\Pages\Dashboard::class);
        // Notifications: a real database notification shows in the full list, and "Mark all read" clears every unread one.
        foreach(range(1,3) as $i)$admin->notifyNow(\Filament\Notifications\Notification::make()->title('Smoke notice '.$i)->toDatabase());
        $overview->call('notificationPage',1);
        $feed=\Simplify\Panel\Services\PanelNotifications::page(1);
        if(!is_array($feed)||!in_array('Smoke notice 3',array_column($feed['items']??[],'title'),true)||($feed['unread']??0)<3)throw new \RuntimeException('the notification list does not show the new notifications');
        $overview->call('markAllNotificationsRead');
        if(\Simplify\Panel\Services\PanelNotifications::unreadCount()!==0)throw new \RuntimeException('mark all read left unread notifications');
        foreach([['listing'],['saveOverviewGroups',[['id'=>'g1','name'=>'Smoke','servers'=>[$server->uuid],'sort'=>'manual']],'port'],['panelNotifications'],['hostHealth'],['announcements'],['dismissAnnouncement','own-0000000000000000'],['restoreAnnouncement','own-0000000000000000'],['pushSettings',null],['savePushPreferences',['updates'=>false]]] as $call){
            $name=array_shift($call);$overview->call($name,...$call);
        }
        // Announcements: the Panel's update check against Pelican's real version service, then one written in the admin page.
        \Simplify\Panel\Services\PanelUpdates::refresh();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        // Opening the form builds every field; callAction() itself needs PHPUnit, so the save goes through the same service.
        Livewire::actingAs($admin)->test(\Simplify\Panel\Filament\Admin\Pages\ManageAnnouncements::class)->set('browserTimezone','Europe/Amsterdam')->call('mountAction','addAnnouncement');
        \Simplify\Panel\Services\Announcements::save(['category'=>'warning','title'=>'Smoke test','text'=>'Hello','audience'=>'everyone','link_type'=>'server','server'=>$server->uuid,'button_label'=>'Open','push'=>true,'pinned'=>true],null,(string)$admin->username);
        $shown=\Simplify\Panel\Services\Announcements::forUser($admin);
        if(!in_array('Smoke test',array_column($shown,'title'),true))throw new \RuntimeException('the new announcement is not shown on the overview');
        if(!array_filter($shown,fn($item)=>$item['title']==='Smoke test'&&$item['pinned']))throw new \RuntimeException('the announcement is not pinned to the notifications');
        // The scheduler's run: refreshes the Panel's updates and sends the new announcement as app notification (nobody subscribed here).
        if(\Illuminate\Support\Facades\Artisan::call('simplify:announcements')!==0)throw new \RuntimeException('simplify:announcements failed');
        if(\Simplify\Panel\Services\Announcements::duePush()!==[])throw new \RuntimeException('the announcement was not marked as sent');
        // Explain this error: the server page renders the button with the saved key, and explaining reads the console from Wings
        // (unreachable here, so it must answer with a plain error instead of failing; nothing is sent to Anthropic).
        Filament::setCurrentPanel(Filament::getPanel('app'));auth()->setUser($admin);
        if(!\Simplify\Panel\Services\ErrorExplainer::settings()['has_key']||!\Simplify\Panel\Services\ErrorExplainer::availableFor($admin))throw new \RuntimeException('the saved Anthropic key is not available');
        $page=Livewire::withQueryParams(['server'=>$server->uuid])->actingAs($admin)->test(\Simplify\Panel\Filament\App\Pages\Dashboard::class);
        if(!str_contains($page->html(),'ps-console-explain'))throw new \RuntimeException('the server page has no Explain button');
        $page->call('explainConsole');
        $explained=\Simplify\Panel\Services\ErrorExplainer::explain($server);
        if(($explained['ok']??true)!==false||!str_contains((string)($explained['error']??''),'Wings'))throw new \RuntimeException('explaining without Wings did not answer with a plain error: '.json_encode($explained));
        result(true,'overview mounts, renders and answers listing, saveOverviewGroups, panelNotifications, hostHealth, announcements and app notification settings; the full notification list shows real notifications and mark all read clears them all; Panel update check runs; the announcement form opens (browser time zone) and a saved, pinned announcement shows and is sent by simplify:announcements; the server page shows Explain and explainConsole answers without Wings');
    }catch(\Throwable $e){result(false,'overview as Livewire component: '.problem($e));}
}

$failures=[];$passed=[];
// 1. Every class of the plugin loads, and every attribute on it can be built (PHP checks repeats and targets only here).
$src=realpath(__DIR__.'/../../src');$classes=0;
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src,FilesystemIterator::SKIP_DOTS)) as $file){
    if($file->getExtension()!=='php')continue;
    $class='Simplify\\Panel\\'.str_replace(['/','.php'],['\\',''],substr($file->getPathname(),strlen($src)+1));
    try{
        if(!class_exists($class)&&!interface_exists($class)&&!trait_exists($class)&&!enum_exists($class)){$failures[]=$class.': not found by the autoloader';continue;}
        $r=new ReflectionClass($class);$classes++;
        $targets=[$r,...$r->getMethods(),...$r->getProperties(),...$r->getReflectionConstants()];
        foreach($r->getMethods() as $m)array_push($targets,...$m->getParameters());
        foreach($targets as $t)foreach($t->getAttributes() as $a)if(class_exists($a->getName()))$a->newInstance();
    }catch(\Throwable $e){$failures[]=$class.': '.problem($e);}
}
$passed[]=$classes.' plugin classes load with their attributes';

// 2. A root admin and one server of their own, as on a fresh panel.
$admin=User::withoutEvents(fn()=>User::factory()->create(['username'=>'smoke-admin','email'=>SMOKE_EMAIL]));
$admin->syncRoles(Role::getRootAdmin());
$server=Server::factory()->create(['owner_id'=>$admin->id,'name'=>'Smoke server']);
// Finish Simplify's setup as the admin does in the setup wizard (until then every page sends the admin to the settings), with every feature on so every page is reachable.
auth()->setUser($admin);\Simplify\Panel\Services\FeatureSettings::save(array_fill_keys(array_keys(\Simplify\Panel\Services\FeatureSettings::definitions()),true),true);
// Explain this error on, with a key that is only stored (encrypted with the panel's APP_KEY) and never sent anywhere.
\Simplify\Panel\Services\ErrorExplainer::save(['key'=>'sk-ant-smoke-test-not-a-real-key','model'=>'claude-haiku-5-5','audience'=>'everyone']);

// 3. Every Simplify page on every panel, each in its own process.
$urls=[];
foreach(Filament::getPanels() as $panel){
    foreach($panel->getPages() as $page){
        if(!str_starts_with($page,'Simplify\\Panel\\'))continue;
        try{$urls[$page.' on '.$panel->getId()]=$panel->getId()==='server'?$page::getUrl(panel:'server',tenant:$server):$page::getUrl(panel:$panel->getId());}
        catch(\Throwable $e){$failures[]='URL of '.$page.' on '.$panel->getId().': '.problem($e);}
    }
}
// The overview of the app panel is the page that broke in beta.7; it must be among them.
if(!array_filter(array_keys($urls),fn($k)=>str_contains($k,'Dashboard on app')))$failures[]='Simplify overview: no Simplify Dashboard page on the app panel';
$children=array_map(fn($what,$url)=>['page',$what,$url],array_keys($urls),$urls);$children[]=['livewire'];
foreach($children as $args){
    $child=new Process([PHP_BINARY,__FILE__,...$args],getcwd(),null,null,300);$child->run();
    $result=json_decode(trim(strrchr("\n".trim($child->getOutput()),"\n")),true);
    if(!is_array($result)){$failures[]=implode(' ',array_slice($args,0,2)).': the check crashed: '.substr(trim($child->getErrorOutput()."\n".$child->getOutput()),-600);continue;}
    if($result['ok'])$passed[]=$result['line'];else $failures[]=$result['line'];
}

foreach($passed as $line)echo "  ok  $line\n";
if($failures){echo "FAIL Pelican panel smoke test:\n  - ".implode("\n  - ",$failures)."\n";exit(1);}
echo "PASS Simplify loads in a real Pelican panel ".config('app.version').": classes and attributes, every page over HTTP, overview Livewire calls, Explain this error\n";
