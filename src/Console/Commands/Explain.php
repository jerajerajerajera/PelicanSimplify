<?php
namespace Simplify\Panel\Console\Commands;
use Illuminate\Console\Command;
use Simplify\Panel\Services\EmergencyAccess;
use Simplify\Panel\Services\ErrorExplainer;
/**
 * "Explain this error" from the terminal, for when the panel website is down: reads the panel log, a Wings log or a
 * server console and prints Claude's answer. Uses the saved Anthropic key, or asks for one that is used once.
 */
final class Explain extends Command
{
    protected $signature='simplify:explain
        {--source=panel : panel, wings or server=<id> (a server id, short uuid or uuid)}
        {--node= : Node id for --source=wings (needed when there is more than one node)}
        {--lines=100 : At most this many log lines are sent (10 to 100)}
        {--url : Print a temporary address for the emergency page instead (works for 60 minutes, no sign-in needed)}';
    protected $description='Explain the panel log, a Wings log or a server console with Claude, also when the panel shows an error.';

    public function handle(): int
    {
        try{
            if($this->option('url'))return $this->address();
            [$source,$server,$node]=$this->target();
            if($source===null)return self::FAILURE;
            $key=ErrorExplainer::savedKey();
            if(!$key){
                $this->warn('No Anthropic API key is saved in Simplify settings (or it can no longer be read, for example after APP_KEY changed).');
                $key=$this->input->isInteractive()?trim((string)$this->secret('Paste an Anthropic API key to use once (it is not saved)')):'';
                if($key===''){$this->error('An Anthropic API key is needed. Add one in Simplify settings, under Error explanations, or run this command in a terminal to paste one.');return self::FAILURE;}
            }
            $label=match($source){'panel'=>'Panel log','wings'=>'Wings log of '.$node->name,default=>'Console of '.$server->name};
            $this->line('Checking the '.lcfirst($label).'. This can take up to a minute.');
            $lines=max(10,min(ErrorExplainer::LINES,(int)$this->option('lines')));
            $result=ErrorExplainer::ask($key,$source,$server,$node,null,ErrorExplainer::ASKED_FROM['terminal'],$lines);
            return $this->show($label,$result);
        }catch(\Throwable $e){
            report($e);
            $this->error('Could not finish: '.(ErrorExplainer::sanitize([get_class($e).': '.$e->getMessage()])[0]??get_class($e)));
            return self::FAILURE;
        }
    }

    /** [source, server, node]; [null] after printing why the choice does not work. */
    private function target(): array
    {
        $source=strtolower(trim((string)$this->option('source')));
        if($source==='panel')return ['panel',null,null];
        if($source==='wings'){
            $nodes=\App\Models\Node::query()->orderBy('id')->get();
            $node=$this->option('node')!==null?$nodes->firstWhere('id',(int)$this->option('node')):($nodes->count()===1?$nodes->first():null);
            if($node)return ['wings',null,$node];
            $this->error($this->option('node')!==null?'There is no node with id '.$this->option('node').'.':'Choose a node with --node=<id>:');
            foreach($nodes as $item)$this->line('  '.$item->id.'  '.$item->name);
            return [null,null,null];
        }
        if(preg_match('/^server[=:](.+)$/',$source,$match)){
            $id=$match[1];
            $server=\App\Models\Server::query()->where(fn($query)=>ctype_digit($id)?$query->where('id',(int)$id):$query->where('uuid_short',$id)->orWhere('uuid',$id))->first();
            if($server)return ['console',$server,null];
            $this->error('There is no server '.$id.'.');
            return [null,null,null];
        }
        $this->error('Use --source=panel, --source=wings or --source=server=<id>.');
        return [null,null,null];
    }

    private function show(string $label,array $result): int
    {
        if(!$result['ok']){$this->error($result['error']);return self::FAILURE;}
        $this->newLine();
        $found=(bool)$result['found'];
        $this->line($found?'<fg=yellow;options=bold>'.$label.': something needs attention</>':'<fg=green;options=bold>'.$label.': everything seems in order</>');
        $this->line($result['summary']);
        if($result['issues']&&!$found){$this->newLine();$this->line('<fg=gray>Noticed something?</>');}
        foreach($result['issues'] as $number=>$issue){
            $this->newLine();
            $this->line('<options=bold>'.($number+1).'. '.$issue['title'].'</>');
            if($issue['cause']!=='')$this->line('   '.wordwrap($issue['cause'],100,"\n   "));
            foreach($issue['steps'] as $step)$this->line('   - '.wordwrap($step,98,"\n     "));
        }
        if($result['model']){$this->newLine();$this->line('<fg=gray>'.$result['lines'].' lines checked by '.$result['model'].'. Addresses, e-mail addresses and passwords were removed first.</>');}
        return self::SUCCESS;
    }

    private function address(): int
    {
        $address=route('simplify.explain.access',['token'=>EmergencyAccess::issue()]);
        $this->info('Open this address in a browser. It works for '.EmergencyAccess::MINUTES.' minutes and replaces any earlier one:');
        $this->line($address);
        $this->line('<fg=gray>Anyone with this address can ask about the panel and Wings logs, so do not share it.</>');
        return self::SUCCESS;
    }
}
