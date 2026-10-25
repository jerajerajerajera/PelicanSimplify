<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
/**
 * Web Push (RFC 8030) for the installed web app, without extra packages: the panel's own VAPID key pair (RFC 8292),
 * the message encrypted for the browser with aes128gcm (RFC 8291), all with PHP's openssl. The push services of
 * Chrome, Edge, Firefox and Safari deliver it; nothing is sent to anyone else.
 */
final class WebPush
{
    /** DER prefix of a P-256 public key (SubjectPublicKeyInfo) in front of the 65-byte uncompressed point. */
    private const P256_PREFIX='3059301306072a8648ce3d020106082a8648ce3d030107034200';
    private const RECORD_SIZE=4096;
    /** Push services of the browsers; subscriptions to any other host are refused, so the panel never posts elsewhere. */
    private const HOSTS=['fcm.googleapis.com','android.googleapis.com','updates.push.services.mozilla.com','push.services.mozilla.com','web.push.apple.com','notify.windows.com','push.apple.com'];

    /** A new VAPID key pair: the private key as PEM, the public key as base64url of the uncompressed point. */
    public static function createKeys(): array
    {
        $key=openssl_pkey_new(['curve_name'=>'prime256v1','private_key_type'=>OPENSSL_KEYTYPE_EC]);
        if($key===false||!openssl_pkey_export($key,$pem))throw new \RuntimeException('This PHP cannot create the keys for app notifications (openssl with P-256 is needed).');
        return ['private'=>$pem,'public'=>self::b64(self::point($key))];
    }

    /** True for an https endpoint of a browser push service. */
    public static function endpointAllowed(string $endpoint): bool
    {
        if(strlen($endpoint)>2048||!str_starts_with($endpoint,'https://'))return false;
        $host=strtolower((string)parse_url($endpoint,PHP_URL_HOST));
        if($host==='')return false;
        foreach(self::HOSTS as $allowed)if($host===$allowed||str_ends_with($host,'.'.$allowed))return true;
        return false;
    }

    /** True when a subscription's browser keys have the right sizes (65-byte P-256 point, 16-byte secret). */
    public static function keysValid(string $p256dh,string $auth): bool
    {
        $point=self::unb64($p256dh);
        return strlen($point)===65&&$point[0]==="\x04"&&strlen(self::unb64($auth))===16;
    }

    /**
     * The request for one message: url, headers and encrypted body. $subject is a mailto: or https: contact for the
     * push service. The message reaches the browser within $ttl seconds or not at all.
     */
    public static function request(array $subscription,string $payload,array $keys,string $subject,int $ttl=86400,string $urgency='normal',?int $now=null): array
    {
        $endpoint=(string)$subscription['endpoint'];
        if(!self::endpointAllowed($endpoint))throw new \InvalidArgumentException('This push service is not allowed.');
        $parts=parse_url($endpoint);
        $audience=$parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');
        $jwt=self::jwt(['aud'=>$audience,'exp'=>($now??time())+12*3600,'sub'=>$subject],$keys['private']);
        return ['url'=>$endpoint,'body'=>self::encrypt($payload,(string)$subscription['p256dh'],(string)$subscription['auth']),'headers'=>[
            'Authorization'=>'vapid t='.$jwt.', k='.$keys['public'],'Content-Encoding'=>'aes128gcm','Content-Type'=>'application/octet-stream',
            'TTL'=>(string)max(0,$ttl),'Urgency'=>in_array($urgency,['very-low','low','normal','high'],true)?$urgency:'normal',
        ]];
    }

    /** aes128gcm content for the browser with these keys (RFC 8291): one record, salt and sender key in the header. */
    public static function encrypt(string $payload,string $p256dh,string $auth,?string $salt=null,$senderKey=null): string
    {
        $receiver=self::unb64($p256dh);$secret=self::unb64($auth);
        if(!self::keysValid($p256dh,$auth))throw new \InvalidArgumentException('The browser keys of this subscription are invalid.');
        if(strlen($payload)>self::RECORD_SIZE-17-86)throw new \InvalidArgumentException('The message is too long for a push notification.');
        $sender=$senderKey??openssl_pkey_new(['curve_name'=>'prime256v1','private_key_type'=>OPENSSL_KEYTYPE_EC]);
        $senderPublic=self::point($sender);
        $shared=openssl_pkey_derive(self::publicKey($receiver),$sender,32);
        if($shared===false)throw new \RuntimeException('Could not encrypt the push notification.');
        $salt??=random_bytes(16);
        $ikm=self::hkdf($secret,$shared,"WebPush: info\0".$receiver.$senderPublic,32);
        $cek=self::hkdf($salt,$ikm,"Content-Encoding: aes128gcm\0",16);
        $nonce=self::hkdf($salt,$ikm,"Content-Encoding: nonce\0",12);
        // One record, so it ends with the 0x02 padding delimiter of the last record.
        $cipher=openssl_encrypt($payload."\x02",'aes-128-gcm',$cek,OPENSSL_RAW_DATA,$nonce,$tag);
        if($cipher===false)throw new \RuntimeException('Could not encrypt the push notification.');
        return $salt.pack('N',self::RECORD_SIZE).chr(65).$senderPublic.$cipher.$tag;
    }

    /** A signed ES256 JSON Web Token (the VAPID header). */
    public static function jwt(array $claims,string $privatePem): string
    {
        $input=self::b64(json_encode(['typ'=>'JWT','alg'=>'ES256'],JSON_THROW_ON_ERROR)).'.'.self::b64(json_encode($claims,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
        if(!openssl_sign($input,$der,$privatePem,OPENSSL_ALGO_SHA256))throw new \RuntimeException('Could not sign the push notification.');
        return $input.'.'.self::b64(self::rawSignature($der));
    }

    /** openssl's DER signature (SEQUENCE of two INTEGERs) as the 64-byte r||s that JWT wants. */
    private static function rawSignature(string $der): string
    {
        $offset=2+(ord($der[1])&0x80?ord($der[1])&0x7f:0);
        $raw='';
        for($i=0;$i<2;$i++){
            $length=ord($der[$offset+1]);
            $raw.=str_pad(ltrim(substr($der,$offset+2,$length),"\0"),32,"\0",STR_PAD_LEFT);
            $offset+=2+$length;
        }
        return $raw;
    }

    /** HKDF-SHA-256 for outputs up to 32 bytes (one expand round). */
    private static function hkdf(string $salt,string $ikm,string $info,int $length): string
    {
        return substr(hash_hmac('sha256',$info."\x01",hash_hmac('sha256',$ikm,$salt,true),true),0,$length);
    }

    /** The 65-byte uncompressed public point of an EC key. */
    private static function point($key): string
    {
        $details=openssl_pkey_get_details($key);
        if(!is_array($details)||!isset($details['ec']['x'],$details['ec']['y']))throw new \RuntimeException('Not a P-256 key.');
        return "\x04".str_pad($details['ec']['x'],32,"\0",STR_PAD_LEFT).str_pad($details['ec']['y'],32,"\0",STR_PAD_LEFT);
    }

    private static function publicKey(string $point)
    {
        $pem="-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin(self::P256_PREFIX).$point),64,"\n")."-----END PUBLIC KEY-----\n";
        $key=openssl_pkey_get_public($pem);
        if($key===false)throw new \InvalidArgumentException('The browser key of this subscription is invalid.');
        return $key;
    }

    public static function b64(string $bytes): string { return rtrim(strtr(base64_encode($bytes),'+/','-_'),'='); }

    public static function unb64(string $text): string { return (string)base64_decode(strtr($text,'-_','+/'),true); }
}
