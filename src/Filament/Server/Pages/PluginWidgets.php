<?php
declare(strict_types=1);
namespace Simplify\Panel\Filament\Server\Pages;

/** Console widgets from other plugins (such as Player Counter) for one server. */
final class PluginWidgets extends \Simplify\Panel\Filament\PluginWidgetsPage
{
    protected static function widgetPanel(): string
    {
        return 'server';
    }

    public function widgetData(): array
    {
        return ['server' => \Filament\Facades\Filament::getTenant(), 'user' => user()];
    }
}
