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
namespace Filament\Schemas\Components {class Group extends \Test\Component{}class Wizard extends \Test\Component{}class Section extends \Test\Component{}class View extends \Test\Component{}}
namespace Filament\Schemas\Components\Wizard {class Step extends \Test\Component{}}
namespace Filament\Schemas\Components\Utilities {class Set {public array $data=[];public function __invoke($key,$value){$this->data[$key]=$value;}}}
namespace Illuminate\Support {class HtmlString {public function __construct($html){}}}
namespace Simplify\Panel\Services {
class Extensions {public static function webAdapter(){return null;}}
class FeatureSettings {public static function enabled($key){return true;}}
class CreationDefaults {public static function forType($type){return match($type){'modded'=>['cpu'=>300,'ram_gb'=>8,'disk_mb'=>30720],'plus'=>['cpu'=>150,'ram_gb'=>6,'disk_mb'=>15360],default=>['cpu'=>100,'ram_gb'=>2,'disk_mb'=>5120]};}}
class CreatorAccess {public static bool $isAdmin=true;public static function admin(){return self::$isAdmin;}public static function settings(){return ['max_cpu'=>200,'max_memory_mb'=>4096,'max_disk_mb'=>10240];}}
class MinecraftCatalog {const TYPES=['vanilla','plus','loader','modded','bedrock','proxy'];const SOFTWARE=['plus'=>['paper'=>'Paper','purpur'=>'Purpur']];public static function eggForType($type,$software=null){return (object)['id'=>1];}public static function software($type,$software){return isset(self::SOFTWARE[$type])?($software?:array_key_first(self::SOFTWARE[$type])):null;}public static function javaServer($type){return !in_array($type,['bedrock','proxy'],true);}public static function usesVersion($type){return in_array($type,['vanilla','plus','loader'],true);}}
}
namespace {
function view($name){return new class{function render(){return '';}};}
require __DIR__.'/../src/Filament/Admin/Pages/CreateMinecraftServer.php';
function check($ok,$message){if(!$ok)throw new \RuntimeException($message);echo "PASS $message\n";}
$page=new \Simplify\Panel\Filament\Admin\Pages\CreateMinecraftServer;$page->creationGame='minecraft';
$schema=$page->form(new \Filament\Schemas\Schema);$wizard=$schema->options['components'][0][0];$steps=$wizard->name;
check(array_map(fn($step)=>$step->name,array_slice($steps,0,5))===['Game','Content','Server','Resources','Server properties'],'one wizard for every game: game, settings, server, resources, then Minecraft properties');
$resources=$steps[3]->options['schema'][0][0]->options['schema'][0];
check(array_map(fn($field)=>$field->name,$resources)===['ram_gb','ram_custom_gb','cpu_choice','cpu','disk_choice','disk_mb'],'RAM, CPU and disk controls have their own Resources step');
$type=$steps[1]->options['schema'][0][0]->options['schema'][0][0];
check($type->name==='server_type'&&array_keys($type->options['options'][0])===['vanilla','plus','loader','modded','bedrock','proxy'],'Minecraft step offers every server type, proxies last');
$callback=$type->options['afterStateUpdated'][0];$page->data=[];
$apply=function($type)use($callback,$page){$callback($type);return $page->data;};
$data=$apply('modded');
check($data['cpu']===300&&$data['cpu_choice']==='300'&&$data['ram_gb']==='8'&&$data['disk_mb']===30720&&$data['disk_choice']==='30720','custom configured modpack defaults preselect all controls');
$page->data['cpu']=400;$page->data['disk_mb']=40960;$data=$apply('plus');
check($data['cpu']===150&&$data['cpu_choice']==='150'&&$data['ram_gb']==='custom'&&$data['ram_custom_gb']===6&&$data['disk_mb']===15360,'going back and switching type resets every resource to its profile');
check($data['server_software']==='paper'&&$data['properties_enabled']===true,'Plugins start on Paper with server properties on');
$data=$apply('vanilla');check($data['ram_gb']==='2'&&$data['cpu']===100&&$data['disk_mb']===5120&&$data['server_software']===null,'vanilla switch restores vanilla defaults');
$data=$apply('bedrock');check($data['properties_enabled']===false,'Bedrock skips the Java server properties');
$page->data=['cpu_choice'=>'400','disk_choice'=>'40960','cpu'=>100,'disk_mb'=>5120];$page->hydrate();
check($page->data['cpu']===400&&$page->data['disk_mb']===40960,'preset choices update actual server allocations without a round trip per click');
$page->data['cpu_choice']='custom';$page->data['cpu']=450;$page->hydrate();check($page->data['cpu']===450,'custom selection keeps the typed allocation');
\Simplify\Panel\Services\CreatorAccess::$isAdmin=false;$data=$apply('modded');
check($data['cpu']===200&&$data['ram_gb']==='4'&&$data['disk_mb']===10240,'profile defaults are capped by effective user allowances');
check(!array_key_exists(400,$page->resourceOptions('cpu','modded'))&&!array_key_exists(20480,$page->resourceOptions('disk_mb','modded')),'preset options respect effective CPU and disk limits');
}
