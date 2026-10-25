<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Server;
use App\Enums\SubuserPermission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
final class ServerBanners
{
    private const ROOT = 'simplify/banners/';
    public static function validId(string $id): bool { return (bool) preg_match('/^upload-[a-f0-9]{32}$/D', $id); }
    public static function metadata(string $id): ?array
    {
        if (!self::validId($id)) return null;
        $data=JsonFile::read(self::ROOT.$id.'.json',null);
        return $data && ($data['id']??null)===$id && is_string($data['server_uuid']??null)
            && preg_match('/^[a-z0-9-]{1,64}$/D',$data['server_uuid']) && is_string($data['name']??null)
            && in_array($data['extension']??null,['png','jpg','webp'],true)
            && Storage::disk('local')->exists(self::ROOT.$id.'.'.$data['extension']) ? $data : null;
    }
    public static function belongsTo(string $id, Server $server): bool { $data=self::metadata($id);return $data && $data['server_uuid']===$server->uuid; }
    public static function entries(?Server $server=null): array
    {
        $items=[];
        foreach(Storage::disk('local')->files(rtrim(self::ROOT,'/')) as $path){
            if(!str_ends_with($path,'.json'))continue;
            $data=self::metadata(basename($path,'.json'));
            if($data && (!$server || $data['server_uuid']===$server->uuid))$items[]=[...$data,'url'=>route('simplify.banner',['id'=>$data['id']])];
        }
        return $items;
    }
    public static function validateBytes(string $bytes): string
    {
        $size=@getimagesizefromstring($bytes);
        $extension=match($size[2]??null){IMAGETYPE_PNG=>'png',IMAGETYPE_JPEG=>'jpg',IMAGETYPE_WEBP=>'webp',default=>null};
        if(strlen($bytes)>UploadLimits::bannerBytes() || !$extension || $size[0]<64 || $size[1]<32 || $size[0]>8192 || $size[1]>8192 || $size[0]*$size[1]>32000000){
            throw ValidationException::withMessages(['bannerUpload'=>'Choose a JPG, PNG or WebP image up to '.UploadLimits::label(UploadLimits::bannerBytes()).', between 64×32 and 8192×8192 pixels (maximum 32 megapixels).']);
        }
        return $extension;
    }
    public static function upload(Server $server, $file): array
    {
        Access::permission($server,SubuserPermission::SettingsRename);
        if(!is_object($file) || !method_exists($file,'getRealPath') || $file->getSize()>UploadLimits::bannerBytes())throw ValidationException::withMessages(['bannerUpload'=>'Choose an image up to '.UploadLimits::label(UploadLimits::bannerBytes()).'.']);
        $bytes=file_get_contents($file->getRealPath());
        if($bytes===false)throw new \RuntimeException('Could not read banner upload.');
        $extension=self::validateBytes($bytes);$id='upload-'.bin2hex(random_bytes(16));
        $name=mb_substr(pathinfo(basename($file->getClientOriginalName()),PATHINFO_FILENAME),0,100) ?: 'Custom banner';
        $data=['id'=>$id,'name'=>$name,'server_uuid'=>$server->uuid,'extension'=>$extension];$disk=Storage::disk('local');
        if(!$disk->put(self::ROOT.$id.'.'.$extension,$bytes))throw new \RuntimeException('Could not save banner image.');
        if(!$disk->put(self::ROOT.$id.'.json',json_encode($data,JSON_THROW_ON_ERROR))){$disk->delete(self::ROOT.$id.'.'.$extension);throw new \RuntimeException('Could not save banner details.');}
        return $data;
    }
    /** Stores image bytes as a banner of this server (used by the creator; callers check permission). */
    public static function store(Server $server, string $bytes, string $name): array
    {
        $extension=self::validateBytes($bytes);$id='upload-'.bin2hex(random_bytes(16));
        $data=['id'=>$id,'name'=>mb_substr($name,0,100) ?: 'Banner','server_uuid'=>$server->uuid,'extension'=>$extension];$disk=Storage::disk('local');
        if(!$disk->put(self::ROOT.$id.'.'.$extension,$bytes))throw new \RuntimeException('Could not save banner image.');
        if(!$disk->put(self::ROOT.$id.'.json',json_encode($data,JSON_THROW_ON_ERROR))){$disk->delete(self::ROOT.$id.'.'.$extension);throw new \RuntimeException('Could not save banner details.');}
        return $data;
    }
    public static function delete(string $id, ?Server $server=null): void
    {
        if($server)Access::permission($server,SubuserPermission::SettingsRename);else abort_unless(user()?->isRootAdmin(),403);
        $data=self::metadata($id);abort_unless($data && (!$server || $data['server_uuid']===$server->uuid),404);
        $selection='simplify/backgrounds/'.$data['server_uuid'].'.json';$disk=Storage::disk('local');
        if((JsonFile::read($selection)['background_id']??null)===$id){
            if(!$disk->put($selection,json_encode(['background_id'=>'auto'],JSON_THROW_ON_ERROR)))throw new \RuntimeException('Could not reset banner selection.');
        }
        // Removing custom artwork never removes bundled banners or running-server files.
        if(!Storage::disk('local')->delete([self::ROOT.$id.'.json',self::ROOT.$id.'.'.$data['extension']]))throw new \RuntimeException('Could not remove banner.');
    }
    public static function path(string $id): ?string
    {
        $data=self::metadata($id);return $data?Storage::disk('local')->path(self::ROOT.$id.'.'.$data['extension']):null;
    }
}
