<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Support\Facades\Storage;
final class ServerArtwork
{
    public const BACKGROUNDS=['valley'=>'minecraft-valley.jpg','harbor'=>'minecraft-harbor.jpg','village'=>'minecraft-village.jpg','survival'=>'preview-world.jpg','town'=>'preview-modpack.jpg','gaming'=>'gaming.svg'];
    /** A valid 64×64 server-icon.png as ['png' => base64, 'hash' => short hash], cached (misses too). */
    public static function iconFile(Server $server): ?array
    {
        if(!ManagedSettings::minecraft($server) || !$server->installed_at)return null;
        return Remember::value('simplify.server-icon.v2.'.$server->uuid,900,function()use($server):?array {
            // The overview exposes this one public image, never general file access.
            $bytes=app(DaemonFileRepository::class)->setServer($server)->getContent('/server-icon.png',102400);
            if(strlen($bytes)>102400 || strlen($bytes)<24 || substr($bytes,0,8)!=="\x89PNG\r\n\x1a\n" || substr($bytes,12,4)!=='IHDR')return null;
            $size=unpack('Nwidth/Nheight',substr($bytes,16,8));
            if($size['width']!==64 || $size['height']!==64)return null;
            return ['png'=>base64_encode($bytes),'hash'=>substr(hash('sha256',$bytes),0,12)];
        },300);
    }
    /** PNG bytes for the icon route: the server-icon.png file, else the favicon from the last status ping. */
    public static function iconBytes(Server $server): ?string
    {
        $file=self::iconFile($server);
        if($file)return base64_decode($file['png'],true)?:null;
        $favicon=MinecraftStatus::favicon($server);
        if($favicon)return base64_decode(substr($favicon,22),true)?:null;
        return SteamArt::iconBytes($server);
    }
    /** Cacheable URL of the server icon; the browser downloads it once per change instead of every refresh. */
    public static function serverIcon(Server $server,array $status=[]): ?string
    {
        $hash=self::iconFile($server)['hash']??null;
        if(!$hash && ($favicon=MinecraftStatus::favicon($server)))$hash=substr(hash('sha256',$favicon),0,12);
        if(!$hash && ($game=SteamArt::iconBytes($server)))$hash=substr(hash('sha256',$game),0,12);
        return $hash?route('simplify.server-icon',['uuid'=>$server->uuid]).'?v='.$hash:null;
    }
    public static function softwareIcon(Server $server,array $meta=[]): ?string
    {
        return ($meta['modpack_icon_url']??null) ?: ($server->egg?->icon ?: null);
    }
    public static function icon(Server $server,array $meta=[]): ?string
    {
        return self::serverIcon($server) ?: ($meta['modpack_icon_url']??null) ?: ($meta['icon_url']??null) ?: ($server->egg?->icon ?: null);
    }
    public static function background(Server $server,array $meta): ?string
    {
        $selection = self::selection($server);
        if ($selection !== null) $meta = ['background_id' => $selection];
        if (($meta['background_id'] ?? '') === 'none' || self::isColor((string) ($meta['background_id'] ?? ''))) return null;
        if(!empty($meta['screenshot_url']))return $meta['screenshot_url'];
        $id=$meta['background_id']??'auto';
        if (str_starts_with($id,'upload-')) {
            if (ServerBanners::belongsTo($id,$server)) return route('simplify.banner',['id'=>$id]);
            $id='auto'; // Deleted uploads fall back to bundled artwork.
        }
        if($id==='auto'){
            $id=JsonFile::read('server-directory/backgrounds/'.$server->uuid.'.json')['background_id']??'';
            if(!isset(self::BACKGROUNDS[$id]))$id=Extensions::webAdapter()?->backgroundFor($server)??'';
            if (!isset(self::BACKGROUNDS[$id]) && ManagedSettings::minecraft($server)) {
                // Stable per server, including servers created before Simplify was installed.
                // Preserve existing/manual selections and require no website extension or storage writes.
                $choices = array_values(array_diff(array_keys(self::BACKGROUNDS),['gaming']));
                $id = $choices[hexdec(substr(hash('sha256', $server->uuid), 0, 8)) % count($choices)];
            }
            if(!isset(self::BACKGROUNDS[$id]) && !ManagedSettings::minecraft($server))$id='gaming';
        }
        return isset(self::BACKGROUNDS[$id])?self::backgroundUrl($id):null;
    }
    /** A colour banner instead of an image: "color" picks one from the accent, "color:#rrggbb" is chosen. */
    public static function isColor(string $id): bool
    {
        return $id === 'color' || (bool) preg_match('/^color:#[0-9a-f]{6}$/', $id);
    }
    public static function backgroundUrl(string $id): string
    {
        return route('simplify.background',['id'=>$id]).'?v='.BuildInfo::hash('core.css');
    }
    public static function selection(Server $server): ?string
    {
        $id = JsonFile::read('simplify/backgrounds/'.$server->uuid.'.json')['background_id'] ?? null;
        return is_string($id) && ($id === 'auto' || $id === 'none' || self::isColor($id) || isset(self::BACKGROUNDS[$id]) || ServerBanners::belongsTo($id,$server)) ? $id : null;
    }
    /** Selects a banner without a permission check (creator only; save() is the user-facing path). */
    public static function select(Server $server, string $id): void
    {
        if (!ServerBanners::belongsTo($id, $server)) return;
        Storage::disk('local')->put('simplify/backgrounds/'.$server->uuid.'.json', json_encode(['background_id' => $id], JSON_THROW_ON_ERROR));
    }
    public static function save(Server $server, string $id): array
    {
        Access::permission($server, \App\Enums\SubuserPermission::SettingsRename);
        $id = strtolower($id);
        abort_unless($id === 'auto' || $id === 'none' || self::isColor($id) || isset(self::BACKGROUNDS[$id]) || ServerBanners::belongsTo($id,$server), 422);
        if (!Storage::disk('local')->put('simplify/backgrounds/'.$server->uuid.'.json', json_encode(['background_id' => $id], JSON_THROW_ON_ERROR))) {
            throw new \RuntimeException('Could not save server background.');
        }
        return ['background_id' => $id, 'background' => self::background($server, [])];
    }
}
