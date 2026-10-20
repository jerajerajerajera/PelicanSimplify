<?php
namespace Simplify\Panel\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Server;
use Simplify\Panel\Services\PendingChanges;
use Simplify\Panel\Services\AutomaticBackups;
final class Maintenance extends Command
{
    protected $signature='simplify:maintenance';
    protected $description='Apply scheduled changes and rotate automatic server backups.';
    public function handle(): int
    {
        self::restartWorkersAfterUpdate();
        $disk=\Illuminate\Support\Facades\Storage::disk('local');$ids=[];
        $directories=['simplify/pending'];
        if(\Simplify\Panel\Services\FeatureSettings::enabled('automatic_backups'))$directories[]='simplify/backups';
        foreach($directories as $directory)foreach($disk->files($directory) as $path){
            $uuid=pathinfo($path,PATHINFO_FILENAME);
            if(preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i',$uuid))$ids[$uuid]=true;
        }
        foreach(array_chunk(array_keys($ids),100) as $chunk)
        foreach(Server::with(['node','egg','allocation'])->whereIn('uuid',$chunk)->lazyById(100) as $s) {
            try { PendingChanges::tick($s); } catch(\Throwable $e) { report($e);PendingChanges::failed($s,$e->getMessage());$this->error($s->name.': '.$e->getMessage()); }
            finally { auth()->forgetUser(); }
            try { if(\Simplify\Panel\Services\FeatureSettings::enabled('automatic_backups')) AutomaticBackups::tick($s); } catch(\Throwable $e) { report($e);$this->error($s->name.': '.$e->getMessage()); }
        }
        return self::SUCCESS;
    }

    /** Queue workers keep the old plugin code in memory; after an update they are restarted, like queue:restart. */
    public static function restartWorkersAfterUpdate(): void
    {
        $version=\Simplify\Panel\Services\BuildInfo::version();
        if($version===null)return;
        $cache=app('cache')->store();
        if($cache->get('simplify.worker-version')===$version)return;
        $cache->forever('illuminate:queue:restart',time());
        $cache->forever('simplify.worker-version',$version);
    }
}
