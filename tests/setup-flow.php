<?php
declare(strict_types=1);
namespace Livewire {trait WithFileUploads {}}
namespace Filament\Pages { class Page {public $form;} }
namespace Filament\Forms\Concerns {trait InteractsWithForms {}}
namespace Filament\Forms\Contracts {interface HasForms {}}
namespace Filament\Notifications {class Notification {public static function make(){return new self;}public function title($v){return $this;}public function body($v){return $this;}public function danger(){return $this;}public function send(){}}}
namespace Simplify\Panel\Services {
 class DependencyCheck {public static int $calls=0;public static bool $pass=true;public static function inspect(...$args){self::$calls++;return [['name'=>'Built Pelican CSS and JavaScript','ok'=>self::$pass,'fix'=>'Rebuild assets']];}public static function ready($checks){return $checks!==[]&&!array_filter($checks,fn($c)=>!$c['ok']);}}
 class FeatureSettings {public static bool $completed=false;public static function enabled($key){return self::$completed;}public static function all(){return ['setup_completed'=>self::$completed];}}
 class CreationDefaults {public static function all(){return [];}}
 class CreatorAccess {public static function configuration(){return ['enabled'=>false];}public static function legacyUserGrants(){return 0;}}class RoleAccess {public const ABILITIES=['files'=>['Files','d'],'create'=>['Create','d']];public const LIMITS=['max_servers'=>[2,1,100]];public static function all(){return ['everyone'=>['abilities'=>[]],'roles'=>[]];}}
 class AppearanceSettings {public static function all(){return ['individual_colors'=>true];}}
 class Extensions {public static function webInstalled(){return false;}}
 class PerformanceSettings {public static function all(){return [];}}
}
namespace {
require_once __DIR__.'/fixtures/lang.php';
 function request(){return new class{function boolean($key){return false;}function query($key){return $GLOBALS['requestedSection']??null;}};}
 require __DIR__.'/../src/Filament/Admin/Pages/SimplifySettings.php';
 function user(){return new class {function isRootAdmin(){return true;}};}
 function abort_unless($ok,$code){if(!$ok)throw new \RuntimeException((string)$code);}
 function base_path(){return '/panel';}function plugin_path($id){return '/panel/plugins/'.$id;}
 use Simplify\Panel\Services\DependencyCheck as D;
 use Simplify\Panel\Services\FeatureSettings as F;
 $make=function(){ $p=new \Simplify\Panel\Filament\Admin\Pages\SimplifySettings;$p->form=new class{public array $data=[];public function fill($data){$this->data=$data;}public function getState(){throw new \RuntimeException('form cannot be saved when checks fail');}};return $p;};
 $page=$make();$page->mount();
 if(D::$calls!==0||$page->requirements!==[]||$page->setupStarted)throw new \RuntimeException('setup skipped its initial check stage');
 $page->refreshRequirements();if(!$page->requirementsReady()||$page->setupStarted)throw new \RuntimeException('passing checks started setup without button');
 $page->beginSetup();if(!$page->setupStarted||D::$calls!==2)throw new \RuntimeException('first setup did not recheck');
 D::$pass=false;$page->save();if($page->setupStarted||!$page->showDiagnostics)throw new \RuntimeException('changed dependencies did not return setup to checks');
 F::$completed=true;D::$calls=0;$settings=$make();$settings->mount();
 if(D::$calls!==0||$settings->showDiagnostics||$settings->requirements!==[])throw new \RuntimeException('ordinary settings visit ran or exposed checks');
 $settings->toggleDiagnostics();if(!$settings->showDiagnostics||D::$calls!==1)throw new \RuntimeException('optional diagnostics did not run');
 $settings->toggleDiagnostics();if($settings->showDiagnostics||D::$calls!==1)throw new \RuntimeException('hiding diagnostics reran checks');
 echo "PASS separate check/first-setup stages, fresh-save gate, quiet settings visits and optional diagnostics\n";
}
