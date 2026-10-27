<?php
// Puts a lab panel in a state the 0.6.0 walk needs, and back. Run from the panel folder.
//   php state.php installing <uuid>   the server shows the installation view (first install, so Kill is offered)
//   php state.php installed <uuid>    back to a normal installed server
//   php state.php explain-key on|off  a (fake) Claude key for Explain this error, or none
//   php state.php free-ports <n>      exactly n unassigned ports on the lab node (the Server Maker needs one)
//   php state.php embed <uuids|none> the Website embed on, with only these servers shown (prints their short ids)
//   php state.php notify <n>          n panel notifications and one announcement for the lab admin; 0 removes them
//   php state.php egg-host <ids> <v>  publishes version <v> of these eggs on the lab egg host (https://127.0.0.1:8443) and points
//                                     their update address there; version 0 is the egg as it is now
//   php state.php egg-run             the scheduler's egg update check, forced
//   php state.php egg-tick            the scheduler's minute for a running egg update test
//   php state.php egg-test <status>   the test server's install finished (installed) or failed (install_failed)
//   php state.php egg-info <ids>      JSON: state, the eggs' image and startup, test servers, copies, test ports, listing
//   php state.php egg-mode <mode>     ask, auto, test or off
//   php state.php egg-reset           no egg update state, kept versions or update addresses; room on the lab node for a test server
//   php state.php scheduler stale|fresh  the scheduler's heartbeats ten minutes old, or now
//   php state.php mode full|addons    Full Simplify or Only the add-ons, as the settings page saves it
use App\Models\Server;use App\Models\User;use Simplify\Panel\Services\Announcements;use Simplify\Panel\Services\ErrorExplainer;
define('LARAVEL_START',microtime(true));require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$admin=User::where('email','lab@example.com')->firstOrFail();auth()->setUser($admin);
[$what,$arg]=[$argv[1]??'',$argv[2]??''];
switch($what){
    case 'installing': Server::where('uuid',$arg)->firstOrFail()->forceFill(['status'=>'installing','installed_at'=>null])->save(); break;
    case 'installed': Server::where('uuid',$arg)->firstOrFail()->forceFill(['status'=>null,'installed_at'=>now()])->save(); break;
    case 'explain-key': ErrorExplainer::save(['provider'=>'anthropic','key'=>$arg==='on'?'sk-ant-lab-not-a-real-key':'','audience'=>'everyone'],$arg!=='on'); break;
    case 'notify':
        // A clean slate: no panel notifications, nothing read or archived, and one lab announcement (the pin button).
        $admin->notifications()->delete();
        Illuminate\Support\Facades\Storage::disk('local')->delete(Announcements::READ_DIR.$admin->id.'.json');
        foreach(Announcements::all() as $row)if($row['title']==='Lab 0.6.0 announcement')Announcements::delete($row['id']);
        if((int)$arg>0)Announcements::save(['title'=>'Lab 0.6.0 announcement','text'=>'Maintenance tonight at 22:00.'],null,'lab-admin');
        // Written straight to the table: sendToDatabase queues a job, and the lab runs no queue worker.
        for($i=1;$i<=(int)$arg;$i++)$admin->notifications()->create(['id'=>(string)Illuminate\Support\Str::uuid(),'type'=>Filament\Notifications\DatabaseNotification::class,
            'data'=>\Filament\Notifications\Notification::make()->title("Lab notification $i")->body($i===1?'Backup of Survival finished.':'Server Creative world restarted after a crash.')->success()->getDatabaseMessage(),'created_at'=>now()->subMinutes($i)]);
        break;
    case 'free-ports':
        // Free ports on the lab node for the Server Maker (seed.php gives every port to a server).
        $node=App\Models\Node::where('name','Lab node')->firstOrFail();
        App\Models\Allocation::where('node_id',$node->id)->whereNull('server_id')->delete();
        for($port=25600;$port<25600+(int)$arg;$port++)App\Models\Allocation::create(['node_id'=>$node->id,'ip'=>'127.0.0.1','port'=>$port]);
        break;
    case 'embed':
        // Website embed: the add-on on, and only these servers (comma-separated uuids, or "none") on the public list.
        $features=Simplify\Panel\Services\FeatureSettings::all();$features['website_embed']=true;Simplify\Panel\Services\FeatureSettings::save($features,true);
        $show=$arg==='none'?[]:explode(',',$arg);
        foreach(Server::all() as $server)Simplify\Panel\Services\WebsiteEmbed::setVisible($server,in_array($server->uuid,$show,true));
        echo implode(',',Server::whereIn('uuid',$show)->pluck('uuid_short')->all()),"\n";
        break;
    case 'egg-host':
        // A newer egg: another Docker image and startup command, so Move servers has something to move.
        foreach(explode(',',$arg) as $id){
            $egg=App\Models\Egg::findOrFail((int)$id);$url='https://127.0.0.1:8443/egg-'.$egg->id.'.json';
            $egg->forceFill(['update_url'=>$url])->save();
            $d=json_decode(app(App\Services\Eggs\Sharing\EggExporterService::class)->handle($egg->id,App\Enums\EggFormat::JSON),true);
            $v=(int)($argv[3]??1);
            if($v>0){$d['description']='Lab update '.$v;$d['docker_images']=['Java 25'=>'ghcr.io/pelican-eggs/yolks:java_25'];$d['startup_commands']=['Default'=>'java -Xms128M -jar server.jar --lab '.$v];}
            $d['meta']['update_url']=$url;
            Illuminate\Support\Facades\File::put('/home/user/lab/egg-host/www/egg-'.$egg->id.'.json',json_encode($d,JSON_PRETTY_PRINT));
        }
        break;
    case 'egg-run': print_r(Simplify\Panel\Services\EggUpdates::run(true)); break;
    case 'egg-tick': print_r(Simplify\Panel\Services\EggUpdateTest::tick()); break;
    case 'egg-test':
        $test=Simplify\Panel\Services\EggUpdates::state()['test']??null;if(!$test){echo "no test running\n";exit(1);}
        Server::findOrFail($test['server'])->forceFill(['status'=>$arg==='installed'?null:$arg,'installed_at'=>$arg==='installed'?now():null])->save(); break;
    case 'egg-mode': Simplify\Panel\Services\EggUpdates::save(['mode'=>$arg]); break;
    case 'egg-info':
        $tests=Server::where('external_id','like',Simplify\Panel\Services\EggUpdateTest::MARK.'%')->get();
        echo json_encode(['state'=>Simplify\Panel\Services\EggUpdates::state(),'mode'=>Simplify\Panel\Services\EggUpdates::settings()['mode'],
            'eggs'=>App\Models\Egg::whereIn('id',explode(',',$arg))->get()->map(fn($e)=>['id'=>$e->id,'name'=>$e->name,'description'=>$e->description,'images'=>$e->docker_images,'startup'=>$e->startup_commands,'backups'=>count(Simplify\Panel\Services\EggUpdates::backups($e))]),
            'servers'=>Server::whereIn('egg_id',explode(',',$arg))->get(['id','egg_id','image','startup']),
            'test_servers'=>$tests->map(fn($s)=>['id'=>$s->id,'uuid'=>$s->uuid,'name'=>$s->name,'status'=>$s->status,'port'=>$s->allocation?->port]),
            'copies'=>App\Models\Egg::where('name','like','%(update check)')->pluck('name'),
            'test_ports'=>App\Models\Allocation::whereBetween('port',[45000,45999])->get(['port','server_id']),
            'hidden_from_query'=>Simplify\Panel\Services\EggUpdateTest::hide(Server::query())->count().' of '.Server::count(),
            'notifications'=>$admin->notifications()->latest()->take(4)->get()->map(fn($n)=>($n->data['title']??'').' | '.mb_strimwidth((string)($n->data['body']??''),0,220)),
        ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n"; exit(0);
    case 'egg-reset':
        foreach(Server::where('external_id','like',Simplify\Panel\Services\EggUpdateTest::MARK.'%')->get() as $s)app(App\Services\Servers\ServerDeletionService::class)->withForce()->handle($s);
        App\Models\Egg::where('name','like','%(update check)')->get()->each->delete();
        App\Models\Allocation::whereBetween('port',[45000,45999])->whereNull('server_id')->get()->each->delete();
        foreach([Simplify\Panel\Services\EggUpdates::STATE,Simplify\Panel\Services\EggUpdates::SETTINGS]as$f)Illuminate\Support\Facades\Storage::disk('local')->delete($f);
        Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('simplify/egg-backups');Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('simplify/egg-tests');
        App\Models\Egg::query()->update(['update_url'=>null]);
        App\Models\Node::where('name','Lab node')->update(['memory'=>262144,'disk'=>2097152,'memory_overallocate'=>-1,'disk_overallocate'=>-1,'cpu_overallocate'=>-1]);
        break;
    case 'scheduler':
        foreach(['simplify.announcements-at','simplify.plugin-check-at','simplify.panel-updates-at']as$k)Illuminate\Support\Facades\Cache::forever($k,$arg==='stale'?time()-600:time());
        break;
    case 'mode':
        $features=Simplify\Panel\Services\FeatureSettings::all();$features['addons_only']=$arg==='addons';Simplify\Panel\Services\FeatureSettings::save($features,true); break;
    default: echo "unknown state $what\n"; exit(1);
}
echo "$what $arg\n";
