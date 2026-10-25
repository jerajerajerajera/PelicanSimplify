<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Node;
final class HostHealth
{
    /** One snapshot shared by every viewer for the configured cache time. */
    public static function snapshot(): array
    {
        return Remember::value('simplify.host-health',(int)PerformanceSettings::get('health_seconds'),fn():array=>self::collect(),15) ?? self::summarize([]);
    }
    private static function collect(): array
    {
        $samples=[];$nodes=[];$rx=0.0;$tx=0.0;$networkAvailable=true;$rxRate=0.0;$txRate=0.0;$ratesAvailable=true;
        foreach(Node::query()->get() as $index=>$node){
            try{$sample=['maintenance'=>$node->isUnderMaintenance(),'stats'=>$node->statistics()];}
            catch(\Throwable){$sample=['maintenance'=>false,'stats'=>[]];}
            $samples[]=$sample;
            $traffic=Remember::value('simplify.node-network.v2.'.($node->id??$index),15,fn()=>self::traffic($node),60);
            $health=self::summarize([$sample]);
            $health['network_rx_bytes']=$traffic['rx_bytes']??null;$health['network_tx_bytes']=$traffic['tx_bytes']??null;
            $health['network_rx_per_second']=$traffic['rx_per_second']??null;$health['network_tx_per_second']=$traffic['tx_per_second']??null;
            if($health['network_rx_per_second']===null||$health['network_tx_per_second']===null)$ratesAvailable=false;else{$rxRate+=$health['network_rx_per_second'];$txRate+=$health['network_tx_per_second'];}
            $health['metric_states']['network']=$traffic!==null?'healthy':'unavailable';
            // Publish names and utilization only, never connection addresses or daemon credentials.
            $nodes[]=['id'=>$node->id??$index,'name'=>$node->name??'Node '.($index+1),...$health];
            if($traffic===null)$networkAvailable=false;else{$rx+=$traffic['rx_bytes'];$tx+=$traffic['tx_bytes'];}
        }
        $result=self::summarize($samples);
        $result['nodes']=$nodes;
        $result['network_rx_bytes']=$networkAvailable?$rx:null;$result['network_tx_bytes']=$networkAvailable?$tx:null;
        $result['network_rx_per_second']=$ratesAvailable&&$result['hosts']>0?$rxRate:null;$result['network_tx_per_second']=$ratesAvailable&&$result['hosts']>0?$txRate:null;
        $result['metric_states']['network']=$networkAvailable&&$result['hosts']>0?'healthy':'unavailable';
        return $result;
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
        $result=['hosts'=>count($samples),'available'=>0,'cpu_percent'=>null,'memory_used'=>null,'memory_total'=>null,'disk_used'=>null,'disk_total'=>null,'state'=>'unavailable','label'=>'Unavailable','metric_states'=>['cpu'=>'unavailable','memory'=>'unavailable','disk'=>'unavailable']];
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
