<?php
namespace Simplify\Panel\Console\Commands;
use Illuminate\Console\Command;
final class Subdomains extends Command
{
    protected $signature='simplify:subdomains';
    protected $description='Retry failed subdomains, follow port changes and remove records of deleted servers.';
    public function handle(): int
    {
        if(\Simplify\Panel\Services\FeatureSettings::enabled('subdomains'))\Simplify\Panel\Services\Subdomains::tick();
        return self::SUCCESS;
    }
}
