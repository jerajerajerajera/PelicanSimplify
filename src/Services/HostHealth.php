<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Node;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
final class HostHealth
{
    /** One snapshot shared by every viewer for the configured cache time. */
    public static function snapshot(): array
    {
        return Remember::value('simplify.host-health',(int)PerformanceSettings::get('health_seconds'),fn():array=>self::collect(),15) ?? self::summarize([]);
    }
    /** The refresh button: drops the shared snapshot unless it is only a few seconds old, so clicks cannot flood Wings. */
    public static function refresh(): array
    {
        $stored=Cache::get('simplify.host-health');
        $at=is_array($stored)&&is_array($stored['value']??null)?(int)($stored['value']['checked_at']??0):0;
        if(time()-$at>=self::REFRESH_GAP)Remember::forget('simplify.host-health');
        return self::snapshot();
    }
    private const REFRESH_GAP=5;
    private static function collect(): array
    {
        $samples=[];$nodes=[];$rx=0.0;$tx=0.0;$networkAvailable=true;$rxRate=0.0;$txRate=0.0;$ratesAvailable=true;
        foreach(Node::query()->get() as $index=>$node){
            try{$maintenance=$node->isUnderMaintenance();}catch(\Throwable){$maintenance=false;}
            [$stats,$problem]=self::statistics($node);
            $sample=['maintenance'=>$maintenance,'stats'=>$stats];
            $samples[]=$sample;
            $traffic=Remember::value('simplify.node-network.v2.'.($node->id??$index),15,fn()=>self::traffic($node),60);
            $health=self::summarize([$sample]);
            $health['network_rx_bytes']=$traffic['rx_bytes']??null;$health['network_tx_bytes']=$traffic['tx_bytes']??null;
            $health['network_rx_per_second']=$traffic['rx_per_second']??null;$health['network_tx_per_second']=$traffic['tx_per_second']??null;
            if($health['network_rx_per_second']===null||$health['network_tx_per_second']===null)$ratesAvailable=false;else{$rxRate+=$health['network_rx_per_second'];$txRate+=$health['network_tx_per_second'];}
            $health['metric_states']['network']=$traffic!==null?'healthy':'unavailable';
            $health['problem']=$problem;
            // Publish names and utilization only, never connection addresses or daemon credentials.
            $nodes[]=['id'=>$node->id??$index,'name'=>$node->name??'Node '.($index+1),...$health];
            if($traffic===null)$networkAvailable=false;else{$rx+=$traffic['rx_bytes'];$tx+=$traffic['tx_bytes'];}
        }
        $result=self::summarize($samples);
        $result['nodes']=$nodes;$result['checked_at']=time();
        $failing=array_values(array_filter($nodes,fn(array $n):bool=>$n['problem']!==null));
        $result['problem']=match(count($failing)){0=>null,1=>count($nodes)>1?$failing[0]['name'].': '.$failing[0]['problem']:$failing[0]['problem'],default=>count($failing).' nodes did not report'};
        $result['network_rx_bytes']=$networkAvailable?$rx:null;$result['network_tx_bytes']=$networkAvailable?$tx:null;
        $result['network_rx_per_second']=$ratesAvailable&&$result['hosts']>0?$rxRate:null;$result['network_tx_per_second']=$ratesAvailable&&$result['hosts']>0?$txRate:null;
        $result['metric_states']['network']=$networkAvailable&&$result['hosts']>0?'healthy':'unavailable';
        return $result;
    }
    /**
     * Wings' system utilization, with a plain reason when it fails. Pelican's own Node::statistics() gives up after
     * one second, which a node behind a tunnel or proxy can miss now and then; the shared cache keeps this to one
     * request per node per cache period.
     * @return array{0: array, 1: ?string}
     */
    private static function statistics(Node $node): array
    {
        try {
            $response=Http::daemon($node)->connectTimeout(3)->timeout(5)->get('/api/system/utilization');
        } catch (ConnectionException $e) {
            return [[], str_contains(strtolower($e->getMessage()),'timed out')?'Wings did not answer in time':'Could not connect to Wings'];
        } catch (\Throwable) {
            return [[], 'Could not connect to Wings'];
        }
        if (!$response->successful()) return [[], self::httpProblem($response->status())];
        $data=$response->json();
        return is_array($data)&&!empty($data['memory_total'])?[$data,null]:[[], 'Wings sent no usable statistics'];
    }
    public static function httpProblem(int $status): string
    {
        return match(true){
            $status===401||$status===403=>"Wings refused the request (HTTP $status)",
            $status>=500=>"Wings is unreachable (HTTP $status)",
            default=>"Wings answered with HTTP $status",
        };
    }
    private static function traffic(Node $node): ?array
    {
        // Wings exposes container traffic, not the physical host interface capacity. The node list is shared with the overview.
        try {
            $snapshot=WingsServers::forNode($node);
            if($snapshot===null)return null;
            $rx=0.0;$tx=0.0;$counters=[];
            foreach($snapshot['servers'] as $uuid=>$server){
                $network=$server['utilization']['network']??[];
                foreach(['rx_bytes','tx_bytes'] as $key)if(!is_numeric($network[$key]??null)||!is_finite((float)$network[$key])||(float)$network[$key]<0)return null;
                $rx+=(float)$network['rx_bytes'];$tx+=(float)$network['tx_bytes'];
                $counters[$uuid]=['rx_bytes'=>(float)$network['rx_bytes'],'tx_bytes'=>(float)$network['tx_bytes']];
            }
            return ['rx_bytes'=>$rx,'tx_bytes'=>$tx,...NetworkRates::sample('node-'.$node->id,$counters,(float)$snapshot['at'])];
        }catch(\Throwable){return null;}
    }
    public static function summarize(array $samples): array
    {
        $result=['hosts'=>count($samples),'available'=>0,'cpu_percent'=>null,'memory_used'=>null,'memory_total'=>null,'disk_used'=>null,'disk_total'=>null,'state'=>'unavailable','label'=>'Unavailable','metric_states'=>['cpu'=>'unavailable','memory'=>'unavailable','disk'=>'unavailable'],'problem'=>null];
        $levels=['cpu'=>0,'memory'=>0,'disk'=>0];$cpu=0.0;$memoryUsed=0.0;$memoryTotal=0.0;$diskUsed=0.0;$diskTotal=0.0;$maintenance=false;$critical=false;$warning=false;
        $number=static fn($v):bool=>is_numeric($v)&&is_finite((float)$v)&&(float)$v>=0;
        foreach($samples as $sample){
            $s=$sample['stats']??[];$maintenance=$maintenance||($sample['maintenance']??false);
            if(!$number($s['memory_total']??null)||(float)$s['memory_total']<=0||!$number($s['memory_used']??null)||!$number($s['disk_total']??null)||(float)$s['disk_total']<=0||!$number($s['disk_used']??null)||!$number($s['cpu_percent']??null))continue;
            $result['available']++;$cpu+=(float)$s['cpu_percent'];$memoryUsed+=(float)$s['memory_used'];$memoryTotal+=(float)$s['memory_total'];$diskUsed+=(float)$s['disk_used'];$diskTotal+=(float)$s['disk_total'];
            $ram=(float)$s['memory_used']/(float)$s['memory_total']*100;$disk=(float)$s['disk_used']/(float)$s['disk_total']*100;
            $values=['cpu'=>(float)$s['cpu_percent'],'memory'=>$ram,'disk'=>$disk];foreach($values as $key=>$value)$levels[$key]=max($levels[$key],$value>=95?2:($value>=85?1:0));
            $critical=$critical||(float)$s['cpu_percent']>=95||$ram>=95||$disk>=95;
            $warning=$warning||(float)$s['cpu_percent']>=85||$ram>=85||$disk>=85;
        }
        if(!$result['available'])return $result;
        $result=array_replace($result,['cpu_percent'=>$cpu/$result['available'],'memory_used'=>$memoryUsed,'memory_total'=>$memoryTotal,'disk_used'=>$diskUsed,'disk_total'=>$diskTotal]);
        foreach($levels as $key=>$level)$result['metric_states'][$key]=['healthy','warning','critical'][$level];
        if($result['available']<$result['hosts']){foreach($result['metric_states'] as $key=>$state)if($state==='healthy')$result['metric_states'][$key]='unavailable';}
        if($result['available']<$result['hosts'])return array_replace($result,['state'=>'unavailable','label'=>'Partly unavailable']);
        if($critical)return array_replace($result,['state'=>'critical','label'=>'Critical load']);
        if($maintenance)return array_replace($result,['state'=>'warning','label'=>'Maintenance']);
        return array_replace($result,['state'=>$warning?'warning':'healthy','label'=>$warning?'High load':'Healthy']);
    }
}
