<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Storage;
/**
 * What kind of panel this is, asked first in the setup: a panel for yourself and friends, or a hosting company with
 * customers. The type picks sensible defaults for the rest of the setup (which the admin can still change there) and,
 * for hosting, holds the links to the billing system and support that customers see.
 */
final class PanelProfile
{
    public const FILE='simplify/panel-profile.json';
    public const TYPES=['friends'=>'Me and my friends','hosting'=>'Hosting company'];
    /** Links customers see; upgrade may hold {uuid}, {short_id}, {server_id} and {external_id} for the server it is opened from. */
    public const LINKS=['client_area','order','upgrade','support'];
    private const DEFAULTS=['type'=>'friends','client_area'=>'','order'=>'','upgrade'=>'','support'=>'','support_label'=>''];
    private const PLACEHOLDERS=['{uuid}','{short_id}','{server_id}','{external_id}'];

    /**
     * Defaults per type for the switches asked later in the setup. A hosting company's customers only see their own
     * servers, do not change resources or create servers outside the billing system, and updates wait for the admin.
     */
    public const FEATURES=[
        'friends'=>['friends_mode'=>true,'user_resources'=>true,'host_mode'=>false,'auto_update_plugins'=>true],
        'hosting'=>['friends_mode'=>false,'user_resources'=>false,'host_mode'=>true,'auto_update_plugins'=>false],
    ];

    public static function settings(): array
    {
        return self::normalize(JsonFile::read(self::FILE));
    }
    public static function type(): string
    {
        return self::settings()['type'];
    }
    public static function hosting(): bool
    {
        return self::type()==='hosting';
    }
    public static function normalize(array $input): array
    {
        $out=self::DEFAULTS;
        if(isset(self::TYPES[$input['type']??null]))$out['type']=$input['type'];
        foreach(self::LINKS as $key)$out[$key]=self::url($input[$key]??null,$key==='upgrade')??'';
        $label=is_string($input['support_label']??null)?trim(preg_replace('/[\x00-\x1F\x7F]+/u','',$input['support_label'])??''):'';
        $out['support_label']=mb_substr($label,0,40);
        return $out;
    }
    public static function save(array $input): array
    {
        $next=self::normalize([...self::settings(),...$input]);
        if(!Storage::disk('local')->put(self::FILE,json_encode($next,JSON_THROW_ON_ERROR)))throw new \RuntimeException('Could not save the panel type.');
        return $next;
    }
    /** Only http(s) links; a template keeps its {placeholders}, which are checked as if filled in. */
    public static function url(mixed $value,bool $template=false): ?string
    {
        if(!is_string($value))return null;
        $value=trim($value);
        if($value===''||mb_strlen($value)>500)return null;
        $check=$template?str_replace(self::PLACEHOLDERS,'1',$value):$value;
        if(!preg_match('#^https?://#i',$check)||filter_var($check,FILTER_VALIDATE_URL)===false)return null;
        return $value;
    }
    /** What customers of a hosting company see (account menu, Explain, Order); null on a panel for friends. */
    public static function customerLinks(): ?array
    {
        $s=self::settings();
        if($s['type']!=='hosting')return null;
        return ['client_area'=>$s['client_area'],'order'=>$s['order'],'support'=>$s['support'],'support_label'=>$s['support_label']!==''?$s['support_label']:__('pelican-simplify::settings.hosting.open_a_ticket')];
    }
    /** The upgrade link for one server, or null when there is none. */
    public static function upgradeUrl(object $server): ?string
    {
        $template=self::settings()['upgrade'];
        if($template==='')return null;
        return str_replace(self::PLACEHOLDERS,array_map('rawurlencode',[(string)$server->uuid,(string)$server->uuid_short,(string)$server->id,(string)($server->external_id??'')]),$template);
    }
}
