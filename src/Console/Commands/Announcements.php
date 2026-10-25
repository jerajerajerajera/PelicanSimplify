<?php
namespace Simplify\Panel\Console\Commands;
use Illuminate\Console\Command;
/** Scheduled every ten minutes: looks for new versions of Simplify, other plugins, Wings and Pelican (the Panel's announcements). */
final class Announcements extends Command
{
    protected $signature='simplify:announcements';
    protected $description='Check for new Simplify, plugin, Wings and Pelican versions and announce them to root admins.';
    public function handle(): int
    {
        $count=count(\Simplify\Panel\Services\PanelUpdates::refresh());
        $this->info($count?$count.' update(s) available.':'Everything is up to date.');
        return self::SUCCESS;
    }
}
