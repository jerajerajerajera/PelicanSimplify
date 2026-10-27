<?php
declare(strict_types=1);
namespace Test {class Component {public array $options=[];public function __construct(public mixed $label=null){}public static function make($label=null){return new static($label);}public function __call($method,$arguments){$this->options[$method]=$arguments;return $this;}}}
namespace Livewire {trait WithFileUploads {}}
namespace Filament\Pages {class Page {}}
namespace Filament\Forms\Concerns {trait InteractsWithForms {}}
namespace Filament\Forms\Contracts {interface HasForms {}}
namespace Filament\Forms\Components {class Repeater extends \Test\Component {}class CheckboxList extends \Test\Component {}class Radio extends \Test\Component {}class Toggle extends \Test\Component {}class TextInput extends \Test\Component {}class Select extends \Test\Component {}class ColorPicker extends \Test\Component {}}
namespace Filament\Schemas {class Schema extends \Test\Component {}}
namespace Filament\Schemas\Components {class Text extends \Test\Component {}class View extends \Test\Component {}class Section extends \Test\Component {}class Wizard extends \Test\Component {public static function make($steps=null){$instance=new self;$instance->options['steps']=$steps;return $instance;}}class Tabs extends \Test\Component {}class Grid extends \Test\Component {}class Actions extends \Test\Component {}}
namespace Filament\Actions {class Action extends \Test\Component {}}
namespace Filament\Schemas\Components\Wizard {class Step extends \Test\Component {}}
namespace Filament\Schemas\Components\Tabs {class Tab extends \Test\Component {}}
namespace Illuminate\Support {class HtmlString {public function __construct($value){}}}
namespace Simplify\Panel\Services {
 class FeatureSettings {public static bool $complete=false;public static function enabled($key){return self::$complete;}public static function definitions(){return ['dashboard'=>['Dashboard','Description',true,'Interface'],'theme'=>['Theme','Description',true,'Appearance'],'easy_creator'=>['Easy Minecraft server creator','Description',false,'Add-ons']];}}
 class CurseForge {public static function panelHasKey(){return false;}public static function allowUserKeys(){return false;}}
 class WebsiteEmbed {public static function enabled(){return false;}}
 class PanelProfile {public const TYPES=['friends'=>'Me and my friends','hosting'=>'Hosting company'];public const FEATURES=['friends'=>[],'hosting'=>[]];}
 class ErrorExplainer {public const AUDIENCES=['everyone'=>'Everyone'];public const PER_USER=6;public const PER_DAY=200;public static function settings(){return ['has_key'=>false,'saved_keys'=>[]];}public static function providers(){return ['anthropic'=>'Claude by Anthropic'];}public static function models($p){return ['claude-opus-5-5'=>'Opus'];}public static function provider($p){return ErrorExplainer::class;}public static function name(){return 'Anthropic';}public static function keyPlaceholder(){return 'sk-ant-';}public static function keyHelp(){return 'help';}}
 class Extensions {public static function webInstalled(){return false;}}
 class PelicanSettingsTabs {public static function all(){return [['key'=>'general','label'=>'General','icon'=>'tabler-home','url'=>'/admin/settings?simplify_embed=1&tab=general%3A%3Adata%3A%3Atab'],['key'=>'mail','label'=>'Mail','icon'=>'tabler-mail','url'=>'/admin/settings?simplify_embed=1&tab=mail%3A%3Adata%3A%3Atab']];}}
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
 if(array_values(array_map(fn($step)=>$step->label,array_filter($steps,fn($step)=>!isset($step->options['visible']))))!==$page->setupSteps())throw new \RuntimeException('progress navigation and wizard steps disagree');
 // The panel type, four questions, Hosting for a hosting company and a step per add-on that needs a value; conditional steps and the progress bar read one list.
 if(array_map(fn($step)=>$step->label,$steps)!==['Panel type','Features','People & access','Look','Hosting','Server Maker','Automatic subdomains','Ready']||$steps[3]->options['schema'][0][1]->label!=='Colours'||$steps[1]->options['schema'][0][1]->label!=='Add-ons'||$steps[0]->options['schema'][0][0]->options['schema'][0][0]->label!=='profile.type')throw new \RuntimeException('wizard steps are not panel type, the four questions, Hosting and add-on steps');
 if(!isset($steps[4]->options['visible'])||!isset($steps[5]->options['visible'])||isset($steps[0]->options['visible'])||isset($steps[1]->options['visible'])||!isset($steps[1]->options['schema'][0][1]->options['schema'][0][0]->options['live'])||!isset($steps[0]->options['schema'][0][0]->options['schema'][0][0]->options['afterStateUpdated']))throw new \RuntimeException('Hosting and add-on steps not conditional, or the panel type does not fill in defaults');
 if($page->setupSteps()!==['Panel type','Features','People & access','Look','Ready'])throw new \RuntimeException('progress bar shows steps of add-ons that are off');
 $page->data=['features'=>['game_creator'=>true,'subdomains'=>false]];if($page->setupSteps()!==['Panel type','Features','People & access','Look','Server Maker','Ready'])throw new \RuntimeException('progress bar does not follow the add-on switches');
 $page->data=['profile'=>['type'=>'hosting']];if($page->setupSteps()!==['Panel type','Features','People & access','Look','Hosting','Ready']||!($steps[4]->options['visible'][0])()||($steps[5]->options['visible'][0])())throw new \RuntimeException('Hosting step does not follow the panel type');$page->data=[];
 if(!function_exists('e')){function e($v){return htmlspecialchars((string)$v);}}
 $summary=\Simplify\Panel\Filament\Admin\Pages\SimplifySettings::setupSummary(['dashboard'=>true,'theme'=>true],['files','create']);
 if(!str_contains($summary,'Dashboard')||!str_contains($summary,'Files, Create')||!str_contains($summary,'ps-setup-summary'))throw new \RuntimeException('setup summary misses the chosen features and abilities');
 if(!isset($wizard->options['submitAction'])||$wizard->options['skippable']!==[false])throw new \RuntimeException('wizard final save or validation guard missing');
 \Simplify\Panel\Services\FeatureSettings::$complete=true;
 $schema=$page->form(new \Filament\Schemas\Schema);$tabs=$schema->options['components'][0][0]->options['tabs'][0];
 if(array_map(fn($tab)=>$tab->label,$tabs)!==['General','Appearance','Server banners','Users & access','Backups & performance','Hosting','Easy Minecraft server creator','Easy server creator','People & roles','Plugin manager','Automatic subdomains','Error explanations','Plugin sites','Website embed','General','Mail'])throw new \RuntimeException('settings tabs lost a setup category');
 // Pelican's own settings close the menu: own key and id (no clash with Simplify's General), one frame per tab, counted for the heading.
 $pelican=$tabs[14];if(($pelican->options['key'][0]??null)!=='pelican-general'||($pelican->options['id'][0]??null)!=='pelican-general'||$pelican->options['schema'][0][0]->label!=='pelican-simplify::filament.admin.pelican-settings-frame'||($pelican->options['schema'][0][0]->options['viewData'][0]['label']??null)!=='General'||$page->pelicanTabs!==2||($tabs[14]->options['badge'][0]??null)!==null)throw new \RuntimeException('Pelican settings tabs not framed inside Simplify settings');
 $profiles=$tabs[6]->options['schema'][0][1]->options['schema'][0];
 if(count($profiles)!==6||$profiles[3]->label!=='Modpacks'||$profiles[2]->label!=='Mods'||count($profiles[3]->options['schema'][0])!==3)throw new \RuntimeException('CPU/RAM/disk profiles missing from settings');
 $explain=$tabs[11]->options['schema'][0][0];
 if(($tabs[11]->options['badge'][0]??null)!=='Add-on'||$explain->label!=='Explain this error'||$explain->options['schema'][0][0]->label!=='explainer.provider'||!isset($explain->options['schema'][0][0]->options['live'])||$explain->options['schema'][0][2]->label!=='explainer.key'||!isset($explain->options['schema'][0][2]->options['password']))throw new \RuntimeException('error explanations tab missing its AI choice or key field');
 if(($tabs[8]->options['badge'][0]??null)!=='Add-on'||$tabs[0]->options['badge'][0]!==null||$tabs[2]->options['badge'][0]!==null)throw new \RuntimeException('add-on tabs not marked');
 echo "PASS separate setup pages, matching numbered navigation, final-step submit, category tabs and editable modpack resource columns\n";
}
