<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
final class AppearanceSettings
{
    public const FILE='simplify/appearance.json';
    private ?array $loaded=null;
    public static function defaults(): array { return ['accent_color'=>'#878787','individual_colors'=>true,'seasonal'=>true,'brand'=>BrandSettings::defaults()]; }
    public static function all(): array { return app(self::class)->load(); }
    private function load(): array
    {
        if($this->loaded!==null)return $this->loaded;
        $saved=JsonFile::read(self::FILE);
        return $this->loaded=['accent_color'=>is_string($saved['accent_color']??null)&&preg_match('/^#[0-9a-fA-F]{6}$/',$saved['accent_color'])?strtolower($saved['accent_color']):self::defaults()['accent_color'],'individual_colors'=>array_key_exists('individual_colors',$saved)?$saved['individual_colors']===true:true,'seasonal'=>($saved['seasonal']??true)!==false,'brand'=>BrandSettings::normalize($saved['brand']??[])];
    }
    public static function save(array $input): void
    {
        abort_unless(user()?->isRootAdmin(),403);
        $data=validator($input,['accent_color'=>'required|regex:/^#[0-9a-fA-F]{6}$/','individual_colors'=>'required|boolean','seasonal'=>'sometimes|boolean'])->validate();
        $current=self::all()['brand']['logo']??null;
        $brand=isset($input['brand'])?validator((array)$input['brand'],BrandSettings::rules())->validate():self::all()['brand'];
        $next=['accent_color'=>strtolower($data['accent_color']),'individual_colors'=>(bool)$data['individual_colors'],'seasonal'=>array_key_exists('seasonal',$data)?(bool)$data['seasonal']:self::all()['seasonal'],'brand'=>BrandSettings::normalize([...$brand,'logo'=>$current],$current)];
        Cache::lock('simplify.appearance',10)->block(3,function()use($next):void{
            if ($next['individual_colors']) ServerAppearance::assignMissingColors();
            if(!Storage::disk('local')->put(self::FILE,json_encode($next,JSON_THROW_ON_ERROR)))throw new \RuntimeException('Could not save appearance settings.');
            app(self::class)->loaded=$next;
        });
    }
    /** Replaces (or with null removes) the uploaded logo; the old file is deleted. */
    public static function setLogo(?string $name): void
    {
        abort_unless(user()?->isRootAdmin(),403);
        Cache::lock('simplify.appearance',10)->block(3,function()use($name):void{
            $all=self::all();
            $old=$all['brand']['logo'];
            $all['brand']=BrandSettings::normalize([...$all['brand'],'mode'=>$name?'logo':'text'],$name??'');
            if(!Storage::disk('local')->put(self::FILE,json_encode($all,JSON_THROW_ON_ERROR)))throw new \RuntimeException('Could not save appearance settings.');
            app(self::class)->loaded=$all;
            if($old!==$name)BrandSettings::deleteLogoFile($old);
        });
    }
}
