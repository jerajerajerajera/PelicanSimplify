<?php
namespace Simplify\Panel\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Simplify\Panel\Services\Announcements as Messages;
use Simplify\Panel\Services\InGameAnnouncements;
use Simplify\Panel\Services\PanelUpdates;
use Simplify\Panel\Services\PushNotifications;
/**
 * Scheduled every minute: sends announcements that start now as app notifications and in game (again when they repeat), and every ten minutes looks for
 * new versions of Simplify, other plugins, Wings and Pelican (the Panel's announcements) and notifies root admins once per version.
 */
final class Announcements extends Command
{
    protected $signature='simplify:announcements';
    protected $description='Send announcements as app notifications and announce new Simplify, plugin, Wings and Pelican versions.';
    public function handle(): int
    {
        $now=time();
        // Heartbeat for the notifications window: shows whether Pelican's scheduler (cron) runs, which real notifications need.
        Cache::forever('simplify.announcements-at',$now);
        // Every ten minutes, and right after Simplify itself was updated.
        if((int)Cache::get('simplify.panel-updates-at',0)<=$now-600||!PanelUpdates::checked()){
            Cache::forever('simplify.panel-updates-at',$now);
            $count=count(PanelUpdates::refresh());
            $this->info($count?$count.' update(s) available.':'Everything is up to date.');
        }
        $accounts=PushNotifications::accounts();
        $users=$accounts?\App\Models\User::query()->whereIn('id',$accounts)->get():collect();
        foreach(Messages::duePush($now) as $row){
            // Marked first, so an overlapping run or a crash never sends it twice.
            Messages::markPushed($row['id'],$now);
            $item=Messages::present($row,$now);
            $message=['title'=>$row['title'],'body'=>$row['text'],'url'=>$item['action']['url']??self::home(),'tag'=>'announcement-'.$row['id']];
            $sent=0;
            foreach($users as $user)if($row['audience']==='everyone'||$user->isAdmin())$sent+=$this->send($user,'announcements',$message);
            $this->info('"'.$row['title'].'" sent to '.$sent.' device(s).');
        }
        foreach(InGameAnnouncements::run($now) as $title=>$result)$this->info('"'.$title.'" in game: '.implode(', ',InGameAnnouncements::summary($result)).'.');
        foreach(PanelUpdates::announcements($now) as $item){
            if(!Cache::add('simplify.push-sent.'.$item['key'],true,now()->addDays(90)))continue;
            foreach($users as $user)if($user->isRootAdmin())$this->send($user,'updates',['title'=>$item['title'],'body'=>$item['text'],'url'=>self::home(),'tag'=>$item['key']]);
        }
        return self::SUCCESS;
    }

    private function send(object $user,string $kind,array $message): int
    {
        try{return PushNotifications::send($user,$kind,$message);}catch(\Throwable $e){report($e);return 0;}
    }

    private static function home(): string
    {
        try{return \Simplify\Panel\Filament\App\Pages\Dashboard::getUrl(panel:'app');}catch(\Throwable){return url('/');}
    }
}
