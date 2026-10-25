<?php
// Prints the Simplify pages on every panel, plus the lab servers, as JSON for the browser test. Run from the panel folder.
use App\Models\Server;use App\Models\User;use Filament\Facades\Filament;
define('LARAVEL_START',microtime(true));require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$admin=User::where('email','lab@example.com')->firstOrFail();auth()->setUser($admin);Filament::setCurrentPanel(Filament::getPanel('app'));
$own=Server::where('owner_id',$admin->id)->orderBy('id')->first();$other=Server::where('owner_id','!=',$admin->id)->orderBy('id')->first();
$pages=[];
foreach(Filament::getPanels() as $panel)foreach($panel->getPages() as $page){
    if(!str_starts_with($page,'Simplify\\Panel\\'))continue;
    $pages[class_basename($page).' ('.$panel->getId().')']=$panel->getId()==='server'?$page::getUrl(panel:'server',tenant:$own):$page::getUrl(panel:$panel->getId());
}
echo json_encode(['version'=>config('app.version'),'pages'=>$pages,'server'=>$own->uuid,'server_name'=>$own->name,'other_server'=>$other?->uuid,'own_count'=>Server::where('owner_id',$admin->id)->count(),'total'=>Server::count()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
