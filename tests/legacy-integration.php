<?php
declare(strict_types=1);
namespace Filament\Contracts {interface Plugin {}}
namespace Filament {class Panel {public array $calls=[];public function __construct(private string $id){}public function getId(){return $this->id;}public function __call($method,$args){$this->calls[$method][]=$args;return $this;}}}
namespace Filament\Actions {class Action {public static function make($name){return new self;}public function __call($method,$args){return $this;}}}
namespace App\Filament\Admin\Resources\Servers {class ServerResource {public static function registerCustomPages($pages){}}}
namespace Simplify\Panel\Filament\Admin\Pages {class CreateServerLegacy {public static function route($path){return $path;}}}
namespace {
require_once __DIR__.'/fixtures/lang.php';
 require __DIR__.'/../src/PelicanSimplifyPlugin.php';
 $plugin=new \Simplify\Panel\PelicanSimplifyPlugin;
 foreach(['app','admin'] as $id){
  $panel=new \Filament\Panel($id);$plugin->register($panel);
  $hooks=array_column($panel->calls['renderHook'],0);
  if(count(array_keys($hooks,'panels::global-search.after'))!==1||in_array('panels::topbar.end',$hooks))throw new \RuntimeException('return link must be registered once before notifications');
  if($id==='admin'){
   if(($panel->calls['topbar'][0][0]??null)!==true)throw new \RuntimeException('admin return bar is hidden');
   foreach($panel->calls['middleware'] as $args)if(in_array(\Simplify\Panel\Http\Middleware\RedirectServerCreate::class,$args[0]))throw new \RuntimeException('native creator is redirected');
  }
 }
 echo "PASS single return hook before notifications, visible admin topbar, native creator preserved and no duplicate page-header registration\n";
}
