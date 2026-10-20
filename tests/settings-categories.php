<?php
declare(strict_types=1);
namespace Test {class Component {public array $options=[];public function __construct(public mixed $label=null){}public static function make($label=null){return new static($label);}public function __call($method,$arguments){$this->options[$method]=$arguments;return $this;}}}
namespace Livewire {trait WithFileUploads {}}
namespace Filament\Pages {class Page {}}
namespace Filament\Forms\Concerns {trait InteractsWithForms {}}
namespace Filament\Forms\Contracts {interface HasForms {}}
namespace Filament\Forms\Components {class Repeater extends \Test\Component {}class CheckboxList extends \Test\Component {}class Toggle extends \Test\Component {}class TextInput extends \Test\Component {}class Select extends \Test\Component {}class ColorPicker extends \Test\Component {}}
namespace Filament\Schemas {class Schema extends \Test\Component {}}
namespace Filament\Schemas\Components {class Text extends \Test\Component {}class View extends \Test\Component {}class Section extends \Test\Component {}class Wizard extends \Test\Component {public static function make($steps=null){$instance=new self;$instance->options['steps']=$steps;return $instance;}}class Tabs extends \Test\Component {}class Grid extends \Test\Component {}}
namespace Filament\Schemas\Components\Wizard {class Step extends \Test\Component {}}
namespace Filament\Schemas\Components\Tabs {class Tab extends \Test\Component {}}
namespace Illuminate\Support {class HtmlString {public function __construct($value){}}}
namespace Simplify\Panel\Services {
 class FeatureSettings {public static bool $complete=false;public static function enabled($key){return self::$complete;}public static function definitions(){return ['dashboard'=>['Dashboard','Description',true,'Interface'],'theme'=>['Theme','Description',true,'Appearance']];}}
 class Extensions {public static function webInstalled(){return false;}}
 class CreatorAccess {public static function legacyUserGrants(){return 0;}}
 class RoleAccess {public const ABILITIES=['files'=>['Files','d'],'create'=>['Create','d']];public const LIMITS=['max_servers'=>[2,1,100]];public static function all(){return ['everyone'=>['abilities'=>[]],'roles'=>[]];}}
 class PerformanceSettings {public const FIELDS=['overview_seconds'=>['Overview','Help',30,10,600],'server_seconds'=>['Server','Help',20,5,600],'restart_seconds'=>['Restart','Help',3,2,60],'health_seconds'=>['Health','Help',30,10,600],'backup_interval_minutes'=>['Backups','Help',360,30,10080],'backup_retention'=>['Points','Help',2,1,100]];}
}
namespace {
require __DIR__.'/../src/Services/BrandSettings.php';
 require __DIR__.'/../src/Filament/Admin/Pages/SimplifySettings.php';
 function view($name){return new class{function render(){return '<button>Finish setup</button>';}};}
 $page=new \Simplify\Panel\Filament\Admin\Pages\SimplifySettings;
 $schema=$page->form(new \Filament\Schemas\Schema);$wizard=$schema->options['components'][0][0];$steps=$wizard->options['steps'];
 if(array_map(fn($step)=>$step->label,$steps)!==$page->setupSteps())throw new \RuntimeException('progress navigation and wizard steps disagree');
 if(array_map(fn($step)=>$step->label,$steps)!==['General','Appearance','Users & access','Backups & performance','Easy Minecraft server creator','Easy server creator','People & roles','Plugin manager','Automatic subdomains']||$steps[1]->options['schema'][0][1]->label!=='Colours')throw new \RuntimeException('categories not separate pages');
 if(!isset($wizard->options['submitAction'])||$wizard->options['skippable']!==[false])throw new \RuntimeException('wizard final save or validation guard missing');
 \Simplify\Panel\Services\FeatureSettings::$complete=true;
 $schema=$page->form(new \Filament\Schemas\Schema);$tabs=$schema->options['components'][0][0]->options['tabs'][0];
 if(array_map(fn($tab)=>$tab->label,$tabs)!==['General','Appearance','Users & access','Backups & performance','Server banners','Easy Minecraft server creator','Easy server creator','People & roles','Plugin manager','Automatic subdomains'])throw new \RuntimeException('settings tabs lost a setup category');
 $profiles=$tabs[5]->options['schema'][0][1]->options['schema'][0];
 if(count($profiles)!==3||$profiles[2]->label!=='Modded / modpacks'||count($profiles[2]->options['schema'][0])!==3)throw new \RuntimeException('CPU/RAM/disk profiles missing from settings');
 if(($tabs[7]->options['badge'][0]??null)!=='Add-on'||$tabs[0]->options['badge'][0]!==null)throw new \RuntimeException('add-on tabs not marked');
 echo "PASS separate setup pages, matching numbered navigation, final-step submit, category tabs and editable modpack resource columns\n";
}
