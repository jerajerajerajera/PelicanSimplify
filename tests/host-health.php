<?php
require __DIR__.'/../src/Services/HostHealth.php';
use Simplify\Panel\Services\HostHealth;
$sample=['maintenance'=>false,'stats'=>['cpu_percent'=>20,'memory_used'=>4,'memory_total'=>16,'disk_used'=>30,'disk_total'=>100]];
$check=function($condition){if(!$condition)throw new RuntimeException('Host health assertion failed');};
$r=HostHealth::summarize([$sample]);$check($r['state']==='healthy'&&$r['cpu_percent']===20.0);
$high=$sample;$high['stats']['cpu_percent']=90;$check(HostHealth::summarize([$high])['state']==='warning');
$high=$sample;$high['stats']['memory_used']=15.5;$check(HostHealth::summarize([$high])['state']==='critical');
$high=$sample;$high['stats']['disk_used']=96;$check(HostHealth::summarize([$high])['state']==='critical');
$missing=['stats'=>['memory_total'=>0]];$r=HostHealth::summarize([$missing]);$check($r['state']==='unavailable'&&$r['cpu_percent']===null);
$r=HostHealth::summarize([$sample,$missing]);$check($r['label']==='Partly unavailable'&&$r['available']===1&&$r['hosts']===2);
$second=$sample;$second['stats']['cpu_percent']=40;$r=HostHealth::summarize([$sample,$second]);$check($r['cpu_percent']===30.0&&$r['memory_total']===32.0&&$r['disk_used']===60.0);
$maintenance=$sample;$maintenance['maintenance']=true;$check(HostHealth::summarize([$maintenance])['label']==='Maintenance');
$check(HostHealth::summarize([])['state']==='unavailable');
$criticalCpu=$sample;$criticalCpu['stats']['cpu_percent']=97;$r=HostHealth::summarize([$criticalCpu,$sample]);$check($r['metric_states']['cpu']==='critical'&&$r['state']==='critical');
$r=HostHealth::summarize([$sample,$missing]);$check($r['metric_states']['memory']==='unavailable');
$r=HostHealth::summarize([$high]);$check($r['metric_states']['disk']==='critical'&&$r['metric_states']['cpu']==='healthy');
echo "PASS 12 host health checks: native totals, pressure thresholds, partial/no telemetry, maintenance and multiple hosts\n";
$check(HostHealth::summarize([])['problem']===null);
$check(HostHealth::httpProblem(403)==='Wings refused the request (HTTP 403)'&&HostHealth::httpProblem(530)==='Wings is unreachable (HTTP 530)'&&HostHealth::httpProblem(404)==='Wings answered with HTTP 404');
echo "PASS 2 host health problem checks\n";
// The refresh button must not re-render the page: a re-render drops the dock's measured width and the tile jumps to full width.
$dashboard=file_get_contents(__DIR__.'/../src/Filament/App/Pages/Dashboard.php');
$check((bool)preg_match('/#\[Renderless\]\s*public function refreshHostHealth\(/',$dashboard));
echo "PASS host health refresh stays renderless\n";
