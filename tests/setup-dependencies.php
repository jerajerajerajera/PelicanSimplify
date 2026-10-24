<?php
declare(strict_types=1);
namespace Livewire {trait WithFileUploads {}}
namespace Filament\Pages { class Page {} }
namespace Filament\Forms\Concerns { trait InteractsWithForms {} }
namespace Filament\Forms\Contracts { interface HasForms {} }
namespace Filament\Notifications { class Notification { public static array $titles=[]; public static function make(){return new self;} public function title($title){self::$titles[]=$title;return $this;} public function body($value){return $this;} public function danger(){return $this;} public function send(){} } }
namespace Simplify\Panel\Services { class FeatureSettings { public static function enabled($key){return false;} } }
namespace {
 require __DIR__.'/../src/Services/BuildInfo.php';
 require __DIR__.'/../src/Services/UploadLimits.php';
 require __DIR__.'/../src/Services/DependencyCheck.php';
 require __DIR__.'/../src/Filament/Admin/Pages/SimplifySettings.php';
 function user(){return new class{function isRootAdmin(){return true;}};}
 function abort_unless($ok,$code){if(!$ok)throw new \RuntimeException((string)$code);}
 function base_path(){return sys_get_temp_dir().'/simplify-nonexistent-panel';}
 function plugin_path($id){return __DIR__.'/..';}
 function config($name){return $name==='app.version'?($GLOBALS['panelVersion']??'1.0.0-beta38'):'sync';}
 $page=new \Simplify\Panel\Filament\Admin\Pages\SimplifySettings;
 // No form or save services exist here. A direct save must return before accessing them.
 $page->save();
 if($page->requirementsReady()||!in_array('Setup blocked',\Filament\Notifications\Notification::$titles,true))throw new \RuntimeException('direct save bypassed dependency gate');
 $page->requirements=[['name'=>'PHP extension: fake','ok'=>true,'fix'=>'']];
 $page->beginSetup();
 if($page->setupStarted||$page->requirementsReady())throw new \RuntimeException('first-setup button reused stale passed checks');
 $groups=$page->requirementGroups();
 if(!isset($groups['PHP runtime'],$groups['Styles and scripts'],$groups['Panel services']))throw new \RuntimeException('grouped checks omitted required categories');
 if(array_sum(array_map('count',$groups))!==count($page->requirements))throw new \RuntimeException('grouping dropped a dependency');
 // A Pelican release Simplify was not tested on warns, but never blocks installation on its own.
 $release=fn()=>array_values(array_filter(\Simplify\Panel\Services\DependencyCheck::inspect(base_path(),plugin_path('pelican-simplify')),fn($c)=>$c['name']==='Tested Pelican release'))[0];
 if(!$release()['ok'])throw new \RuntimeException('beta38 is a tested release');
 $GLOBALS['panelVersion']='v1.0.0-rc.2'; if(!$release()['ok'])throw new \RuntimeException('rc.2 is a tested release');
 $GLOBALS['panelVersion']='1.0.0-rc.3'; $untested=$release();
 if($untested['ok']||$untested['blocking']!==false||!str_contains($untested['fix'],'1.0.0-rc.3'))throw new \RuntimeException('an untested release must be a non-blocking warning naming the version');
 \Simplify\Panel\Services\DependencyCheck::assertReady([['name'=>'CSS','ok'=>true,'fix'=>''],$untested]);
 $page->requirements=[$untested]; if(!isset($page->requirementGroups()['Panel services']))throw new \RuntimeException('release warning belongs with panel services');
 echo "PASS an untested Pelican release warns under panel services and does not block installation\n";
 echo "PASS failed or stale checks cannot open first setup; grouped cards retain every check\n";
 echo "PASS direct setup save cannot bypass failed dependencies or write settings\n";
}
