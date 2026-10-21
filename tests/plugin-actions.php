<?php
declare(strict_types=1);
// Buttons other plugins add to the console and the server list: groups are flattened, hidden buttons and buttons an
// administrator switched off are left out, links keep their link, everything else gets a run link signed for this
// session, and the server is the tenant while the checks run (and is put back afterwards).
namespace Illuminate\Support\Facades {
 class Cache {public static function remember($k,$t,$f){return $f();}}
 class Http {}
 class Storage {public static array $files=[];public static function disk($d){return new class{public function exists($p){return isset(Storage::$files[$p]);}public function get($p){return Storage::$files[$p];}};}}
}
namespace App\Models {class Plugin {public static function all(){return [(object)['id'=>'ucs','name'=>'User Creatable Servers','namespace'=>'Vendor\\Ucs','status'=>'enabled'],(object)['id'=>'mclogs','name'=>'Mclogs Uploader','namespace'=>'Vendor\\Mclogs','status'=>'enabled']];}}}
namespace Filament\Facades {class Filament {public static $tenant='outer';public static array $seen=[];public static function getTenant(){return self::$tenant;}public static function setTenant($t,bool $isQuiet=false){self::$tenant=$t;}}}
namespace Filament\Actions {
 class Action {
  public function __construct(public string $name,public array $o=[]){}
  public function getName(){return $this->name;}
  public function isHidden(){return ($this->o['hidden']??fn()=>false)();}
  public function isDisabled(){return $this->o['disabled']??false;}
  public function getTooltip(){return $this->o['tooltip']??null;}
  public function getLabel(){return $this->o['label']??ucfirst($this->name);}
  public function getUrl(){return $this->o['url']??null;}
  public function getIcon(){return null;}
  public function shouldOpenUrlInNewTab(){return false;}
 }
 class ActionGroup {public function __construct(public array $actions){}public function getActions(){return $this->actions;}}
}
namespace Vendor\Ucs {class CreateServerAction extends \Filament\Actions\Action {}}
namespace Vendor\Mclogs {class UploadLogsAction extends \Filament\Actions\Action {}}
namespace App\Filament\App\Resources\Servers\Pages {class ListServers {protected static array $customHeaderActions=[];}}
namespace App\Filament\Server\Pages {class Console {protected static array $customHeaderActions=[];}}
namespace Simplify\Panel\Filament\Server\Pages {class PluginActions {public static function getUrl($q,$panel,$tenant){return '/server/'.$tenant->short.'/simplify-actions?'.http_build_query($q);}}}
namespace Simplify\Panel\Filament\App\Pages {class PluginActions {public static function getUrl($q,$panel,$tenant){return '/simplify-actions?'.http_build_query($q);}}}
namespace {
 function class_basename($c){return basename(str_replace('\\','/',$c));}
 function session(){return new class{public function getId(){return $GLOBALS['session'];}};}
 function config($k,$d=null){return 'base64:secret';}
 function report($e){throw $e;}
 $GLOBALS['session']='abc';
 require __DIR__.'/../src/Services/ExternalPlugins.php';
 use Simplify\Panel\Services\ExternalPlugins as X;
 $check=function(bool $ok,string $what){if(!$ok)throw new \RuntimeException($what);};
 $set=function(string $class,array $value){(new \ReflectionProperty($class,'customHeaderActions'))->setValue(null,$value);};

 // Server list: a link button keeps its link; a disabled one stays visible but disabled.
 $set('App\\Filament\\App\\Resources\\Servers\\Pages\\ListServers',['before'=>[new \Vendor\Ucs\CreateServerAction('create_server',['label'=>'Create server','url'=>'/create-server','disabled'=>true])]]);
 $app=X::actions('app');
 $check(count($app)===1&&$app[0]['url']==='/create-server'&&$app[0]['run_url']===null&&$app[0]['disabled']===true&&$app[0]['plugin']==='User Creatable Servers','link button: '.json_encode($app));

 // Console: a group is flattened; a hidden button is left out; the server is the tenant during the checks only.
 $server=(object)['short'=>'abcd1234'];
 $set('App\\Filament\\Server\\Pages\\Console',['before'=>[new \Filament\Actions\ActionGroup([
   new \Vendor\Mclogs\UploadLogsAction('upload_logs',['tooltip'=>'Upload logs','label'=>'','hidden'=>function()use($server){\Filament\Facades\Filament::$seen[]=\Filament\Facades\Filament::getTenant();return false;}]),
   new \Filament\Actions\Action('secret',['hidden'=>fn()=>true]),
 ])],'after'=>[new \Filament\Actions\Action('ping')]]);
 $server_actions=X::actions('server',$server);
 $check(array_column($server_actions,'name')===['upload_logs','ping'],'console buttons: '.json_encode(array_column($server_actions,'name')));
 $check($server_actions[0]['label']==='Upload logs'&&$server_actions[1]['plugin']===null,'tooltip as label, unknown plugin');
 $check(\Filament\Facades\Filament::$seen===[$server]&&\Filament\Facades\Filament::getTenant()==='outer','tenant during the checks, restored after');
 parse_str(parse_url($server_actions[0]['run_url'],PHP_URL_QUERY),$q);
 $check($q['run']==='upload_logs'&&hash_equals(X::runToken('server','upload_logs'),$q['token']),'signed run link');
 $GLOBALS['session']='other';
 $check(!hash_equals(X::runToken('server','upload_logs'),$q['token']),'another session cannot use the link');
 $check(X::runToken('app','upload_logs')!==X::runToken('server','upload_logs'),'the token names the panel');

 // Switched off by an administrator: left out, still in the catalog of the settings.
 \Illuminate\Support\Facades\Storage::$files['simplify/external-pages.json']=json_encode(['hidden'=>['server-action:ping']]);
 $check(array_column(X::actions('server',$server),'name')===['upload_logs'],'switched-off button is left out');
 $check(count(X::actions('server',$server,true))===3,'all buttons for the catalog, hidden ones included');
 echo "PASS plugin buttons: links, groups, hidden and switched-off buttons, tenant during checks, signed run links\n";
}
