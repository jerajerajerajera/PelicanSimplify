<?php
// Fills a lab panel: an admin, a second owner, one node that points at the fake Wings, Minecraft and other-game
// eggs and twelve servers; turns every Simplify feature on. Run from the panel folder: php seed.php <wings-port> > servers.json
use App\Models\Allocation;use App\Models\Egg;use App\Models\Node;use App\Models\Role;use App\Models\Server;use App\Models\User;
define('LARAVEL_START',microtime(true));
require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$port=(int)($argv[1]??8090);
$admin=User::withoutEvents(fn()=>User::factory()->create(['username'=>'lab-admin','email'=>'lab@example.com','password'=>bcrypt('LabPassword123!')]));
$admin->syncRoles(Role::getRootAdmin());
$friend=User::withoutEvents(fn()=>User::factory()->create(['username'=>'friend','email'=>'friend@example.com','password'=>bcrypt('LabPassword123!')]));
$node=Node::factory()->create(['name'=>'Lab node','fqdn'=>'127.0.0.1','scheme'=>'http','daemon_listen'=>$port,'daemon_connect'=>$port,'memory'=>16384,'disk'=>500000,'cpu'=>400]);
$eggs=['Paper'=>'ghcr.io/pelican-eggs/yolks:java_21','Vanilla Minecraft'=>'ghcr.io/pelican-eggs/yolks:java_21','Forge Minecraft'=>'ghcr.io/pelican-eggs/yolks:java_17','Rust'=>'ghcr.io/pelican-eggs/games:rust'];
$eggModels=[];foreach($eggs as $name=>$image)$eggModels[$name]=Egg::factory()->create(['name'=>$name,'docker_images'=>[$image]]);
$names=['Survival','Creative world','Skyblock','Modpack Monday','Hardcore','Minigames','Friends only','Rust weekend','Test server','Build team','Vanilla classic','Rust outpost'];
$list=[];
foreach($names as $i=>$name){
    $eggName=array_keys($eggs)[$i%4];
    $allocation=Allocation::factory()->create(['node_id'=>$node->id,'ip'=>'127.0.0.1','port'=>25565+$i]);
    $server=Server::factory()->create(['name'=>$name,'owner_id'=>$i%3===2?$friend->id:$admin->id,'node_id'=>$node->id,'allocation_id'=>$allocation->id,'egg_id'=>$eggModels[$eggName]->id,
        'image'=>$eggs[$eggName],'memory'=>2048,'disk'=>10240,'cpu'=>200,'installed_at'=>now(),'status'=>null]);
    $allocation->update(['server_id'=>$server->id]);
    $list[]=['uuid'=>$server->uuid,'name'=>$name];
}
echo json_encode($list),"\n";
auth()->setUser($admin);
// Every Simplify feature on, as the full panel ("Only the add-ons" off).
\Simplify\Panel\Services\FeatureSettings::save([...array_fill_keys(array_keys(\Simplify\Panel\Services\FeatureSettings::definitions()),true),'addons_only'=>false],true);
fprintf(STDERR,"seeded %d servers on node %d (wings :%d)\n",count($list),$node->id,$port);
