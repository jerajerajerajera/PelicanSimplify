<?php
declare(strict_types=1);
namespace App\Enums {enum TablerIcon:string {case Server2="server";}}
namespace Test {class Component {public array $options=[];public function __construct(public mixed $name=null){}public static function make($name=null){return new static($name);}public function __call($method,$args){$this->options[$method]=$args;return $this;}}}
namespace Filament\Actions {class Action extends \Test\Component{}}
namespace Filament\Pages {class Page {}}
namespace Filament\Forms\Concerns {trait InteractsWithForms {}}
namespace Filament\Forms\Contracts {interface HasForms {}}
namespace Filament\Forms\Components {class Radio extends \Test\Component{}class Hidden extends \Test\Component{}class Select extends \Test\Component{}class TextInput extends \Test\Component{}class Toggle extends \Test\Component{}class Textarea extends \Test\Component{}class ViewField extends \Test\Component{}class FileUpload extends \Test\Component{}class Checkbox extends \Test\Component{}class KeyValue extends \Test\Component{}}
namespace Filament\Schemas {class Schema extends \Test\Component{}}
namespace Filament\Schemas\Components {class Wizard extends \Test\Component{}class Section extends \Test\Component{}class View extends \Test\Component{}}
namespace Filament\Schemas\Components\Wizard {class Step extends \Test\Component{}}
namespace Filament\Schemas\Components\Utilities {class Set {public array $data=[];public function __invoke($key,$value){$this->data[$key]=$value;}}}
namespace Illuminate\Support {class HtmlString {public function __construct($html){}}}
namespace Simplify\Panel\Services {
class Extensions {public static function webAdapter(){return null;}}
class FeatureSettings {public static function enabled($key){return true;}}
class CreationDefaults {public static function forType($type){return match($type){'modded'=>['cpu'=>300,'ram_gb'=>8,'disk_mb'=>30720],'plus'=>['cpu'=>150,'ram_gb'=>6,'disk_mb'=>15360],default=>['cpu'=>100,'ram_gb'=>2,'disk_mb'=>5120]};}}
class CreatorAccess {public static bool $isAdmin=true;public static function admin(){return self::$isAdmin;}public static function settings(){return ['max_cpu'=>200,'max_memory_mb'=>4096,'max_disk_mb'=>10240];}}
class MinecraftCatalog {public static function eggForType($type){return (object)['id'=>1];}}
}
namespace {
function view($name){return new class{function render(){return '';}};}
require __DIR__.'/../src/Filament/Admin/Pages/CreateMinecraftServer.php';
function check($ok,$message){if(!$ok)throw new \RuntimeException($message);echo "PASS $message\n";}
$page=new \Simplify\Panel\Filament\Admin\Pages\CreateMinecraftServer;$page->creationGame='minecraft';
$schema=$page->form(new \Filament\Schemas\Schema);$wizard=$schema->options['components'][0][1];$steps=$wizard->name;
$resources=$steps[1]->options['schema'][0][2]->options['schema'][0];
check(array_map(fn($field)=>$field->name,$resources)===['ram_gb','ram_custom_gb','cpu_choice','cpu','disk_choice','disk_mb'],'RAM, CPU and disk controls all live in Minecraft step two');
$type=$steps[0]->options['schema'][0][0];$set=new \Filament\Schemas\Components\Utilities\Set;
$callback=$type->options['afterStateUpdated'][0];$callback('modded',$set);
check($set->data['cpu']===300&&$set->data['cpu_choice']==='300'&&$set->data['ram_gb']==='8'&&$set->data['disk_mb']===30720&&$set->data['disk_choice']==='30720','custom configured modpack defaults preselect all controls');
$set->data['cpu']=400;$set->data['disk_mb']=40960;$callback('plus',$set);
check($set->data['cpu']===150&&$set->data['cpu_choice']==='150'&&$set->data['ram_gb']==='custom'&&$set->data['ram_custom_gb']===6&&$set->data['disk_mb']===15360,'going back and switching type resets every resource to its profile');
$callback('vanilla',$set);check($set->data['ram_gb']==='2'&&$set->data['cpu']===100&&$set->data['disk_mb']===5120,'vanilla switch restores vanilla defaults');
$page->data=['cpu_choice'=>'400','disk_choice'=>'40960','cpu'=>100,'disk_mb'=>5120];$page->hydrate();
check($page->data['cpu']===400&&$page->data['disk_mb']===40960,'preset choices update actual server allocations without a round trip per click');
$page->data['cpu_choice']='custom';$page->data['cpu']=450;$page->hydrate();check($page->data['cpu']===450,'custom selection keeps the typed allocation');
\Simplify\Panel\Services\CreatorAccess::$isAdmin=false;$callback('modded',$set);
check($set->data['cpu']===200&&$set->data['ram_gb']==='4'&&$set->data['disk_mb']===10240,'profile defaults are capped by effective user allowances');
check(!array_key_exists(400,$page->resourceOptions('cpu','modded'))&&!array_key_exists(20480,$page->resourceOptions('disk_mb','modded')),'preset options respect effective CPU and disk limits');
}
