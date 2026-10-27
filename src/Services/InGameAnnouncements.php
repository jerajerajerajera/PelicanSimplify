<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
/**
 * "Also send in game": an announcement posted in the chat of running game servers, with the chat command of each
 * server's egg (say for Minecraft, servermsg for Project Zomboid, …) through Wings' console. Sent by the scheduler
 * (simplify:announcements, every minute) when the announcement starts and, when asked, again every few minutes while it
 * shows. Root admins can change or add the command per egg under Announcements → In-game commands.
 */
final class InGameAnnouncements
{
    /** Per egg id: the admin's own command, or "-" to skip that game. */
    public const COMMANDS_FILE='simplify/announcements-ingame-commands.json';
    /** Per announcement id: when it was last sent in game and how that went. */
    public const SENT_FILE='simplify/announcements-ingame.json';
    /** @lang announcements.repeats / announcements.targets: shown through Translations::labels(). */
    public const REPEATS=[0=>'Only once',5=>'Every 5 minutes',10=>'Every 10 minutes',15=>'Every 15 minutes',30=>'Every 30 minutes',60=>'Every hour',180=>'Every 3 hours'];
    public const TARGETS=['all'=>'All servers','pick'=>'Choose'];
    public const PLACEHOLDER='{message}';
    public const MESSAGE_LENGTH=240;
    public const COMMAND_LENGTH=200;
    /** At most this many servers per announcement and run, so a large panel cannot keep the scheduler busy. */
    public const SERVERS=200;
    private const PICKS=100;
    /** Wait between two commands to the same node, in microseconds. */
    private const PAUSE=50000;

    /**
     * The chat command Simplify knows for an egg, by its name; null when the game has no chat command on its console
     * (Palworld, Valheim, Velocity, …). Checked from top to bottom.
     */
    private const DEFAULTS=[
        '/velocity/i'=>null,
        '/bungee|waterfall|travertine|flamecord/i'=>'alert {message}',
        '/minecraft|vanilla|paper|purpur|spigot|bukkit|folia|forge|fabric|quilt|sponge|modrinth|curseforge|ftb|tekkit|hexxit|technic|atlauncher|bedrock|nukkit|pocketmine|mohist|arclight|magma|modpack/i'=>'say {message}',
        '/counter-?strike|\bcs ?2\b|cs ?: ?go|garry|gmod|team fortress|\btf2\b|left 4 dead|\bl4d|insurgency|day of defeat|no more room|synergy|fistful of frags/i'=>'say {message}',
        '/\brust\b|terraria|tmodloader|tshock|unturned/i'=>'say {message}',
        '/zomboid/i'=>'servermsg "{message}"',
        '/factorio/i'=>'{message}',
    ];

    /** The default command for an egg name, or null. */
    public static function defaultCommand(string $egg): ?string
    {
        foreach(self::DEFAULTS as $pattern=>$command)if(preg_match($pattern,$egg))return $command;
        return null;
    }

    /** The admin's own commands per egg id. */
    public static function commands(): array
    {
        $commands=[];
        foreach(JsonFile::read(self::COMMANDS_FILE) as $egg=>$command)if(is_numeric($egg)&&is_string($command)&&self::validCommand($command))$commands[(int)$egg]=$command;
        return $commands;
    }

    /** Saves the admin's own commands ([egg id => command]); empty means "automatic", "-" means "skip this game". */
    public static function saveCommands(array $input): array
    {
        $commands=[];
        foreach($input as $egg=>$command){
            $command=is_string($command)?trim($command):'';
            if(!is_numeric($egg)||$command==='')continue;
            if(!self::validCommand($command))throw new \InvalidArgumentException('Use a single line of at most '.self::COMMAND_LENGTH.' characters with '.self::PLACEHOLDER.' in it, or - to skip a game.');
            $commands[(int)$egg]=$command;
        }
        if(!Storage::disk('local')->put(self::COMMANDS_FILE,json_encode($commands,JSON_THROW_ON_ERROR|JSON_FORCE_OBJECT)))throw new \RuntimeException('Could not save the in-game commands.');
        return $commands;
    }

    /** The command for one egg (own command first, then the default), null when this game is skipped. */
    public static function commandFor(int $eggId,string $eggName,?array $own=null): ?string
    {
        $own??=self::commands();
        if(isset($own[$eggId]))return $own[$eggId]==='-'?null:$own[$eggId];
        return self::defaultCommand($eggName);
    }

    /** What players read: "[Announcement] Title - text" on one line. */
    public static function message(array $row): string
    {
        $text=trim((string)preg_replace('/\s+/u',' ',(string)($row['text']??'')));
        $line='[Announcement] '.$row['title'].($text!==''?' - '.$text:'');
        // No control characters (a line break would be a second console command) and no double quotes, which some commands wrap the message in.
        $line=str_replace('"',"'",(string)preg_replace('/[\x00-\x1F\x7F]/u','',$line));
        return mb_strlen($line)>self::MESSAGE_LENGTH?rtrim(mb_substr($line,0,self::MESSAGE_LENGTH-1)).'…':$line;
    }

    public static function render(string $command,string $message): string
    {
        return str_replace(self::PLACEHOLDER,$message,$command);
    }

    /** The "Also send in game" part of an announcement, from the form fields or a saved row. Throws \InvalidArgumentException for the form. */
    public static function fromInput(array $input,?array $old=null,?int $editor=null): array
    {
        $saved=is_array($input['ingame']??null)?$input['ingame']:null;
        $settings=$saved??[
            'enabled'=>!empty($input['ingame']),'targets'=>$input['ingame_targets']??'all','servers'=>$input['ingame_servers']??[],
            'groups'=>$input['ingame_groups']??[],'repeat'=>$input['ingame_repeat']??0,
            // Groups are an account's own: they are looked up in the account that picked them.
            'groups_of'=>$input['ingame_groups_of']??$old['ingame']['groups_of']??$editor,
        ];
        $clean=self::clean($settings);
        if($clean['enabled']&&$clean['targets']==='pick'&&$clean['servers']===[]&&$clean['groups']===[])throw new \InvalidArgumentException('Pick at least one server or server group to send it to in game.');
        return $clean;
    }

    public static function clean(mixed $settings): array
    {
        $settings=is_array($settings)?$settings:[];
        $ids=fn(mixed $list,string $pattern):array=>array_slice(array_values(array_unique(array_filter(is_array($list)?$list:[],fn($id)=>is_string($id)&&preg_match($pattern,$id)))),0,self::PICKS);
        $repeat=(int)($settings['repeat']??0);
        $owner=$settings['groups_of']??null;
        return ['enabled'=>($settings['enabled']??false)===true,'targets'=>isset(self::TARGETS[$settings['targets']??''])?$settings['targets']:'all',
            'servers'=>$ids($settings['servers']??[],'/^[A-Za-z0-9-]{1,64}$/'),'groups'=>$ids($settings['groups']??[],'/^[A-Za-z0-9_-]{1,40}$/'),
            'repeat'=>isset(self::REPEATS[$repeat])?$repeat:0,'groups_of'=>is_numeric($owner)?(int)$owner:null];
    }

    /** Announcements that should go out in game now: live, switched on, never sent or due for a repeat. */
    public static function due(array $rows,?int $now=null): array
    {
        $now??=time();
        $sent=self::sent();
        return array_values(array_filter($rows,function(array $row) use ($sent,$now):bool{
            if(!($row['ingame']['enabled']??false)||Announcements::state($row,$now)!=='live')return false;
            $last=$sent[$row['id']]['at']??null;
            if($last===null)return true;
            $repeat=$row['ingame']['repeat'];
            // A few seconds early is fine: the scheduler runs every minute, not exactly on the minute.
            return $repeat>0&&$last+$repeat*60-20<=$now;
        }));
    }

    /** Per announcement id: ['at' => time, 'sent' => n, 'offline' => n, 'unsupported' => n, 'failed' => n]. */
    public static function sent(): array
    {
        $rows=[];
        foreach(JsonFile::read(self::SENT_FILE) as $id=>$row)if(is_string($id)&&is_array($row)&&is_int($row['at']??null))$rows[$id]=['at'=>$row['at'],...array_map('intval',array_intersect_key($row,array_flip(['sent','offline','unsupported','failed'])))];
        return $rows;
    }

    /** Forgets that an announcement was sent in game, so it goes out again (its start moved, or "send in game" was just switched on). */
    public static function forget(string $id): void
    {
        $sent=self::sent();
        if(!isset($sent[$id]))return;
        unset($sent[$id]);
        self::writeSent($sent);
    }

    /** The next time a repeating announcement goes out in game, or null. */
    public static function next(array $row,?int $now=null): ?int
    {
        $now??=time();
        if(!($row['ingame']['enabled']??false)||Announcements::state($row,$now)==='ended')return null;
        if(Announcements::state($row,$now)==='scheduled')return $row['starts_at'];
        $last=self::sent()[$row['id']]['at']??null;
        if($last===null)return $now;
        $next=$row['ingame']['repeat']>0?$last+$row['ingame']['repeat']*60:null;
        return $next!==null&&($row['ends_at']===null||$next<$row['ends_at'])?$next:null;
    }

    /** Sends every due announcement; returns [title => result] for the console. */
    public static function run(?int $now=null): array
    {
        $now??=time();
        $results=[];
        foreach(self::due(Announcements::all(),$now) as $row){
            // One run per announcement and minute, even when two schedulers overlap.
            if(!Cache::add('simplify.ingame.'.$row['id'].'.'.intdiv($now,60),true,120))continue;
            self::record($row['id'],['at'=>$now]); // Marked first, so a crash halfway never sends it twice in a row.
            try{$result=self::send($row);}catch(\Throwable $e){report($e);$result=['sent'=>0,'offline'=>0,'unsupported'=>0,'failed'=>1];}
            self::record($row['id'],['at'=>$now,...$result]);
            $results[$row['title']]=$result;
        }
        return $results;
    }

    /** Sends one announcement to its running servers now. */
    public static function send(array $row): array
    {
        $result=['sent'=>0,'offline'=>0,'unsupported'=>0,'failed'=>0];
        $servers=self::targets($row['ingame']);
        if($servers===[])return $result;
        $message=self::message($row);
        $own=self::commands();
        $byNode=[];
        foreach($servers as $server){
            $command=self::commandFor((int)$server->egg_id,(string)($server->egg?->name??''),$own);
            if($command===null){$result['unsupported']++;continue;}
            if(!$server->node||(method_exists($server,'isSuspended')&&$server->isSuspended())){$result['offline']++;continue;}
            $byNode[(int)$server->node_id][]=[$server,self::render($command,$message)];
        }
        foreach($byNode as $list){
            $node=$list[0][0]->node;
            $states=WingsServers::forNode($node);
            foreach($list as $index=>[$server,$command]){
                if(($states['servers'][$server->uuid]['state']??null)!=='running'){$result['offline']++;continue;}
                if($index>0)usleep(self::PAUSE);
                try{
                    $response=Http::daemon($node)->connectTimeout(2)->timeout(4)->post('/api/servers/'.$server->uuid.'/commands',['commands'=>[$command]]);
                    $response->successful()?$result['sent']++:$result['failed']++;
                }catch(\Throwable){$result['failed']++;}
            }
        }
        return $result;
    }

    /** The servers an announcement goes to: all, or the picked servers plus the servers in the picked groups. */
    public static function targets(array $settings): array
    {
        $query=\App\Models\Server::query()->with(['egg','node']);
        if($settings['targets']==='pick'){
            $uuids=$settings['servers'];
            if($settings['groups']!==[]&&$settings['groups_of']!==null&&($owner=\App\Models\User::query()->find($settings['groups_of'])))$uuids=[...$uuids,...self::groupServers($owner,$settings['groups'])];
            if($uuids===[])return [];
            $query->whereIn('uuid',array_values(array_unique($uuids)));
        }
        return $query->orderBy('id')->limit(self::SERVERS)->get()->all();
    }

    /** The server uuids in these groups of an account. */
    public static function groupServers(object $user,array $groups): array
    {
        $wanted=array_flip($groups);
        $uuids=[];
        foreach(OverviewGroups::get($user)['groups']??[] as $group)if(isset($wanted[$group['id']]))array_push($uuids,...$group['servers']);
        return $uuids;
    }

    /** "Sent to 4 servers", "1 offline", … for the announcements list. */
    public static function summary(array $result): array
    {
        $parts=[];
        $parts[]=trans_choice('pelican-simplify::announcements.summary.sent', $result['sent']);
        if($result['offline']??0)$parts[]=__('pelican-simplify::announcements.summary.offline', ['count'=>$result['offline']]);
        if($result['unsupported']??0)$parts[]=__('pelican-simplify::announcements.summary.unsupported', ['count'=>$result['unsupported']]);
        if($result['failed']??0)$parts[]=__('pelican-simplify::announcements.summary.failed', ['count'=>$result['failed']]);
        return $parts;
    }

    private static function validCommand(string $command): bool
    {
        if($command==='-')return true;
        return mb_strlen($command)<=self::COMMAND_LENGTH&&str_contains($command,self::PLACEHOLDER)&&!preg_match('/[\x00-\x1F\x7F]/u',$command);
    }

    private static function record(string $id,array $result): void
    {
        $sent=self::sent();
        $sent[$id]=$result;
        // Keep the newest 100.
        uasort($sent,fn(array $a,array $b):int=>$b['at']<=>$a['at']);
        self::writeSent(array_slice($sent,0,100,true));
    }

    private static function writeSent(array $sent): void
    {
        if(!Storage::disk('local')->put(self::SENT_FILE,json_encode($sent,JSON_THROW_ON_ERROR|JSON_FORCE_OBJECT)))throw new \RuntimeException('Could not save the in-game announcements.');
    }
}
