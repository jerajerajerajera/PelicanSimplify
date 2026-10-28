<?php
declare(strict_types=1);
namespace Filament\Pages {class Page {public ?string $redirectUrl=null;public static function getUrl($panel=null){return '/simplify';}public function redirect($url){$this->redirectUrl=$url;}}}
namespace Livewire {trait WithFileUploads {}}
namespace App\Models {class Server {public string $name='Example';}}
namespace App\Services\Servers {class ServerDeletionService {public static bool $fail=false;public function handle($s){if(self::$fail)throw new \RuntimeException('Deletion failed');\Simplify\Panel\Services\Access::$deleted=true;}}}
namespace Simplify\Panel\Services {class Access {public static bool $deleted=false;public static bool $owner=true;public static bool $offline=true;public static function server($uuid){if(self::$deleted)throw new \RuntimeException('404');return new \App\Models\Server;}public static function feature($key){return true;}public static function owner($s){\abort_unless(self::$owner,403);}public static function offline($s){\abort_unless(self::$offline,409);}}}
namespace {
require_once __DIR__.'/fixtures/lang.php';
function app($c){return new $c;}function abort_unless($ok,$code,$message=''){if(!$ok)throw new \RuntimeException((string)$code);}
require __DIR__.'/../src/Filament/App/Pages/Dashboard.php';
use Simplify\Panel\Filament\App\Pages\Dashboard;use Simplify\Panel\Services\Access;use App\Services\Servers\ServerDeletionService;
foreach(['confirmation','owner','offline','service'] as $failure){$d=new Dashboard;$d->serverUuid='uuid';Access::$owner=$failure!=='owner';Access::$offline=$failure!=='offline';ServerDeletionService::$fail=$failure==='service';try{$d->deleteServer($failure==='confirmation'?'Wrong':'Example');throw new \LogicException('Must reject');}catch(\RuntimeException){}if($d->serverUuid!=='uuid'||$d->redirectUrl!==null||Access::$deleted)throw new \RuntimeException('Failed deletion must keep the server selected');}
Access::$owner=Access::$offline=true;ServerDeletionService::$fail=false;$d->deleteServer('Example');if($d->serverUuid!==null||$d->redirectUrl!=='/simplify')throw new \RuntimeException('Must clear the deleted selection and redirect');
$attributes=(new \ReflectionMethod(Dashboard::class,'deleteServer'))->getAttributes();if(!array_filter($attributes,fn($a)=>$a->getName()==='Livewire\\Attributes\\Renderless'))throw new \RuntimeException('Deleted page must not render');
echo "PASS deletion redirects without rendering, clears selection only after success and preserves deletion guards\n";
}
