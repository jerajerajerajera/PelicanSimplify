<?php
// Times the overview's listing() as the admin, with an empty cache each round, and counts the Wings requests it made.
// Run from the panel folder: php bench.php <wings-log> [rounds]
use App\Models\User;use Filament\Facades\Filament;use Livewire\Livewire;
define('LARAVEL_START',microtime(true));require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$log=$argv[1];$rounds=(int)($argv[2]??3);$http=[];\Illuminate\Support\Facades\Http::globalRequestMiddleware(function($r)use(&$http){$http[]=(string)$r->getUri();return $r;});
$admin=User::where('email','lab@example.com')->firstOrFail();Filament::setCurrentPanel(Filament::getPanel('app'));
$lines=fn()=>is_file($log)?count(file($log)):0;
$results=[];
for($i=0;$i<$rounds;$i++){
    cache()->flush();$before=$lines();$t=microtime(true);
    $overview=Livewire::actingAs($admin)->test(\Simplify\Panel\Filament\App\Pages\Dashboard::class);
    $mount=microtime(true)-$t;$t=microtime(true);
    $overview->call('listing');
    $results[]=['mount_ms'=>round($mount*1000),'listing_ms'=>round((microtime(true)-$t)*1000),'wings_requests'=>$lines()-$before];
}
// A warm second call within the cache lifetime, as the browser's 30-second refresh sees it.
$before=$lines();$t=microtime(true);$overview->call('listing');$warm=['listing_ms'=>round((microtime(true)-$t)*1000),'wings_requests'=>$lines()-$before];
echo json_encode(['version'=>config('app.version'),'php'=>PHP_VERSION,'cold'=>$results,'warm'=>$warm,'outbound_http'=>array_count_values(array_map(fn($u)=>preg_replace('~^(https?://[^/]+/[^?]*).*~','$1',$u),$http))]),"\n";
