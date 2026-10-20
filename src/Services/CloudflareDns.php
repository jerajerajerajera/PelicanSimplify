<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
/**
 * Keeps one server's Cloudflare records in line with its subdomain: a CNAME to the shared target and, for
 * Minecraft, an SRV record carrying the port. Records are tagged "pelican:<uuid>"; records without that tag
 * (made by hand or by another tool) are never changed or removed.
 * $send(method, path, ?body): array returns the decoded Cloudflare response, so tests can use a fake.
 */
final class CloudflareDns
{
    /** @param callable(string,string,?array):array $send */
    public function __construct(private string $zoneId,private $send,private string $domain,private string $target) {}
    public static function comment(string $uuid): string { return 'pelican:'.$uuid; }
    public static function http(string $token): callable
    {
        return static function(string $method,string $path,?array $body)use($token): array {
            $request=\Illuminate\Support\Facades\Http::withToken($token)->acceptJson()->timeout(10)->baseUrl('https://api.cloudflare.com/client/v4');
            $response=$method==='GET'?$request->get($path):$request->send($method,$path,$body===null?[]:['json'=>$body]);
            return (array)($response->json()??['success'=>false,'errors'=>[['message'=>'HTTP '.$response->status()]]]);
        };
    }
    /** Records a server should have; none without a subdomain. */
    public function wanted(string $uuid,string $name,int $port,bool $minecraft): array
    {
        if($name==='')return [];
        $comment=self::comment($uuid);$host=$name.'.'.$this->domain;
        $records=[['type'=>'CNAME','name'=>$host,'content'=>$this->target,'proxied'=>false,'ttl'=>1,'comment'=>$comment]];
        // Java clients look up the SRV record, so players can leave out the port.
        if($minecraft)$records[]=['type'=>'SRV','name'=>'_minecraft._tcp.'.$host,'data'=>['priority'=>0,'weight'=>5,'port'=>$port,'target'=>$this->target],'ttl'=>1,'comment'=>$comment];
        return $records;
    }
    /** Changes that turn $owned (records with this server's tag) into $wanted: [operation, record] pairs. */
    public static function diff(array $wanted,array $owned): array
    {
        $ops=[];$byKey=[];
        foreach($owned as $r)$byKey[$r['type'].' '.$r['name']][]=$r;
        foreach($wanted as $w){
            $key=$w['type'].' '.$w['name'];$have=$byKey[$key][0]??null;
            if(!$have){$ops[]=['create',$w];continue;}
            array_shift($byKey[$key]);
            if(!self::same($w,$have))$ops[]=['update',$w+['id'=>$have['id']]];
        }
        foreach($byKey as $rest)foreach($rest as $r)$ops[]=['delete',$r];
        return $ops;
    }
    /** Returns null when done, or a message for the server owner. */
    public function sync(string $uuid,string $name,int $port,bool $minecraft): ?string
    {
        try {
            $wanted=$this->wanted($uuid,$name,$port,$minecraft);
            foreach($wanted as $w)foreach($this->find(['name.exact'=>$w['name']]) as $other)
                if(($other['comment']??'')!==self::comment($uuid))return 'A DNS record for '.$w['name'].' already exists and was not created by this panel.';
            $base='/zones/'.rawurlencode($this->zoneId).'/dns_records';
            foreach(self::diff($wanted,$this->find(['comment.exact'=>self::comment($uuid)])) as [$op,$r]){
                $body=array_diff_key($r,['id'=>true]);
                $result=match($op){'create'=>($this->send)('POST',$base,$body),'update'=>($this->send)('PUT',$base.'/'.rawurlencode($r['id']),$body),'delete'=>($this->send)('DELETE',$base.'/'.rawurlencode($r['id']),null)};
                if(!($result['success']??false))return 'Cloudflare refused to '.$op.' '.$r['name'].': '.self::error($result);
            }
            return null;
        } catch(\Throwable $e) { return 'Cloudflare could not be reached: '.$e->getMessage(); }
    }
    public function remove(string $uuid): ?string { return $this->sync($uuid,'',0,false); }
    private function find(array $query): array
    {
        $result=($this->send)('GET','/zones/'.rawurlencode($this->zoneId).'/dns_records?'.http_build_query($query+['per_page'=>100]),null);
        if(!($result['success']??false))throw new \RuntimeException(self::error($result));
        return array_values(array_filter((array)($result['result']??[]),'is_array'));
    }
    private static function error(array $result): string { return (string)($result['errors'][0]['message']??'unknown error'); }
    private static function same(array $w,array $h): bool
    {
        if($w['type']==='SRV')return array_intersect_key((array)($h['data']??[]),$w['data'])==$w['data'];
        return strcasecmp((string)($h['content']??''),$w['content'])===0&&($h['proxied']??false)===false;
    }
}
