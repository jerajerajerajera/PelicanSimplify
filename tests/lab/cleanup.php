<?php
// Removes what the smoke test left behind (its own node, user and server with a random public IP) so only the lab node remains.
use App\Models\Node;use App\Models\Server;use App\Models\User;
define('LARAVEL_START',microtime(true));require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$lab=Node::where('name','Lab node')->firstOrFail();
foreach(Server::where('node_id','!=',$lab->id)->get() as $s){$s->allocations()->update(['server_id'=>null]);$s->forceDelete();}
Node::where('id','!=',$lab->id)->delete();
User::whereNotIn('email',['lab@example.com','friend@example.com'])->delete();
if(class_exists('Simplify\Panel\Services\Announcements')){foreach(Simplify\Panel\Services\Announcements::all() as $a)if((str_starts_with($a['title']??'','Lab announcement')||str_starts_with($a['title']??'','Smoke')))Simplify\Panel\Services\Announcements::delete($a['id']);}
echo "nodes ".Node::count().", servers ".Server::count().", users ".User::count()."\n";
