<?php
// Puts a lab panel in a state the 0.6.0 walk needs, and back. Run from the panel folder.
//   php state.php installing <uuid>   the server shows the installation view (first install, so Kill is offered)
//   php state.php installed <uuid>    back to a normal installed server
//   php state.php explain-key on|off  a (fake) Claude key for Explain this error, or none
//   php state.php free-ports <n>      exactly n unassigned ports on the lab node (the Server Maker needs one)
//   php state.php embed <uuids|none> the Website embed on, with only these servers shown (prints their short ids)
//   php state.php notify <n>          n panel notifications and one announcement for the lab admin; 0 removes them
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
    default: fwrite(STDERR,"unknown state $what\n"); exit(1);
}
echo "$what $arg\n";
