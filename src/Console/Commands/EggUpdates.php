<?php
namespace Simplify\Panel\Console\Commands;
use Illuminate\Console\Command;
/**
 * Scheduled every minute; checks the eggs' update URLs every six hours, announces new versions to root admins and,
 * when switched on, updates the eggs no server uses.
 */
final class EggUpdates extends Command
{
    protected $signature = 'simplify:egg-updates {--force : Check now and also when egg updates are off}';
    protected $description = 'Check eggs for updates, announce them and update eggs without servers when switched on.';
    public function handle(): int
    {
        foreach (\Simplify\Panel\Services\EggUpdates::run((bool) $this->option('force')) as $message) $this->info($message);
        return self::SUCCESS;
    }
}
