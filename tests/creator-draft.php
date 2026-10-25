<?php
declare(strict_types=1);
namespace App\Enums { enum TablerIcon: string { case Server2='server'; } }
namespace Filament\Pages { class Page {} }
namespace Filament\Forms\Contracts { interface HasForms {} }
namespace Filament\Forms\Concerns { trait InteractsWithForms {} }
namespace Illuminate\Support\Facades {
 class Storage { public static array $files=[]; public static function disk($disk){return new self;} public function exists($p){return isset(self::$files[$p]);} public function get($p){return self::$files[$p]??null;} public function put($p,$v){self::$files[$p]=$v;return true;} }
 class Crypt { public static function encryptString($v){return 'encrypted:'.base64_encode($v);} public static function decryptString($v){return base64_decode(substr($v,10));} }
 class Cache { public static function lock($key,$seconds){return new self;} public function block($seconds,$callback){return $callback();} }
}
namespace Simplify\Panel\Services { class Extensions {public static bool $web=false;public static function webAdapter(){return self::$web?new class {public function creatorSteps($page){return [new class {public function getLabel(){return 'Website display';}}];}}:null;}} class FeatureSettings {public static bool $backups=true;public static function enabled($key){return $key!=='backups'||self::$backups;} } class CreatorAccess { public static bool $allowed=true; public static function canCreate(){return self::$allowed;} } }
namespace {
 function user(){return (object)['id'=>$GLOBALS['userId']];}
 function abort_unless($allowed,$status){if(!$allowed)throw new RuntimeException('403');}
 require __DIR__.'/../src/Filament/Admin/Pages/CreateMinecraftServer.php';
 class DraftPage extends \Simplify\Panel\Filament\Admin\Pages\CreateMinecraftServer { public function dispatch(...$args){} public function finish(){ $m=new ReflectionMethod(get_parent_class($this),'finishDraft');$m->invoke($this);} }
 function check($condition,$message){if(!$condition)throw new RuntimeException($message);echo 'PASS '.$message."\n";}
 $GLOBALS['userId']=7;$page=new DraftPage;$page->draftSession=microtime(true);$page->data=['name'=>'Pirate crew','game_variables'=>['PASSWORD'=>'private'],'server_icon'=>new stdClass];
 check($page->saveDraft(4),'save partial draft without form validation');
 $path='ssc/creator-drafts/7.enc';$raw=\Illuminate\Support\Facades\Storage::$files[$path];$draft=json_decode(\Illuminate\Support\Facades\Crypt::decryptString($raw),true);
 check($draft['state']['name']==='Pirate crew'&&$draft['step']===4,'state and wizard step retained');check(!isset($draft['state']['server_icon']),'temporary upload objects excluded');
 $GLOBALS['userId']=8;$other=new DraftPage;$other->draftSession=microtime(true);$other->data=['name'=>'Other user'];$other->saveDraft();check(\Illuminate\Support\Facades\Storage::$files[$path]===$raw,'user drafts isolated');
 $GLOBALS['userId']=7;$old=new DraftPage;$old->draftSession=$page->draftSession;$old->data=['name'=>'Late autosave'];$page->finish();$complete=\Illuminate\Support\Facades\Storage::$files[$path];$old->saveDraft();check(\Illuminate\Support\Facades\Storage::$files[$path]===$complete,'late autosave cannot recreate completed draft');
 $new=new DraftPage;$new->draftSession=microtime(true)+1;$new->data=['name'=>'New draft'];$new->saveDraft();check(\Illuminate\Support\Facades\Storage::$files[$path]!==$complete,'new creator session can save after completion');
 $minecraft=new DraftPage;$minecraft->creationGame='minecraft';$minecraft->draftSession=microtime(true)+2;$minecraft->data=['game'=>'','name'=>'Minecraft draft'];$minecraft->saveDraft(2,5);
 $game=new DraftPage;$game->creationGame='other';$game->draftSession=microtime(true)+2;$game->data=['game'=>'other','name'=>'Other game draft'];$game->saveDraft();
 $mcPath='ssc/creator-drafts/7-minecraft.enc';$gamePath='ssc/creator-drafts/7-other.enc';$gameRaw=\Illuminate\Support\Facades\Storage::$files[$gamePath];
 check(json_decode(\Illuminate\Support\Facades\Crypt::decryptString(\Illuminate\Support\Facades\Storage::$files[$mcPath]),true)['state']['name']==='Minecraft draft','Minecraft draft saved separately from legacy and other games');
 check(json_decode(\Illuminate\Support\Facades\Crypt::decryptString(\Illuminate\Support\Facades\Storage::$files[$mcPath]),true)['state']['game']==='minecraft','locked Minecraft category survives empty client game value');
 check(json_decode(\Illuminate\Support\Facades\Crypt::decryptString(\Illuminate\Support\Facades\Storage::$files[$mcPath]),true)['furthest_step']===5,'visited-step progress remains saved when navigating backwards');
 $minecraft->finish();check(\Illuminate\Support\Facades\Storage::$files[$gamePath]===$gameRaw,'completing Minecraft leaves other game draft intact');
 \Simplify\Panel\Services\CreatorAccess::$allowed=false;try{$new->saveDraft();throw new RuntimeException('access missing');}catch(RuntimeException $e){check($e->getMessage()==='403','creator access enforced for draft writes');}
 echo "Draft service flow checks use storage, encryption and lock doubles; live framework checks are still required.\n";
}

namespace {
 $page=new DraftPage;$page->creationGame='minecraft';$step=new \ReflectionMethod(get_parent_class($page),'restoreWizardStep');$step->setAccessible(true);
 check($step->invoke($page,4,2)===4,'backup draft stays on backups without the web plugin');
 check($step->invoke($page,5,2)===5,'review draft stays on review without the web plugin');
 check($step->invoke($page,6,1)===5,'legacy game-step draft migrates through both wizard changes');
 check($step->invoke($page,4,3)===4,'current no-web draft stays on backups');
 $page->creationGame='other';check($step->invoke($page,5,2)===5,'other-game flow omits the website step too');
 $page->creationGame='minecraft';\Simplify\Panel\Services\Extensions::$web=true;check(in_array('Website display',$page->wizardStepLabels(),true),'web step appears only when supplied by the web adapter');check($step->invoke($page,4,2)===5,'old backup draft migrates when website step is actually available');$saved=$page->wizardStepLabels();\Simplify\Panel\Services\Extensions::$web=false;check(!in_array('Website display',$page->wizardStepLabels(),true),'website step is absent without the helper');check($step->invoke($page,5,4,$saved)===4,'named draft restores backups after removing the web helper');\Simplify\Panel\Services\FeatureSettings::$backups=false;check(!in_array('Backups',$page->wizardStepLabels(),true),'stepper follows disabled backups too');check($step->invoke($page,5,4,$saved)===4,'removed draft stage advances safely to Review');
}
