<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
final class UploadLimits
{
    /** PHP shorthand values use binary units; zero means unlimited for post_max_size. */
    public static function bytes(string $value): int
    {
        $value=trim($value);
        if(!preg_match('/^(-?\d+(?:\.\d+)?)\s*([KMGTP]?)B?$/iD',$value,$match))return 0;
        $power=$match[2]===''?0:strpos('KMGTP',strtoupper($match[2]))+1;
        return max(0,(int)((float)$match[1]*1024**$power));
    }
    public static function calculate(string $upload,string $post,mixed $rules,bool $enabled=true): array
    {
        $uploadBytes=self::bytes($upload);$postBytes=self::bytes($post);$limits=[];
        if($uploadBytes>0)$limits[]=$uploadBytes;
        // Allow room for multipart boundaries and the Livewire request metadata.
        if($postBytes>0)$limits[]=max(0,$postBytes-64*1024);
        $rules=is_array($rules)?$rules:explode('|',(string)$rules);
        foreach($rules as $rule)if(is_string($rule)&&preg_match('/^max:(\d+)$/D',$rule,$m))$limits[]=(int)$m[1]*1024;
        $bytes=$enabled?($limits?min($limits):PHP_INT_MAX):0;
        return ['upload'=>$upload,'post'=>$post,'bytes'=>$bytes,'banner_bytes'=>min(5*1024*1024,$bytes),'enabled'=>$enabled];
    }
    public static function current(): array
    {
        $rules=function_exists('config')?config('livewire.temporary_file_upload.rules'):null;
        // Livewire's default rule applies only when no custom rules are configured.
        return self::calculate((string)ini_get('upload_max_filesize'),(string)ini_get('post_max_size'),$rules??'file|max:12288',filter_var(ini_get('file_uploads'),FILTER_VALIDATE_BOOLEAN));
    }
    public static function bannerBytes(): int { return self::current()['banner_bytes']; }
    public static function label(int $bytes): string { return rtrim(rtrim(number_format($bytes/1048576,2,'.',''),'0'),'.').' MB'; }
    public static function checks(): array
    {
        $limits=self::current();$t=static fn(string $key,string $english,array $replace=[]):string=>Translations::text('checks.uploads.'.$key,$english,$replace);
        $runtime=PHP_SAPI==='cli'?$t('runtime_cli','CLI PHP (verify PHP-FPM separately)'):$t('runtime_web','Web PHP');
        $enough=$limits['banner_bytes']>=5*1048576;
        return [
            ['id'=>'upload-capacity','name'=>$t('capacity','Upload capacity · :size for banners',['size'=>self::label($limits['banner_bytes'])]),'ok'=>$enough,'blocking'=>false,'group'=>'Upload capacity',
             'detail'=>$t('capacity_detail',':runtime: upload_max_filesize=:upload; post_max_size=:post. Banner uploads are capped at the lower PHP/Livewire limit (maximum 5 MB).',['runtime'=>$runtime,'upload'=>$limits['upload'],'post'=>$limits['post']]),
             'fix'=>$enough?'':$t('capacity_fix','To allow 5 MB banners, set PHP-FPM upload_max_filesize = 10M and post_max_size = 12M, check any Livewire max rule, then reload PHP-FPM.')],
            ['id'=>'proxy-upload-limit','name'=>$t('proxy','NGINX / proxy upload limit · not verified'),'ok'=>false,'blocking'=>false,'group'=>'Upload capacity',
             'detail'=>$t('proxy_detail','A proxy may reject uploads before PHP receives them (HTTP 413), even when the checks above pass.'),
             'fix'=>$t('proxy_fix','In the NGINX server block serving this panel, set client_max_body_size 12m; check for lower location overrides and upstream proxy limits. Run sudo nginx -t, then sudo systemctl reload nginx. Verify with a real upload. No server configuration is changed automatically.')],
        ];
    }
}
